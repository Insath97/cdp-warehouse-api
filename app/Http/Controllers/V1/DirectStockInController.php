<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DirectStockInStoreRequest;
use App\Http\Requests\DirectStockInUpdateRequest;
use App\Models\StockInBatch;
use App\Models\StockInBatchItem;
use App\Models\StockBag;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use App\Models\Receipt;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DirectStockInController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockInBatch Index', ['only' => ['index', 'show']]),
            new Middleware('permission:StockInBatch Create', ['only' => ['store']]),
            new Middleware('permission:StockInBatch Update', ['only' => ['update']]),
        ];
    }

    /**
     * Display a listing of direct stock in batches.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockInBatch::where('type', 'direct')->with([
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'creator:id,name,username',
            ]);

            // Apply user warehouse scope
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Search Filter
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Warehouse Filter
            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Status Filter
            if ($request->has('status') && $request->status != '') {
                $query->byStatus($request->status);
            }

            // Date Range
            if ($request->has('from_date') && $request->from_date != '') {
                $query->whereDate('received_date', '>=', $request->from_date);
            }
            if ($request->has('to_date') && $request->to_date != '') {
                $query->whereDate('received_date', '<=', $request->to_date);
            }

            $batches = $query->orderBy('id', 'desc')->paginate($perPage);

            $this->logActivity('INDEX', 'DirectStockIn', 'Retrieved direct stock in batches listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Direct stock in batches retrieved successfully',
                'data' => $batches,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve direct stock in batches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created direct stock in batch along with batch items and bags.
     */
    public function store(DirectStockInStoreRequest $request)
    {
        try {
            $validated = $request->validated();

            $batch = DB::transaction(function () use ($validated) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                // 1. Optional Vehicle & Vehicle Log Creation
                $vehicleId = null;
                $vehicleLogId = null;

                if (!empty($validated['vehicle_number'])) {
                    $vehicle = Vehicle::firstOrCreate(
                        ['vehicle_number' => strtoupper(trim($validated['vehicle_number']))],
                        [
                            'vehicle_type' => $validated['vehicle_type'],
                            'is_active' => true,
                        ]
                    );

                    $vehicleId = $vehicle->id;

                    $logNumber = 'VLOG-' . date('Ymd') . '-' . strtoupper(Str::random(5));
                    $vehicleLog = VehicleLog::create([
                        'log_number' => $logNumber,
                        'vehicle_id' => $vehicleId,
                        'log_type' => 'stock_in',
                        'direction' => 'in',
                        'entry_time' => now(),
                        'driver_name' => $validated['driver_name'],
                        'driver_phone' => $validated['driver_phone'] ?? null,
                        'driver_nic' => $validated['driver_nic'] ?? null,
                        'purpose' => $validated['purpose'] ?? 'Direct Stock In',
                        'notes' => $validated['vehicle_notes'] ?? null,
                        'logged_by' => $userId,
                    ]);

                    $vehicleLogId = $vehicleLog->id;
                }

                // 2. Pre-calculate aggregates
                $totalBagsCount = 0;
                $netWeightSum = 0;
                $totalAmountSum = 0;

                foreach ($validated['items'] as $item) {
                    $qtyBags = count($item['bags']);
                    $itemWeight = array_sum(array_column($item['bags'], 'bag_weight'));
                    $itemAmount = $itemWeight * (float)$item['unit_price'];

                    $totalBagsCount += $qtyBags;
                    $netWeightSum += $itemWeight;
                    $totalAmountSum += $itemAmount;
                }

                // 3. Create StockInBatch
                $stockInBatch = StockInBatch::create([
                    'type' => 'direct',
                    'supplier_id' => null,
                    'warehouse_id' => $validated['warehouse_id'],
                    'vehicle_id' => $vehicleId,
                    'vehicle_log_id' => $vehicleLogId,
                    'received_date' => $validated['received_date'],
                    'gross_weight' => $netWeightSum, // for direct, gross matches net weight initially
                    'tare_weight' => 0,
                    'net_weight' => $netWeightSum,
                    'total_bags' => $totalBagsCount,
                    'total_amount' => $totalAmountSum,
                    'status' => $validated['status'] ?? 'received',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                // 4. Create items and bags
                $stockInBatch->load('warehouse:id,branch_id');
                $branchId = $stockInBatch->warehouse->branch_id ?? ($authUser->branch_id ?? 1);

                foreach ($validated['items'] as $itemData) {
                    $itemBags = $itemData['bags'];
                    $qtyBags = count($itemBags);
                    $itemWeight = array_sum(array_column($itemBags, 'bag_weight'));
                    $itemAmount = $itemWeight * (float)$itemData['unit_price'];

                    // Create StockInBatchItem
                    $batchItem = $stockInBatch->items()->create([
                        'item_type_id' => $itemData['item_type_id'],
                        'item_variety_id' => $itemData['item_variety_id'],
                        'quantity_bags' => $qtyBags,
                        'unit_weight' => $qtyBags > 0 ? ($itemWeight / $qtyBags) : 0,
                        'total_weight' => $itemWeight,
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemAmount,
                        'remaining_quantity_bags' => $qtyBags,
                        'remaining_weight' => $itemWeight,
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    // Create individual StockBags
                    foreach ($itemBags as $bagData) {
                        StockBag::create([
                            'stock_in_batch_id' => $stockInBatch->id,
                            'stock_in_batch_item_id' => $batchItem->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $stockInBatch->warehouse_id,
                            'supplier_id' => null,
                            'item_type_id' => $itemData['item_type_id'],
                            'item_variety_id' => $itemData['item_variety_id'],
                            'bag_weight' => $bagData['bag_weight'],
                            'unit_price' => $itemData['unit_price'],
                            'selling_price' => $itemData['unit_price'], // default selling price matches unit price
                            'status' => 'in_stock',
                            'location_id' => $bagData['location_id'] ?? null,
                            'notes' => $bagData['notes'] ?? null,
                            'created_by' => $userId,
                        ]);
                    }
                }

                // 5. Create Receipt
                $nextReceiptId = (Receipt::max('id') ?? 0) + 1;
                $receiptNumber = 'RCP-' . date('Ymd') . '-' . str_pad($nextReceiptId, 5, '0', STR_PAD_LEFT);
                Receipt::create([
                    'receipt_number' => $receiptNumber,
                    'stock_in_batch_id' => $stockInBatch->id,
                    'supplier_id' => null,
                    'warehouse_id' => $stockInBatch->warehouse_id,
                    'branch_id' => $branchId,
                    'receipt_date' => $stockInBatch->received_date,
                    'total_bags' => $stockInBatch->total_bags,
                    'total_weight' => $stockInBatch->net_weight,
                    'total_amount' => $stockInBatch->total_amount,
                    'status' => 'pending',
                    'created_by' => $userId,
                ]);

                return $stockInBatch;
            });

            $batch->load([
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'vehicleLog',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'items.bags',
                'receipt',
            ]);

            $this->logActivity('CREATE', 'DirectStockIn', "Created direct stock in batch: {$batch->batch_number} with {$batch->total_bags} bags.", $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Direct stock in batch created successfully',
                'data' => $batch,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create direct stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified direct stock in batch.
     */
    public function show(string $id)
    {
        try {
            $batch = StockInBatch::where('type', 'direct')->with([
                'warehouse:id,code,name,city',
                'vehicle:id,vehicle_number',
                'vehicleLog:id,log_number,entry_time,exit_time,driver_name,driver_phone,driver_nic',
                'items.itemType:id,name,code,description',
                'items.itemVariety:id,name,code,slug,description',
                'items.bags',
                'creator:id,name,username',
                'updater:id,name,username',
                'receipt',
            ])->find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Direct stock in batch not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'DirectStockIn', "Retrieved direct stock in batch details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Direct stock in batch retrieved successfully',
                'data' => $batch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve direct stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified direct stock in batch, including items and bags.
     */
    public function update(DirectStockInUpdateRequest $request, string $id)
    {
        try {
            $batch = StockInBatch::where('type', 'direct')->find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Direct stock in batch not found',
                ], 404);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($batch, $validated) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                // 1. Update Vehicle Log details if present
                if (!empty($validated['vehicle_number'])) {
                    $vehicle = Vehicle::firstOrCreate(
                        ['vehicle_number' => strtoupper(trim($validated['vehicle_number']))],
                        [
                            'vehicle_type' => $validated['vehicle_type'] ?? 'other',
                            'is_active' => true,
                        ]
                    );

                    $batch->vehicle_id = $vehicle->id;

                    if ($batch->vehicle_log_id) {
                        $vehicleLog = VehicleLog::find($batch->vehicle_log_id);
                        if ($vehicleLog) {
                            $vehicleLog->update([
                                'vehicle_id' => $vehicle->id,
                                'driver_name' => $validated['driver_name'] ?? $vehicleLog->driver_name,
                                'driver_phone' => $validated['driver_phone'] ?? $vehicleLog->driver_phone,
                                'driver_nic' => $validated['driver_nic'] ?? $vehicleLog->driver_nic,
                                'purpose' => $validated['purpose'] ?? $vehicleLog->purpose,
                                'notes' => $validated['vehicle_notes'] ?? $vehicleLog->notes,
                            ]);
                        }
                    } else {
                        $logNumber = 'VLOG-' . date('Ymd') . '-' . strtoupper(Str::random(5));
                        $vehicleLog = VehicleLog::create([
                            'log_number' => $logNumber,
                            'vehicle_id' => $vehicle->id,
                            'log_type' => 'stock_in',
                            'direction' => 'in',
                            'entry_time' => now(),
                            'driver_name' => $validated['driver_name'],
                            'driver_phone' => $validated['driver_phone'] ?? null,
                            'driver_nic' => $validated['driver_nic'] ?? null,
                            'purpose' => $validated['purpose'] ?? 'Direct Stock In',
                            'notes' => $validated['vehicle_notes'] ?? null,
                            'logged_by' => $userId,
                        ]);
                        $batch->vehicle_log_id = $vehicleLog->id;
                    }
                }

                // 2. Sync Items and Bags
                if (isset($validated['items'])) {
                    $submittedItemIds = [];
                    $submittedBagIds = [];
                    $submittedBagCodes = [];

                    foreach ($validated['items'] as $item) {
                        if (!empty($item['id'])) {
                            $submittedItemIds[] = $item['id'];
                        }
                        foreach ($item['bags'] as $bag) {
                            if (!empty($bag['id'])) {
                                $submittedBagIds[] = $bag['id'];
                            }
                            if (!empty($bag['bag_code'])) {
                                $submittedBagCodes[] = $bag['bag_code'];
                            }
                        }
                    }

                    // Delete unmatched bags first
                    $bagsQuery = $batch->bags();
                    if (!empty($submittedBagIds) || !empty($submittedBagCodes)) {
                        $bagsQuery->where(function ($q) use ($submittedBagIds, $submittedBagCodes) {
                            if (!empty($submittedBagIds)) {
                                $q->whereNotIn('id', $submittedBagIds);
                            }
                            if (!empty($submittedBagCodes)) {
                                $q->whereNotIn('bag_code', $submittedBagCodes);
                            }
                        });
                    }
                    $bagsQuery->delete();

                    // Delete unmatched items
                    $batch->items()->whereNotIn('id', $submittedItemIds)->delete();

                    $totalBagsCount = 0;
                    $netWeightSum = 0;
                    $totalAmountSum = 0;

                    $branchId = $batch->warehouse->branch_id ?? ($authUser->branch_id ?? 1);

                    foreach ($validated['items'] as $itemData) {
                        $qtyBags = count($itemData['bags']);
                        $itemWeight = array_sum(array_column($itemData['bags'], 'bag_weight'));
                        $itemAmount = $itemWeight * (float)$itemData['unit_price'];

                        $totalBagsCount += $qtyBags;
                        $netWeightSum += $itemWeight;
                        $totalAmountSum += $itemAmount;

                        // Create or update item
                        if (!empty($itemData['id'])) {
                            $batchItem = StockInBatchItem::find($itemData['id']);
                            $batchItem->update([
                                'item_type_id' => $itemData['item_type_id'],
                                'item_variety_id' => $itemData['item_variety_id'],
                                'quantity_bags' => $qtyBags,
                                'unit_weight' => $qtyBags > 0 ? ($itemWeight / $qtyBags) : 0,
                                'total_weight' => $itemWeight,
                                'unit_price' => $itemData['unit_price'],
                                'total_price' => $itemAmount,
                                'remaining_quantity_bags' => $qtyBags, // Reset or recalculate based on state
                                'remaining_weight' => $itemWeight,
                                'notes' => $itemData['notes'] ?? $batchItem->notes,
                            ]);
                        } else {
                            $batchItem = $batch->items()->create([
                                'item_type_id' => $itemData['item_type_id'],
                                'item_variety_id' => $itemData['item_variety_id'],
                                'quantity_bags' => $qtyBags,
                                'unit_weight' => $qtyBags > 0 ? ($itemWeight / $qtyBags) : 0,
                                'total_weight' => $itemWeight,
                                'unit_price' => $itemData['unit_price'],
                                'total_price' => $itemAmount,
                                'remaining_quantity_bags' => $qtyBags,
                                'remaining_weight' => $itemWeight,
                                'notes' => $itemData['notes'] ?? null,
                            ]);
                        }

                        // Sync nested bags
                        foreach ($itemData['bags'] as $bagData) {
                            $bagMatch = null;
                            if (!empty($bagData['id'])) {
                                $bagMatch = StockBag::find($bagData['id']);
                            } elseif (!empty($bagData['bag_code'])) {
                                $bagMatch = StockBag::where('bag_code', $bagData['bag_code'])->first();
                            }

                            if ($bagMatch) {
                                $bagMatch->update([
                                    'stock_in_batch_item_id' => $batchItem->id,
                                    'item_type_id' => $itemData['item_type_id'],
                                    'item_variety_id' => $itemData['item_variety_id'],
                                    'bag_weight' => $bagData['bag_weight'],
                                    'unit_price' => $itemData['unit_price'],
                                    'selling_price' => $itemData['unit_price'],
                                    'total_price' => $bagData['bag_weight'] * $itemData['unit_price'],
                                    'total_sales_amount' => $bagData['bag_weight'] * $itemData['unit_price'],
                                    'location_id' => $bagData['location_id'] ?? $bagMatch->location_id,
                                    'notes' => $bagData['notes'] ?? $bagMatch->notes,
                                    'updated_by' => $userId,
                                ]);
                            } else {
                                StockBag::create([
                                    'stock_in_batch_id' => $batch->id,
                                    'stock_in_batch_item_id' => $batchItem->id,
                                    'branch_id' => $branchId,
                                    'warehouse_id' => $batch->warehouse_id,
                                    'supplier_id' => null,
                                    'item_type_id' => $itemData['item_type_id'],
                                    'item_variety_id' => $itemData['item_variety_id'],
                                    'bag_weight' => $bagData['bag_weight'],
                                    'unit_price' => $itemData['unit_price'],
                                    'selling_price' => $itemData['unit_price'],
                                    'status' => 'in_stock',
                                    'location_id' => $bagData['location_id'] ?? null,
                                    'notes' => $bagData['notes'] ?? null,
                                    'created_by' => $userId,
                                ]);
                            }
                        }
                    }

                    $batch->total_bags = $totalBagsCount;
                    $batch->net_weight = $netWeightSum;
                    $batch->gross_weight = $netWeightSum;
                    $batch->total_amount = $totalAmountSum;
                }

                // 3. Update Batch Metadata
                if (isset($validated['warehouse_id'])) {
                    $batch->warehouse_id = $validated['warehouse_id'];
                }
                if (isset($validated['received_date'])) {
                    $batch->received_date = $validated['received_date'];
                }
                if (isset($validated['notes'])) {
                    $batch->notes = $validated['notes'];
                }
                if (isset($validated['status'])) {
                    $batch->status = $validated['status'];
                }
                $batch->updated_by = $userId;
                $batch->save();

                // 4. Update Receipt details
                if ($batch->receipt) {
                    $batch->receipt->update([
                        'warehouse_id' => $batch->warehouse_id,
                        'receipt_date' => $batch->received_date,
                        'total_bags' => $batch->total_bags,
                        'total_weight' => $batch->net_weight,
                        'total_amount' => $batch->total_amount,
                    ]);
                }
            });

            $batch->load([
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'vehicleLog',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'items.bags',
                'receipt',
            ]);

            $this->logActivity('UPDATE', 'DirectStockIn', "Updated direct stock in batch: {$batch->batch_number} with {$batch->total_bags} bags.", $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Direct stock in batch updated successfully',
                'data' => $batch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update direct stock in batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
