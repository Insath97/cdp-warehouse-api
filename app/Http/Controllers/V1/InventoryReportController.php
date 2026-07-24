<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetInventoryReportRequest;
use App\Models\StockBag;
use App\Models\QualityInspection;
use App\Traits\ActivityLogTrait;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:InventoryReport Index', ['only' => ['balance', 'valuation', 'aging', 'alerts']]),
        ];
    }

    /**
     * Real-time stock balance summary grouped by Warehouse, Branch, Item Type, Item Variety, Grade, and Bag Count.
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

            return response()->json([
                'status' => 'success',
                'message' => 'Stock balance summary retrieved successfully',
                'data' => $results,
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
