<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockInBatchRequest;
use App\Http\Requests\UpdateStockInBatchRequest;
use App\Models\StockInBatch;
use App\Models\StockInBatchItem;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockInBatchController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockInBatch Index', ['only' => ['index', 'show']]),
            new Middleware('permission:StockInBatch List', ['only' => ['getActiveList']]),
            new Middleware('permission:StockInBatch Create', ['only' => ['store']]),
            new Middleware('permission:StockInBatch Update', ['only' => ['update']]),
            new Middleware('permission:StockInBatch Delete', ['only' => ['destroy']]),
            new Middleware('permission:StockInBatch Update Status', ['only' => ['updateStatus']]),
        ];
    }

    /**
     * Display a listing of stock in batches.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockInBatch::with([
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'creator:id,name',
            ]);

            // Apply logged-in user branch/warehouse scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Apply Search Scope
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filter by supplier
            if ($request->has('supplier_id') && $request->supplier_id != '') {
                $query->where('supplier_id', $request->supplier_id);
            }

            // Filter by warehouse
            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Filter by status
            if ($request->has('status') && $request->status != '') {
                $query->byStatus($request->status);
            }

            // Filter by date range
            if ($request->has('from_date') && $request->from_date != '') {
                $query->whereDate('received_date', '>=', $request->from_date);
            }

            if ($request->has('to_date') && $request->to_date != '') {
                $query->whereDate('received_date', '<=', $request->to_date);
            }

            $batches = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock in batches retrieved successfully',
                'data' => $batches,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock in batches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created stock in batch in storage.
     */
    public function store(CreateStockInBatchRequest $request)
    {
        try {
            $validated = $request->validated();

            $batch = DB::transaction(function () use ($validated) {
                $itemsData = $validated['items'];
                unset($validated['items']);

                $authUser = auth('api')->user();
                $validated['created_by'] = $authUser->id ?? 1;
                $validated['status'] = $validated['status'] ?? 'received';

                if (empty($validated['warehouse_id']) && $authUser && $authUser->warehouse_id) {
                    $validated['warehouse_id'] = $authUser->warehouse_id;
                }

                // Calculate totals from items if not provided
                $totalBags = 0;
                $calculatedNetWeight = 0;
                $totalAmount = 0;

                $processedItems = [];

                foreach ($itemsData as $item) {
                    $qtyBags = (int) ($item['quantity_bags'] ?? 0);
                    $unitWeight = (float) ($item['unit_weight'] ?? 0);
                    $totalWeight = isset($item['total_weight']) && $item['total_weight'] > 0
                        ? (float) $item['total_weight']
                        : $qtyBags * $unitWeight;

                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    $totalPrice = isset($item['total_price']) && $item['total_price'] > 0
                        ? (float) $item['total_price']
                        : $totalWeight * $unitPrice;

                    $totalBags += $qtyBags;
                    $calculatedNetWeight += $totalWeight;
                    $totalAmount += $totalPrice;

                    $processedItems[] = [
                        'item_type_id' => $item['item_type_id'],
                        'item_variety_id' => $item['item_variety_id'],
                        'quantity_bags' => $qtyBags,
                        'unit_weight' => $unitWeight,
                        'total_weight' => $totalWeight,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'remaining_quantity_bags' => $qtyBags,
                        'remaining_weight' => $totalWeight,
                        'notes' => $item['notes'] ?? null,
                    ];
                }

                $validated['total_bags'] = $totalBags;
                $validated['total_amount'] = $totalAmount;

                if (!isset($validated['net_weight']) || $validated['net_weight'] <= 0) {
                    $validated['net_weight'] = $calculatedNetWeight;
                }

                // Create StockInBatch header
                $stockInBatch = StockInBatch::create($validated);

                // Create line items
                foreach ($processedItems as $pItem) {
                    $stockInBatch->items()->create($pItem);
                }

                return $stockInBatch;
            });

            // Log activity
            $this->logActivity(
                'CREATE',
                'StockInBatch',
                "Created stock in batch: {$batch->batch_number} with {$batch->total_bags} bags.",
                $request->validated()
            );

            $batch->load([
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'vehicleLog:id,log_number',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'creator:id,name',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock in batch created successfully',
                'data' => $batch,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified stock in batch.
     */
    public function show(string $id)
    {
        try {
            $batch = StockInBatch::with([
                'supplier:id,code,name,phone_primary',
                'warehouse:id,code,name,city',
                'vehicle:id,vehicle_number,driver_name',
                'vehicleLog:id,log_number,entry_time,exit_time',
                'items.itemType:id,name,code,description',
                'items.itemVariety:id,name,code,slug,description',
                'creator:id,name,email',
                'updater:id,name,email',
            ])->find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Stock in batch retrieved successfully',
                'data' => $batch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified stock in batch in storage.
     */
    public function update(UpdateStockInBatchRequest $request, string $id)
    {
        try {
            $batch = StockInBatch::find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($batch, $validated) {
                $hasItemsUpdate = isset($validated['items']);
                $itemsData = $validated['items'] ?? [];
                unset($validated['items']);

                $validated['updated_by'] = auth()->id() ?? 1;

                if ($hasItemsUpdate) {
                    // Delete old items and re-create updated line items
                    $batch->items()->delete();

                    $totalBags = 0;
                    $calculatedNetWeight = 0;
                    $totalAmount = 0;

                    foreach ($itemsData as $item) {
                        $qtyBags = (int) ($item['quantity_bags'] ?? 0);
                        $unitWeight = (float) ($item['unit_weight'] ?? 0);
                        $totalWeight = isset($item['total_weight']) && $item['total_weight'] > 0
                            ? (float) $item['total_weight']
                            : $qtyBags * $unitWeight;

                        $unitPrice = (float) ($item['unit_price'] ?? 0);
                        $totalPrice = isset($item['total_price']) && $item['total_price'] > 0
                            ? (float) $item['total_price']
                            : $totalWeight * $unitPrice;

                        $totalBags += $qtyBags;
                        $calculatedNetWeight += $totalWeight;
                        $totalAmount += $totalPrice;

                        $batch->items()->create([
                            'item_type_id' => $item['item_type_id'],
                            'item_variety_id' => $item['item_variety_id'],
                            'quantity_bags' => $qtyBags,
                            'unit_weight' => $unitWeight,
                            'total_weight' => $totalWeight,
                            'unit_price' => $unitPrice,
                            'total_price' => $totalPrice,
                            'remaining_quantity_bags' => $qtyBags,
                            'remaining_weight' => $totalWeight,
                            'notes' => $item['notes'] ?? null,
                        ]);
                    }

                    $validated['total_bags'] = $totalBags;
                    $validated['total_amount'] = $totalAmount;
                    if (!isset($validated['net_weight']) || $validated['net_weight'] <= 0) {
                        $validated['net_weight'] = $calculatedNetWeight;
                    }
                }

                $batch->update($validated);
            });

            // Log activity
            $this->logActivity(
                'UPDATE',
                'StockInBatch',
                "Updated stock in batch: {$batch->batch_number}",
                $request->validated()
            );

            $batch->load([
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'updater:id,name',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock in batch updated successfully',
                'data' => $batch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified stock in batch from storage.
     */
    public function destroy(string $id)
    {
        try {
            $batch = StockInBatch::find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            $batchNumber = $batch->batch_number;
            $batch->delete(); // Cascade deletes items

            // Log activity
            $this->logActivity('DELETE', 'StockInBatch', "Deleted stock in batch: {$batchNumber}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock in batch deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the status of the stock in batch.
     */
    public function updateStatus(Request $request, string $id)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:draft,pending,received,completed,cancelled',
            ]);

            $batch = StockInBatch::find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            $oldStatus = $batch->status;
            $batch->status = $request->status;
            $batch->updated_by = auth()->id() ?? 1;
            $batch->save();

            $this->logActivity(
                'UPDATE_STATUS',
                'StockInBatch',
                "Changed batch {$batch->batch_number} status from '{$oldStatus}' to '{$batch->status}'"
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Stock in batch status updated successfully',
                'data' => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'status' => $batch->status,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock in batch status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get lightweight active list of stock in batches for dropdowns.
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = StockInBatch::whereNotIn('status', ['cancelled']);

            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            if ($request->has('supplier_id') && $request->supplier_id != '') {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            $batches = $query->orderBy('id', 'desc')
                ->get(['id', 'batch_number', 'grn_number', 'supplier_id', 'warehouse_id', 'total_bags', 'received_date', 'status']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active stock in batches retrieved successfully',
                'data' => $batches,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active stock in batches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
