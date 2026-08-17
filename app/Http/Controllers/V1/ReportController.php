<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetInventoryReportRequest;
use App\Http\Requests\GetReportRequest;
use App\Models\QualityInspection;
use App\Models\StockBag;
use App\Models\StockInBatch;
use App\Traits\ActivityLogTrait;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Report Index', ['only' => ['batchWise']]),
            new Middleware('permission:InventoryReport Index', ['only' => ['balance', 'valuation', 'aging', 'alerts']]),
        ];
    }

    /**
     * Batch wise report with bags, vehicle, warehouse, branch details.
     */
    public function batchWise(GetReportRequest $request)
    {
        try {
            $validated = $request->validated();

            $query = StockInBatch::query()
                ->with([
                    'supplier:id,name,code',
                    'warehouse:id,name,code,branch_id',
                    'warehouse.branch:id,name,code',
                    'vehicle:id,vehicle_number',
                    'vehicleLog:id,log_number,entry_time,exit_time,driver_name,driver_phone',
                    'items:id,stock_in_batch_id,item_type_id,item_variety_id,quantity_bags,total_weight,unit_price,total_price',
                    'items.itemType:id,name,code',
                    'items.itemVariety:id,name,code',
                ])
                ->withCount([
                    'bags as total_bags_count',
                    'bags as in_stock_count' => function ($q) {
                        $q->where('status', 'in_stock');
                    },
                    'bags as dispatched_count' => function ($q) {
                        $q->where('status', 'dispatched');
                    },
                    'bags as damaged_count' => function ($q) {
                        $q->where('status', 'damaged');
                    },
                    'bags as returned_count' => function ($q) {
                        $q->where('status', 'returned');
                    }
                ])
                ->withSum('bags as total_weight', 'bag_weight');

            // Apply tenancy constraints
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('stock_in_batches.warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Apply filters
            if (!empty($validated['start_date'])) {
                $query->where('stock_in_batches.received_date', '>=', $validated['start_date']);
            }
            if (!empty($validated['end_date'])) {
                $query->where('stock_in_batches.received_date', '<=', $validated['end_date']);
            }
            if (!empty($validated['warehouse_id'])) {
                $query->where('stock_in_batches.warehouse_id', $validated['warehouse_id']);
            }
            if (!empty($validated['branch_id'])) {
                $query->whereHas('warehouse', function ($q) use ($validated) {
                    $q->where('branch_id', $validated['branch_id']);
                });
            }
            if (!empty($validated['supplier_id'])) {
                $query->where('stock_in_batches.supplier_id', $validated['supplier_id']);
            }
            if (!empty($validated['status'])) {
                $query->where('stock_in_batches.status', $validated['status']);
            }

            $perPage = $validated['per_page'] ?? 15;
            $batches = $query->orderBy('stock_in_batches.id', 'desc')->paginate($perPage);

            // Attach bags summary to each batch using Eloquent aggregate attributes
            $batches->getCollection()->transform(function ($batch) {
                $batch->bags_summary = [
                    'total_bags' => (int) $batch->total_bags_count,
                    'in_stock' => (int) $batch->in_stock_count,
                    'dispatched' => (int) $batch->dispatched_count,
                    'damaged' => (int) $batch->damaged_count,
                    'returned' => (int) $batch->returned_count,
                    'total_weight' => (float) ($batch->total_weight ?? 0.0),
                ];
                return $batch;
            });

            // Calculate summary totals
            $summaryQuery = StockInBatch::query();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $summaryQuery->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }
            if (!empty($validated['start_date'])) {
                $summaryQuery->where('received_date', '>=', $validated['start_date']);
            }
            if (!empty($validated['end_date'])) {
                $summaryQuery->where('received_date', '<=', $validated['end_date']);
            }
            if (!empty($validated['warehouse_id'])) {
                $summaryQuery->where('warehouse_id', $validated['warehouse_id']);
            }
            if (!empty($validated['branch_id'])) {
                $summaryQuery->whereHas('warehouse', function ($q) use ($validated) {
                    $q->where('branch_id', $validated['branch_id']);
                });
            }
            if (!empty($validated['supplier_id'])) {
                $summaryQuery->where('supplier_id', $validated['supplier_id']);
            }
            if (!empty($validated['status'])) {
                $summaryQuery->where('status', $validated['status']);
            }

            $totalBatches = $summaryQuery->count();
            $totalBags = (clone $summaryQuery)->sum('total_bags');
            $totalWeight = (clone $summaryQuery)->sum('net_weight');
            $totalAmount = (clone $summaryQuery)->sum('total_amount');

            $this->logActivity('INDEX', 'Report', 'Retrieved batch wise report');

            return response()->json([
                'status' => 'success',
                'message' => 'Batch wise report retrieved successfully',
                'data' => [
                    'summary' => [
                        'total_batches' => (int) $totalBatches,
                        'total_bags' => (int) $totalBags,
                        'total_weight' => (float) $totalWeight,
                        'total_amount' => (float) $totalAmount,
                    ],
                    'batches' => $batches,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve batch wise report',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Real-time stock balance summary grouped by Warehouse, Branch, Item Type, Item Variety, Grade, and Bag Count.
     * Includes batch-level breakdown under each group.
     */
    public function balance(GetInventoryReportRequest $request)
    {
        try {
            $validated = $request->validated();

            $query = StockBag::where('stock_bags.status', 'in_stock')
                ->leftJoin('quality_inspections', function ($join) {
                    $join->on('stock_bags.id', '=', 'quality_inspections.stock_bag_id')
                         ->whereRaw('quality_inspections.id = (SELECT max(qi.id) FROM quality_inspections qi WHERE qi.stock_bag_id = stock_bags.id)');
                })
                ->leftJoin('stock_in_batches', 'stock_bags.stock_in_batch_id', '=', 'stock_in_batches.id')
                ->select([
                    'stock_bags.branch_id',
                    'stock_bags.warehouse_id',
                    'stock_bags.item_type_id',
                    'stock_bags.item_variety_id',
                    DB::raw('COALESCE(quality_inspections.grade, "A") as grade'),
                    DB::raw('count(stock_bags.id) as total_bags'),
                    DB::raw('sum(stock_bags.bag_weight) as total_weight')
                ]);

            // Apply tenancy constraints
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('stock_bags.warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Apply Filters
            if (!empty($validated['branch_id'])) {
                $query->where('stock_bags.branch_id', $validated['branch_id']);
            }
            if (!empty($validated['warehouse_id'])) {
                $query->where('stock_bags.warehouse_id', $validated['warehouse_id']);
            }
            if (!empty($validated['item_type_id'])) {
                $query->where('stock_bags.item_type_id', $validated['item_type_id']);
            }
            if (!empty($validated['item_variety_id'])) {
                $query->where('stock_bags.item_variety_id', $validated['item_variety_id']);
            }
            if (!empty($validated['from_date'])) {
                $query->where('stock_in_batches.received_date', '>=', $validated['from_date']);
            }
            if (!empty($validated['to_date'])) {
                $query->where('stock_in_batches.received_date', '<=', $validated['to_date']);
            }

            $results = $query->groupBy(
                'stock_bags.branch_id',
                'stock_bags.warehouse_id',
                'stock_bags.item_type_id',
                'stock_bags.item_variety_id',
                'grade'
            )->get();

            // Load relations on the aggregates for output formatting
            $results->load([
                'branch:id,name,code',
                'warehouse:id,name,code',
                'itemType:id,name,code',
                'itemVariety:id,name,code'
            ]);

            // Fetch batch-level breakdown for each group
            $results->each(function ($item) use ($validated) {
                $batches = StockBag::where('stock_bags.status', 'in_stock')
                    ->where('stock_bags.warehouse_id', $item->warehouse_id)
                    ->where('stock_bags.branch_id', $item->branch_id)
                    ->where('stock_bags.item_type_id', $item->item_type_id)
                    ->where('stock_bags.item_variety_id', $item->item_variety_id)
                    ->leftJoin('quality_inspections', function ($join) {
                        $join->on('stock_bags.id', '=', 'quality_inspections.stock_bag_id')
                             ->whereRaw('quality_inspections.id = (SELECT max(qi.id) FROM quality_inspections qi WHERE qi.stock_bag_id = stock_bags.id)');
                    })
                    ->whereRaw('COALESCE(quality_inspections.grade, "A") = ?', [$item->grade])
                    ->leftJoin('stock_in_batches', 'stock_bags.stock_in_batch_id', '=', 'stock_in_batches.id')
                    ->leftJoin('suppliers', 'stock_in_batches.supplier_id', '=', 'suppliers.id');

                if (!empty($validated['from_date'])) {
                    $batches->where('stock_in_batches.received_date', '>=', $validated['from_date']);
                }
                if (!empty($validated['to_date'])) {
                    $batches->where('stock_in_batches.received_date', '<=', $validated['to_date']);
                }

                $batches = $batches->select([
                        'stock_bags.stock_in_batch_id',
                        'stock_in_batches.batch_number as batch_code',
                        'stock_in_batches.received_date',
                        'suppliers.id as supplier_id',
                        'suppliers.name as supplier_name',
                        DB::raw('count(stock_bags.id) as total_bags'),
                        DB::raw('sum(stock_bags.bag_weight) as total_weight'),
                        DB::raw('sum(stock_bags.total_price) as total_cost'),
                        DB::raw('sum(stock_bags.total_sales_amount) as expected_sales'),
                        DB::raw('ROUND(sum(stock_bags.bag_weight) / count(stock_bags.id), 2) as avg_weight_per_bag'),
                    ])
                    ->groupBy(
                        'stock_bags.stock_in_batch_id',
                        'stock_in_batches.batch_number',
                        'stock_in_batches.received_date',
                        'suppliers.id',
                        'suppliers.name'
                    )
                    ->orderBy('stock_in_batches.received_date', 'desc')
                    ->get();

                // Add bag status counts per batch (from all bags in that batch, not just in_stock)
                $batches->each(function ($batch) {
                    $bagCounts = StockBag::where('stock_in_batch_id', $batch->stock_in_batch_id)
                        ->selectRaw('
                            count(id) as total_bags,
                            sum(case when status = "in_stock" then 1 else 0 end) as in_stock,
                            sum(case when status = "dispatched" then 1 else 0 end) as dispatched,
                            sum(case when status = "damaged" then 1 else 0 end) as damaged,
                            sum(case when status = "returned" then 1 else 0 end) as returned
                        ')
                        ->first();

                    $batch->bag_status = [
                        'total_bags' => (int) $bagCounts->total_bags,
                        'in_stock' => (int) $bagCounts->in_stock,
                        'dispatched' => (int) $bagCounts->dispatched,
                        'damaged' => (int) $bagCounts->damaged,
                        'returned' => (int) $bagCounts->returned,
                    ];
                });

                $item->batches = $batches;
            });

            // Calculate grand totals
            $totalBags = $results->sum('total_bags');
            $totalWeight = (float) $results->sum('total_weight');

            // Add per-row calculations
            $results->each(function ($item) use ($totalBags, $totalWeight) {
                $item->avg_weight_per_bag = $item->total_bags > 0
                    ? round((float) $item->total_weight / $item->total_bags, 2)
                    : 0;
                $item->weight_percentage = $totalWeight > 0
                    ? round(((float) $item->total_weight / $totalWeight) * 100, 2)
                    : 0;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Stock balance summary retrieved successfully',
                'data' => [
                    'summary' => [
                        'total_bags' => (int) $totalBags,
                        'total_weight' => round($totalWeight, 2),
                        'total_groups' => $results->count(),
                    ],
                    'stocks' => $results,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock balance summary',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Stock valuation report (total cost value vs expected sales value).
     */
    public function valuation(GetInventoryReportRequest $request)
    {
        try {
            $validated = $request->validated();

            $query = StockBag::where('status', 'in_stock')
                ->select([
                    'branch_id',
                    'warehouse_id',
                    'item_type_id',
                    'item_variety_id',
                    DB::raw('count(id) as total_bags'),
                    DB::raw('sum(bag_weight) as total_weight'),
                    DB::raw('sum(total_price) as total_cost_value'),
                    DB::raw('sum(total_sales_amount) as expected_sales_value'),
                    DB::raw('sum(total_sales_amount) - sum(total_price) as expected_profit_margin')
                ]);

            // Tenancy scoping
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Filters
            if (!empty($validated['branch_id'])) {
                $query->where('branch_id', $validated['branch_id']);
            }
            if (!empty($validated['warehouse_id'])) {
                $query->where('warehouse_id', $validated['warehouse_id']);
            }
            if (!empty($validated['item_type_id'])) {
                $query->where('item_type_id', $validated['item_type_id']);
            }
            if (!empty($validated['item_variety_id'])) {
                $query->where('item_variety_id', $validated['item_variety_id']);
            }

            $results = $query->groupBy('branch_id', 'warehouse_id', 'item_type_id', 'item_variety_id')->get();

            $results->load([
                'branch:id,name,code',
                'warehouse:id,name,code',
                'itemType:id,name,code',
                'itemVariety:id,name,code'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock valuation report retrieved successfully',
                'data' => $results,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock valuation report',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Stock aging report. Grouped by Warehouse/Variety into 30, 90, 180, and 180+ day buckets.
     */
    public function aging(GetInventoryReportRequest $request)
    {
        try {
            $validated = $request->validated();

            $query = StockBag::where('status', 'in_stock')
                ->select([
                    'branch_id',
                    'warehouse_id',
                    'item_type_id',
                    'item_variety_id',
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) <= 30 THEN 1 ELSE 0 END) as age_0_30_count"),
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) <= 30 THEN bag_weight ELSE 0 END) as age_0_30_weight"),
                    
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) BETWEEN 31 AND 90 THEN 1 ELSE 0 END) as age_31_90_count"),
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) BETWEEN 31 AND 90 THEN bag_weight ELSE 0 END) as age_31_90_weight"),
                    
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) BETWEEN 91 AND 180 THEN 1 ELSE 0 END) as age_91_180_count"),
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) BETWEEN 91 AND 180 THEN bag_weight ELSE 0 END) as age_91_180_weight"),
                    
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) > 180 THEN 1 ELSE 0 END) as age_180_plus_count"),
                    DB::raw("SUM(CASE WHEN DATEDIFF(NOW(), created_at) > 180 THEN bag_weight ELSE 0 END) as age_180_plus_weight"),
                    
                    DB::raw("count(id) as total_bags"),
                    DB::raw("sum(bag_weight) as total_weight")
                ]);

            // Tenancy scoping
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Filters
            if (!empty($validated['branch_id'])) {
                $query->where('branch_id', $validated['branch_id']);
            }
            if (!empty($validated['warehouse_id'])) {
                $query->where('warehouse_id', $validated['warehouse_id']);
            }
            if (!empty($validated['item_type_id'])) {
                $query->where('item_type_id', $validated['item_type_id']);
            }
            if (!empty($validated['item_variety_id'])) {
                $query->where('item_variety_id', $validated['item_variety_id']);
            }

            $results = $query->groupBy('branch_id', 'warehouse_id', 'item_type_id', 'item_variety_id')->get();

            $results->load([
                'branch:id,name,code',
                'warehouse:id,name,code',
                'itemType:id,name,code',
                'itemVariety:id,name,code'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock aging report retrieved successfully',
                'data' => $results,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock aging report',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Stock aging and moisture drop alerts.
     */
    public function alerts(GetInventoryReportRequest $request)
    {
        try {
            $validated = $request->validated();
            $warehouseIds = null;

            // Resolve tenancy
            $authUser = auth('api')->user();
            if ($authUser) {
                $warehouseIds = $authUser->getAccessibleWarehouseIds();
            }

            // 1. Get Moisture Level Alerts (moisture percentage is below 11.5% or above 14.5% for active stock bags)
            $moistureQuery = QualityInspection::whereHas('stockBag', function ($q) use ($warehouseIds, $validated) {
                $q->where('status', 'in_stock');
                if (is_array($warehouseIds)) {
                    $q->whereIn('warehouse_id', $warehouseIds);
                }
                if (!empty($validated['warehouse_id'])) {
                    $q->where('warehouse_id', $validated['warehouse_id']);
                }
            })->with([
                'stockBag:id,bag_code,warehouse_id',
                'stockBag.warehouse:id,name,code',
                'itemVariety:id,name',
            ])->where(function ($q) {
                $q->where('moisture_percentage', '<', 11.50)
                  ->orWhere('moisture_percentage', '>', 14.50);
            });

            if (!empty($validated['item_variety_id'])) {
                $moistureQuery->where('item_variety_id', $validated['item_variety_id']);
            }

            $moistureAlerts = $moistureQuery->orderBy('id', 'desc')->get();

            // 2. Get Weight Loss Alerts (where weight_change_type is weight_loss and loss >= 0.5 kg)
            $weightLossQuery = QualityInspection::whereHas('stockBag', function ($q) use ($warehouseIds, $validated) {
                $q->where('status', 'in_stock');
                if (is_array($warehouseIds)) {
                    $q->whereIn('warehouse_id', $warehouseIds);
                }
                if (!empty($validated['warehouse_id'])) {
                    $q->where('warehouse_id', $validated['warehouse_id']);
                }
            })->with([
                'stockBag:id,bag_code,warehouse_id',
                'stockBag.warehouse:id,name,code',
                'itemVariety:id,name',
            ])->where('weight_change_type', 'weight_loss')
              ->where('weight_difference', '<=', -0.50);

            if (!empty($validated['item_variety_id'])) {
                $weightLossQuery->where('item_variety_id', $validated['item_variety_id']);
            }

            $weightLossAlerts = $weightLossQuery->orderBy('id', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Stock quality and weight drop alerts retrieved successfully',
                'data' => [
                    'moisture_alerts' => $moistureAlerts,
                    'weight_loss_alerts' => $weightLossAlerts,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock alerts',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
