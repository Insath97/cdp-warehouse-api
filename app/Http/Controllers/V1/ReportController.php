<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetReportRequest;
use App\Models\StockInBatch;
use App\Models\StockBag;
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
                    'vehicle:id,vehicle_number,driver_name,driver_phone',
                    'vehicleLog:id,log_number,entry_time,exit_time',
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
}
