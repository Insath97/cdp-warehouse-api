<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockInStoreRequest;
use App\Http\Requests\StockInUpdateRequest;
use App\Models\Receipt;
use App\Models\StockInBatch;
use App\Models\StockInBatchItem;
use App\Models\StockBag;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockInController extends Controller implements HasMiddleware
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
                'items.bags',
                'bags',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
            ]);

            // Apply logged-in user branch/warehouse scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Filter by type (direct/supplier)
            if ($request->has('type') && $request->type != '') {
                $query->byType($request->type);
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

            $this->logActivity('INDEX', 'StockIn', 'Retrieved consolidated stock in listing');

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
     * Store a newly created stock in batch.
     */
    public function store(StockInStoreRequest $request)
    {
        try {
            $validated = $request->validated();
            $type = $validated['type'];

            $batch = DB::transaction(function () use ($validated, $type) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                if ($type === 'direct') {
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
                        'gross_weight' => $netWeightSum,
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
                                'selling_price' => $itemData['unit_price'],
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
                } else {
                    // Supplier-based flow
                    $itemsData = $validated['items'];
                    unset($validated['items']);

                    $validated['created_by'] = $userId;
                    $validated['status'] = $validated['status'] ?? 'received';

                    if (empty($validated['warehouse_id']) && $authUser && $authUser->warehouse_id) {
                        $validated['warehouse_id'] = $authUser->warehouse_id;
                    }

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

                    $stockInBatch = StockInBatch::create($validated);

                    foreach ($processedItems as $pItem) {
                        $stockInBatch->items()->create($pItem);
                    }

                    $nextReceiptId = (Receipt::max('id') ?? 0) + 1;
                    $receiptNumber = 'RCP-' . date('Ymd') . '-' . str_pad($nextReceiptId, 5, '0', STR_PAD_LEFT);

                    $stockInBatch->load('warehouse:id,branch_id');
                    $branchId = $stockInBatch->warehouse->branch_id ?? ($authUser->branch_id ?? null);

                    Receipt::create([
                        'receipt_number' => $receiptNumber,
                        'stock_in_batch_id' => $stockInBatch->id,
                        'supplier_id' => $stockInBatch->supplier_id,
                        'warehouse_id' => $stockInBatch->warehouse_id,
                        'branch_id' => $branchId,
                        'receipt_date' => $stockInBatch->received_date ?? now(),
                        'total_bags' => $stockInBatch->total_bags,
                        'total_weight' => $stockInBatch->net_weight,
                        'total_amount' => $stockInBatch->total_amount,
                        'status' => 'pending',
                        'created_by' => $validated['created_by'],
                    ]);

                    return $stockInBatch;
                }
            });

            // Load appropriate relations depending on type
            if ($batch->type === 'direct') {
                $batch->load([
                    'warehouse:id,code,name',
                    'vehicle:id,vehicle_number',
                    'vehicleLog',
                    'items.itemType:id,name,code',
                    'items.itemVariety:id,name,code',
                    'items.bags',
                    'receipt',
                ]);
            } else {
                $batch->load([
                    'supplier:id,code,name',
                    'warehouse:id,code,name',
                    'vehicle:id,vehicle_number',
                    'vehicleLog:id,log_number',
                    'items.itemType:id,name,code',
                    'items.itemVariety:id,name,code',
                    'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
                    'receipt',
                ]);
            }

            $this->logActivity('CREATE', 'StockIn', "Created stock in batch: {$batch->batch_number} ({$batch->type}) with {$batch->total_bags} bags.", $request->validated());

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
            $batch = StockInBatch::find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock in batch not found',
                ], 404);
            }

            // User accessibility check
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && !in_array($batch->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to this warehouse batch.',
                    ], 403);
                }
            }

            $relations = [
                'supplier:id,code,name,phone_primary',
                'warehouse:id,code,name,city',
                'vehicle:id,vehicle_number',
                'vehicleLog:id,log_number,entry_time,exit_time,driver_name,driver_phone,driver_nic',
                'items.itemType:id,name,code,description',
                'items.itemVariety:id,name,code,slug,description',
                'items.bags',
                'bags',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
                'updater:id,name,username,email',
                'receipt',
            ];

            $batch->load($relations);

            $this->logActivity('SHOW', 'StockIn', "Retrieved stock in batch details for ID: {$id}");

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
    public function update(StockInUpdateRequest $request, string $id)
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
            $type = $batch->type; // Maintain consistent type or determine from validated/DB

            DB::transaction(function () use ($batch, $validated, $type) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                $validated['updated_by'] = $userId;

                if ($type === 'direct') {
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
                            if (isset($item['bags'])) {
                                foreach ($item['bags'] as $bag) {
                                    if (!empty($bag['id'])) {
                                        $submittedBagIds[] = $bag['id'];
                                    }
                                    if (!empty($bag['bag_code'])) {
                                        $submittedBagCodes[] = $bag['bag_code'];
                                    }
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
                                    'remaining_quantity_bags' => $qtyBags,
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

                        $batch->gross_weight = $netWeightSum;
                        $batch->net_weight = $netWeightSum;
                        $batch->total_bags = $totalBagsCount;
                        $batch->total_amount = $totalAmountSum;
                    }

                    unset($validated['items']);
                    $batch->update($validated);

                } else {
                    // Supplier-based Flow
                    $hasItemsUpdate = isset($validated['items']);
                    $itemsData = $validated['items'] ?? [];
                    unset($validated['items']);

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
                }

                // Synchronize Receipt
                $receipt = Receipt::where('stock_in_batch_id', $batch->id)->first();
                if ($receipt) {
                    $batch->load('warehouse:id,branch_id');
                    $receipt->update([
                        'supplier_id' => $batch->supplier_id,
                        'warehouse_id' => $batch->warehouse_id,
                        'branch_id' => $batch->warehouse->branch_id ?? $receipt->branch_id,
                        'receipt_date' => $batch->received_date ?? $receipt->receipt_date,
                        'total_bags' => $batch->total_bags,
                        'total_weight' => $batch->net_weight,
                        'total_amount' => $batch->total_amount,
                    ]);
                }
            });

            // Reload matching relationships
            $relations = [
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'vehicle:id,vehicle_number',
                'items.itemType:id,name,code',
                'items.itemVariety:id,name,code',
                'updater:id,name',
                'receipt',
            ];

            if ($batch->type === 'direct') {
                $relations[] = 'items.bags';
            }

            $batch->load($relations);

            $this->logActivity(
                'UPDATE',
                'StockIn',
                "Updated stock in batch: {$batch->batch_number}",
                $request->validated()
            );

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
            $batch->delete(); // Cascade deletes items and bags if configured in models/migration

            $this->logActivity('DELETE', 'StockIn', "Deleted stock in batch: {$batchNumber}");

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
                'StockIn',
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

            if ($request->has('type') && $request->type != '') {
                $query->byType($request->type);
            }

            $batches = $query->with(['items.bags', 'bags'])
                ->orderBy('id', 'desc')
                ->get(['id', 'batch_number', 'supplier_id', 'warehouse_id', 'total_bags', 'received_date', 'status', 'type']);

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
