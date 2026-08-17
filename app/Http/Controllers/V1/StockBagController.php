<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockBagRequest;
use App\Http\Requests\UpdateStockBagRequest;
use App\Models\BarcodeToken;
use App\Models\ItemVariety;
use App\Models\StockBag;
use App\Models\StockInBatch;
use App\Models\StockInBatchItem;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockBagController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockBag Index', ['only' => ['index', 'show', 'getBatchDetails']]),
            new Middleware('permission:StockBag List', ['only' => ['getActiveList']]),
            new Middleware('permission:StockBag Create', ['only' => ['store']]),
            new Middleware('permission:StockBag Update', ['only' => ['update']]),
            new Middleware('permission:StockBag Delete', ['only' => ['destroy']]),
            new Middleware('permission:StockBag Update Status', ['only' => ['updateStatus']]),
        ];
    }

    /**
     * Helper endpoint to fetch batch context when selecting a batch for bag entry.
     * Returns batch header, warehouse, branch, supplier, available varieties, and next bag sequence.
     */
    public function getBatchDetails(string $batchId)
    {
        try {
            $batch = StockInBatch::with([
                'supplier:id,code,name',
                'warehouse.branch:id,code,name',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
            ])->find($batchId);

            if (! $batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            // Warehouse & Branch Security Authorization Check
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && ! in_array($batch->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access. You do not have permission to manage bags for this warehouse.',
                    ], 403);
                }
            }

            // Next sequential bag number for this batch
            $nextBagNumber = (StockBag::where('stock_in_batch_id', $batchId)->max('bag_number') ?? 0) + 1;

            // Varieties present in this batch
            $batchVarieties = $batch->items->map(function ($item) use ($batchId) {
                $registeredBagsCount = StockBag::where('stock_in_batch_id', $batchId)
                    ->where('item_variety_id', $item->item_variety_id)
                    ->count();

                return [
                    'stock_in_batch_item_id' => $item->id,
                    'item_type_id' => $item->item_type_id,
                    'item_type_name' => $item->itemType->name ?? null,
                    'item_variety_id' => $item->item_variety_id,
                    'item_variety_name' => $item->itemVariety->name ?? null,
                    'item_variety_code' => $item->itemVariety->code ?? null,
                    'expected_quantity_bags' => $item->quantity_bags,
                    'unit_weight' => $item->unit_weight,
                    'unit_price' => $item->unit_price,
                    'registered_bags_count' => $registeredBagsCount,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Batch details fetched successfully for bag entry',
                'data' => [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'received_date' => $batch->received_date,
                    'supplier' => $batch->supplier,
                    'warehouse' => $batch->warehouse,
                    'branch' => $batch->warehouse->branch ?? null,
                    'next_bag_number' => $nextBagNumber,
                    'available_item_varieties' => $batchVarieties,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch batch details',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display a listing of stock bags.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockBag::with([
                'stockInBatch:id,batch_number',
                'warehouse:id,code,name',
                'branch:id,code,name',
                'supplier:id,code,name',
                'itemType:id,name,code',
                'itemVariety:id,name,code',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
            ]);

            // User Branch/Warehouse Scope Authorization Filter
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

            // Filters
            if ($request->has('batch_id') && $request->batch_id != '') {
                $query->byBatch($request->batch_id);
            }

            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->byWarehouse($request->warehouse_id);
            }

            if ($request->has('status') && $request->status != '') {
                $query->byStatus($request->status);
            }

            if ($request->has('item_type_id') && $request->item_type_id != '') {
                $query->where('item_type_id', $request->item_type_id);
            }

            if ($request->has('item_variety_id') && $request->item_variety_id != '') {
                $query->where('item_variety_id', $request->item_variety_id);
            }

            $bags = $query->orderBy('id', 'desc')->paginate($perPage);

            $this->logActivity('INDEX', 'StockBag', 'Retrieved stock bags listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Stock bags retrieved successfully',
                'data' => $bags,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock bags',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store newly created stock bag(s) in storage.
     * Supports single bag entry or bulk array entry.
     */
    public function store(CreateStockBagRequest $request)
    {
        try {
            $validated = $request->validated();

            $batch = StockInBatch::with('warehouse')->find($validated['stock_in_batch_id']);

            if (! $batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            // Security authorization check on warehouse scope
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && ! in_array($batch->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access. You cannot store bags for this warehouse.',
                    ], 403);
                }
            }

            $warehouseId = $batch->warehouse_id;
            $branchId = $batch->warehouse->branch_id ?? ($authUser->branch_id ?? 1);
            $supplierId = $batch->supplier_id;

            $createdBags = DB::transaction(function () use ($validated, $batch, $warehouseId, $branchId, $supplierId, $authUser) {
                $bagsList = [];

                if (isset($validated['bags']) && is_array($validated['bags'])) {
                    // Bulk bag insertion
                    foreach ($validated['bags'] as $bagInput) {
                        $bagsList[] = $this->createSingleBagRecord(
                            $bagInput,
                            $batch,
                            $warehouseId,
                            $branchId,
                            $supplierId,
                            $authUser
                        );
                    }
                } else {
                    // Single bag insertion
                    $bagsList[] = $this->createSingleBagRecord(
                        $validated,
                        $batch,
                        $warehouseId,
                        $branchId,
                        $supplierId,
                        $authUser
                    );
                }

                // Update actual bag count on StockInBatch
                $actualCount = StockBag::where('stock_in_batch_id', $batch->id)->count();
                $batch->update(['total_bags' => max($batch->total_bags, $actualCount)]);

                return $bagsList;
            });

            // Log activity
            $this->logActivity(
                'CREATE',
                'StockBag',
                'Registered '.count($createdBags)." bag(s) for batch: {$batch->batch_number}",
                $request->validated()
            );

            return response()->json([
                'status' => 'success',
                'message' => count($createdBags).' stock bag(s) created successfully',
                'data' => count($createdBags) === 1 ? $createdBags[0] : $createdBags,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock bag',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Helper to insert a single bag record.
     */
    private function createSingleBagRecord(array $data, StockInBatch $batch, int $warehouseId, int $branchId, ?int $supplierId, $authUser): StockBag
    {
        $itemVarietyId = $data['item_variety_id'];
        $batchItemId = $data['stock_in_batch_item_id'] ?? null;

        // Auto-find item_type_id and batch_item_id if missing
        if (! $batchItemId || ! isset($data['item_type_id'])) {
            $batchItem = StockInBatchItem::where('stock_in_batch_id', $batch->id)
                ->where('item_variety_id', $itemVarietyId)
                ->first();

            if ($batchItem) {
                $batchItemId = $batchItemId ?? $batchItem->id;
                $itemTypeId = $batchItem->item_type_id;
            } else {
                $variety = ItemVariety::find($itemVarietyId);
                $itemTypeId = $variety->item_type_id ?? $data['item_type_id'] ?? 1;
            }
        } else {
            $itemTypeId = $data['item_type_id'];
        }

        $nextNum = (StockBag::where('stock_in_batch_id', $batch->id)->max('bag_number') ?? 0) + 1;

        $weight = (float) $data['bag_weight'];
        $uPrice = (float) ($data['unit_price'] ?? 0);
        $sPrice = (float) ($data['selling_price'] ?? 0);

        $totalPrice = $weight * $uPrice;
        $totalSalesAmount = $weight * $sPrice;

        $datePrefix = date('Ymd');
        $randomStr = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $generatedBagCode = 'BAG-'.$datePrefix.'-'.str_pad($nextNum, 4, '0', STR_PAD_LEFT).'-'.$randomStr;

        // Support scanned identifiers or fall back to auto-generated codes
        $bagCode = ! empty($data['bag_code']) ? trim($data['bag_code']) : (! empty($data['barcode_code']) ? trim($data['barcode_code']) : $generatedBagCode);
        $barcodeCode = ! empty($data['barcode_code']) ? trim($data['barcode_code']) : $bagCode;
        $qrCode = ! empty($data['qr_code']) ? trim($data['qr_code']) : ('QR-'.$barcodeCode);

        $bag = StockBag::create([
            'bag_code' => $bagCode,
            'bag_number' => $nextNum,
            'stock_in_batch_id' => $batch->id,
            'stock_in_batch_item_id' => $batchItemId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplierId,
            'item_type_id' => $itemTypeId,
            'item_variety_id' => $itemVarietyId,
            'bag_weight' => $weight,
            'unit_price' => $uPrice,
            'selling_price' => $sPrice,
            'total_price' => $totalPrice,
            'total_sales_amount' => $totalSalesAmount,
            'status' => $data['status'] ?? 'in_stock',
            'barcode_code' => $barcodeCode,
            'qr_code' => $qrCode,
            'location_id' => $data['location_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $authUser->id ?? 1,
        ]);

        // Automatically update matching system BarcodeToken status to 'used'
        if (! empty($data['barcode_code'])) {
            $tokenCode = trim($data['barcode_code']);
            BarcodeToken::where('token_code', $tokenCode)->update([
                'status' => 'used',
                'used_at' => now(),
                'used_by' => $authUser->id ?? 1,
            ]);
        }

        $bag->load([
            'stockInBatch:id,batch_number',
            'itemType:id,name,code',
            'itemVariety:id,name,code',
            'warehouse:id,name,code',
            'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
        ]);

        return $bag;
    }

    /**
     * Display the specified stock bag.
     */
    public function show(string $id)
    {
        try {
            $bag = StockBag::with([
                'stockInBatch:id,batch_number,received_date',
                'stockInBatchItem',
                'branch:id,code,name',
                'warehouse:id,code,name,city',
                'supplier:id,code,name',
                'itemType:id,name,code',
                'itemVariety:id,name,code,slug',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
                'updater:id,name,username,email',
            ])->find($id);

            if (! $bag) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock bag not found',
                ], 404);
            }

            // Security authorization check
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && ! in_array($bag->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to this bag',
                    ], 403);
                }
            }

            $this->logActivity('SHOW', 'StockBag', "Retrieved stock bag details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock bag retrieved successfully',
                'data' => $bag,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock bag',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified stock bag in storage.
     */
    public function update(UpdateStockBagRequest $request, string $id)
    {
        try {
            $bag = StockBag::find($id);

            if (! $bag) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock bag not found',
                ], 404);
            }

            // Security authorization check
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && ! in_array($bag->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to update this bag',
                    ], 403);
                }
            }

            $validated = $request->validated();

            // Recalculate price totals if weight or prices are updated
            if (isset($validated['bag_weight']) || isset($validated['unit_price']) || isset($validated['selling_price'])) {
                $weight = isset($validated['bag_weight']) ? (float) $validated['bag_weight'] : (float) $bag->bag_weight;
                $uPrice = isset($validated['unit_price']) ? (float) $validated['unit_price'] : (float) $bag->unit_price;
                $sPrice = isset($validated['selling_price']) ? (float) $validated['selling_price'] : (float) $bag->selling_price;

                $validated['total_price'] = $weight * $uPrice;
                $validated['total_sales_amount'] = $weight * $sPrice;
            }

            // Resolve item variety, item type, and batch item relations if updated
            if (isset($validated['item_variety_id'])) {
                $itemVarietyId = $validated['item_variety_id'];
                $batchItem = StockInBatchItem::where('stock_in_batch_id', $bag->stock_in_batch_id)
                    ->where('item_variety_id', $itemVarietyId)
                    ->first();

                if ($batchItem) {
                    $validated['stock_in_batch_item_id'] = $batchItem->id;
                    $validated['item_type_id'] = $batchItem->item_type_id;
                } else {
                    $variety = ItemVariety::find($itemVarietyId);
                    $validated['item_type_id'] = $variety->item_type_id ?? 1;
                    $validated['stock_in_batch_item_id'] = null;
                }
            }

            $validated['updated_by'] = $authUser->id ?? 1;

            $oldBarcode = $bag->barcode_code;

            DB::transaction(function () use ($bag, $validated, $oldBarcode, $authUser) {
                $bag->update($validated);

                // Handle BarcodeToken status transition if barcode_code was updated
                if (isset($validated['barcode_code']) && trim($validated['barcode_code']) !== $oldBarcode) {
                    $newBarcode = trim($validated['barcode_code']);

                    // Free old barcode token if it existed
                    if (! empty($oldBarcode)) {
                        BarcodeToken::where('token_code', $oldBarcode)->update([
                            'status' => 'unused',
                            'used_at' => null,
                            'used_by' => null,
                        ]);
                    }

                    // Mark new barcode token as used
                    if (! empty($newBarcode)) {
                        BarcodeToken::where('token_code', $newBarcode)->update([
                            'status' => 'used',
                            'used_at' => now(),
                            'used_by' => $authUser->id ?? 1,
                        ]);
                    }
                }
            });

            $this->logActivity('UPDATE', 'StockBag', "Updated bag: {$bag->bag_code}", $request->validated());

            $bag->load([
                'stockInBatch:id,batch_number',
                'itemType:id,name,code',
                'itemVariety:id,name,code',
                'warehouse:id,name,code',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock bag updated successfully',
                'data' => $bag,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock bag',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update status of the specified stock bag.
     */
    public function updateStatus(Request $request, string $id)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:in_stock,dispatched,damaged,returned',
            ]);

            $bag = StockBag::find($id);

            if (! $bag) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock bag not found',
                ], 404);
            }

            $oldStatus = $bag->status;
            $bag->status = $request->status;
            $bag->updated_by = auth()->id() ?? 1;
            $bag->save();

            $this->logActivity('UPDATE_STATUS', 'StockBag', "Changed bag {$bag->bag_code} status from '{$oldStatus}' to '{$bag->status}'");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock bag status updated successfully',
                'data' => [
                    'id' => $bag->id,
                    'bag_code' => $bag->bag_code,
                    'status' => $bag->status,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock bag status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified stock bag from storage.
     */
    public function destroy(string $id)
    {
        try {
            $bag = StockBag::find($id);

            if (! $bag) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock bag not found',
                ], 404);
            }

            $bagCode = $bag->bag_code;
            $bag->delete();

            $this->logActivity('DELETE', 'StockBag', "Deleted stock bag: {$bagCode}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock bag deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock bag',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get active list of stock bags for dropdowns / selections.
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = StockBag::where('status', 'in_stock');

            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            if ($request->has('batch_id') && $request->batch_id != '') {
                $query->byBatch($request->batch_id);
            }

            if ($request->has('item_variety_id') && $request->item_variety_id != '') {
                $query->where('item_variety_id', $request->item_variety_id);
            }

            $bags = $query->orderBy('bag_number', 'asc')
                ->get(['id', 'bag_code', 'bag_number', 'stock_in_batch_id', 'item_variety_id', 'bag_weight', 'selling_price', 'status', 'location_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active stock bags retrieved successfully',
                'data' => $bags,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active stock bags',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
