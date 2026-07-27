<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockDispatchRequest;
use App\Http\Requests\UpdateStockDispatchRequest;
use App\Models\StockDispatch;
use App\Models\DispatchItem;
use App\Models\Invoice;
use App\Models\StockBag;
use App\Models\VehicleLog;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockDispatchController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockDispatch Index', ['only' => ['index', 'show']]),
            new Middleware('permission:StockDispatch Create', ['only' => ['store']]),
            new Middleware('permission:StockDispatch Update', ['only' => ['update']]),
            new Middleware('permission:StockDispatch Delete', ['only' => ['destroy']]),
            new Middleware('permission:StockDispatch Confirm', ['only' => ['confirmGatePass']]),
            new Middleware('permission:StockDispatch GateExit', ['only' => ['recordGateExit']]),
        ];
    }

    /**
     * Display a listing of stock dispatches.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockDispatch::with([
                'buyer:id,code,name',
                'warehouse:id,code,name',
                'branch:id,code,name',
                'vehicle:id,vehicle_number',
                'invoice:id,invoice_number,total_amount,payment_status',
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
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('status') && $request->status != '') {
                $query->byStatus($request->status);
            }

            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->has('buyer_id') && $request->buyer_id != '') {
                $query->where('buyer_id', $request->buyer_id);
            }

            if ($request->has('from_date') && $request->from_date != '') {
                $query->whereDate('dispatch_date', '>=', $request->from_date);
            }

            if ($request->has('to_date') && $request->to_date != '') {
                $query->whereDate('dispatch_date', '<=', $request->to_date);
            }

            $dispatches = $query->orderBy('id', 'desc')->paginate($perPage);

            $this->logActivity('INDEX', 'StockDispatch', 'Retrieved stock dispatches listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Stock dispatches retrieved successfully',
                'data' => $dispatches,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock dispatches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created dispatch (Unified Stock Out).
     */
    public function store(CreateStockDispatchRequest $request)
    {
        try {
            $validated = $request->validated();

            $dispatch = DB::transaction(function () use ($validated) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                // Resolve defaults if not supplied
                $warehouseId = $validated['warehouse_id'] ?? $authUser->warehouse_id ?? null;
                $branchId = $validated['branch_id'] ?? $authUser->branch_id ?? null;

                if (!$warehouseId || !$branchId) {
                    throw new \Exception('Warehouse and Branch association are required to create a dispatch.');
                }

                $itemsData = $validated['items'];
                $invoiceData = $validated['invoice'] ?? [];

                // 1. Resolve each StockBag by stock_bag_id, barcode_code, qr_code, or bag_code
                $processedItems = [];
                $totalWeight = 0;
                $totalSalesAmount = 0;

                foreach ($itemsData as $index => $itemInput) {
                    $bag = null;
                    if (!empty($itemInput['stock_bag_id'])) {
                        $bag = StockBag::find($itemInput['stock_bag_id']);
                    } elseif (!empty($itemInput['barcode_code'])) {
                        $bag = StockBag::where('barcode_code', $itemInput['barcode_code'])->first();
                    } elseif (!empty($itemInput['qr_code'])) {
                        $bag = StockBag::where('qr_code', $itemInput['qr_code'])->first();
                    } elseif (!empty($itemInput['bag_code'])) {
                        $bag = StockBag::where('bag_code', $itemInput['bag_code'])->first();
                    }

                    if (!$bag) {
                        $identifier = $itemInput['stock_bag_id'] ?? $itemInput['barcode_code'] ?? $itemInput['qr_code'] ?? $itemInput['bag_code'] ?? "#{$index}";
                        throw new \Exception("Could not find Stock Bag matching identifier: {$identifier}");
                    }

                    if ($bag->status !== 'in_stock') {
                        throw new \Exception("Stock Bag ID {$bag->id} ({$bag->bag_code}) is not in stock. Current status is: {$bag->status}");
                    }
                    if ($bag->warehouse_id != $warehouseId) {
                        throw new \Exception("Stock Bag ID {$bag->id} ({$bag->bag_code}) does not belong to warehouse ID {$warehouseId}.");
                    }

                    $sellingPrice = isset($itemInput['selling_price'])
                        ? (float) $itemInput['selling_price']
                        : ((float) ($bag->selling_price > 0 ? $bag->selling_price : $bag->unit_price));

                    $bagWeight = (float) $bag->bag_weight;
                    $itemTotal = $bagWeight * $sellingPrice;

                    $totalWeight += $bagWeight;
                    $totalSalesAmount += $itemTotal;

                    $processedItems[] = [
                        'stock_bag_id' => $bag->id,
                        'selling_price' => $sellingPrice,
                        'bag_weight' => $bagWeight,
                        'notes' => $itemInput['notes'] ?? null,
                        'created_by' => $userId,
                    ];
                }

                $totalBags = count($processedItems);

                // 2. Create the Dispatch Master
                $dispatch = StockDispatch::create([
                    'warehouse_id' => $warehouseId,
                    'branch_id' => $branchId,
                    'buyer_id' => $validated['buyer_id'],
                    'dispatch_type' => $validated['dispatch_type'],
                    'dispatch_date' => $validated['dispatch_date'],
                    'delivery_note_reference' => $validated['delivery_note_reference'] ?? null,
                    'vehicle_id' => $validated['vehicle_id'] ?? null,
                    'vehicle_log_id' => $validated['vehicle_log_id'] ?? null,
                    'total_bags' => $totalBags,
                    'total_weight' => $totalWeight,
                    'total_sales_amount' => $totalSalesAmount,
                    'status' => 'draft',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                // 3. Create Dispatch Items & Update Stock Bags
                foreach ($processedItems as &$item) {
                    $item['stock_dispatch_id'] = $dispatch->id;
                    DispatchItem::create($item);

                    // Update StockBag status immediately
                    StockBag::where('id', $item['stock_bag_id'])->update([
                        'status' => 'dispatched',
                        'selling_price' => $item['selling_price'],
                        'total_sales_amount' => $item['bag_weight'] * $item['selling_price'],
                        'updated_by' => $userId,
                    ]);
                }

                // 4. Auto-generate the Invoice linked to the Dispatch
                $discountAmount = (float) ($invoiceData['discount_amount'] ?? 0);
                $taxAmount = (float) ($invoiceData['tax_amount'] ?? 0);
                $totalInvoiceAmount = $totalSalesAmount - $discountAmount + $taxAmount;

                Invoice::create([
                    'invoice_number' => $invoiceData['invoice_number'] ?? null, // Model boot auto-generates if empty
                    'buyer_id' => $dispatch->buyer_id,
                    'stock_dispatch_id' => $dispatch->id,
                    'invoice_date' => $dispatch->dispatch_date,
                    'due_date' => $dispatch->dispatch_date, // matches dispatch date by default
                    'sub_total' => $totalSalesAmount,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalInvoiceAmount,
                    'payment_status' => 'unpaid',
                    'payment_method' => $invoiceData['payment_method'] ?? null,
                    'notes' => $invoiceData['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                return $dispatch;
            });

            // Log activity
            $this->logActivity(
                'CREATE',
                'StockDispatch',
                "Created stock dispatch: {$dispatch->dispatch_number} for buyer ID: {$dispatch->buyer_id} containing {$dispatch->total_bags} bags",
                $request->validated()
            );

            $dispatch->load(['buyer', 'warehouse', 'branch', 'vehicle', 'items.stockBag', 'invoice']);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock dispatch created successfully',
                'data' => $dispatch,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock dispatch',
                'error' => $th->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified stock dispatch.
     */
    public function show(string $id)
    {
        try {
            $dispatch = StockDispatch::with([
                'buyer',
                'warehouse',
                'branch',
                'vehicle',
                'vehicleLog',
                'items.stockBag.itemType',
                'items.stockBag.itemVariety',
                'invoice',
                'creator:id,name,username',
            ])->find($id);

            if (! $dispatch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock dispatch not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'StockDispatch', "Retrieved stock dispatch details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock dispatch retrieved successfully',
                'data' => $dispatch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock dispatch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified stock dispatch in storage (Draft Status Only).
     */
    public function update(UpdateStockDispatchRequest $request, string $id)
    {
        try {
            $dispatch = StockDispatch::find($id);

            if (! $dispatch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock dispatch not found',
                ], 404);
            }

            // Ensure editing is restricted only to drafts
            if ($dispatch->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft stock dispatches can be updated. This dispatch is: ' . $dispatch->status,
                ], 422);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($dispatch, $validated) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                // Revert status of current dispatch bags back to 'in_stock'
                $oldItems = DispatchItem::where('stock_dispatch_id', $dispatch->id)->get();
                $oldBagIds = $oldItems->pluck('stock_bag_id')->toArray();
                
                if (!empty($oldBagIds)) {
                    StockBag::whereIn('id', $oldBagIds)->update([
                        'status' => 'in_stock',
                        'selling_price' => 0.00,
                        'total_sales_amount' => 0.00,
                        'updated_by' => $userId,
                    ]);
                }

                // Delete old dispatch items
                DispatchItem::where('stock_dispatch_id', $dispatch->id)->delete();

                // Build values
                $warehouseId = $validated['warehouse_id'] ?? $dispatch->warehouse_id;
                $branchId = $validated['branch_id'] ?? $dispatch->branch_id;

                // Process items if provided, else keep same bags (we need to lock them again)
                $itemsData = $validated['items'] ?? null;
                
                if ($itemsData !== null) {
                    // Gather all new Bag IDs and validate they are 'in_stock' (which they are, since we just reverted)
                    $newBagIds = collect($itemsData)->pluck('stock_bag_id')->toArray();
                    $newBags = StockBag::whereIn('id', $newBagIds)->get();

                    foreach ($newBags as $bag) {
                        if ($bag->status !== 'in_stock') {
                            throw new \Exception("Stock Bag ID {$bag->id} ({$bag->bag_code}) is not in stock.");
                        }
                    }

                    $itemsPriceMap = collect($itemsData)->keyBy('stock_bag_id');
                    $totalBags = count($itemsData);
                    $totalWeight = 0;
                    $totalSalesAmount = 0;

                    foreach ($newBags as $bag) {
                        $itemInput = $itemsPriceMap[$bag->id];
                        $sellingPrice = (float) $itemInput['selling_price'];
                        $bagWeight = (float) $bag->bag_weight;
                        $itemTotal = $bagWeight * $sellingPrice;

                        $totalWeight += $bagWeight;
                        $totalSalesAmount += $itemTotal;

                        // Create dispatch item
                        DispatchItem::create([
                            'stock_dispatch_id' => $dispatch->id,
                            'stock_bag_id' => $bag->id,
                            'selling_price' => $sellingPrice,
                            'bag_weight' => $bagWeight,
                            'notes' => $itemInput['notes'] ?? null,
                            'created_by' => $userId,
                        ]);

                        // Set back to dispatched
                        StockBag::where('id', $bag->id)->update([
                            'status' => 'dispatched',
                            'selling_price' => $sellingPrice,
                            'total_sales_amount' => $itemTotal,
                            'updated_by' => $userId,
                        ]);
                    }

                    $dispatch->total_bags = $totalBags;
                    $dispatch->total_weight = $totalWeight;
                    $dispatch->total_sales_amount = $totalSalesAmount;
                } else {
                    // Re-lock the old bags if no items array was passed (edge case)
                    if (!empty($oldBagIds)) {
                        foreach ($oldItems as $oldItem) {
                            StockBag::where('id', $oldItem->stock_bag_id)->update([
                                'status' => 'dispatched',
                                'selling_price' => $oldItem->selling_price,
                                'total_sales_amount' => $oldItem->bag_weight * $oldItem->selling_price,
                                'updated_by' => $userId,
                            ]);
                            
                            // Re-create the item record
                            DispatchItem::create([
                                'stock_dispatch_id' => $dispatch->id,
                                'stock_bag_id' => $oldItem->stock_bag_id,
                                'selling_price' => $oldItem->selling_price,
                                'bag_weight' => $oldItem->bag_weight,
                                'notes' => $oldItem->notes,
                                'created_by' => $userId,
                            ]);
                        }
                    }
                }

                // Update headers
                $dispatch->warehouse_id = $warehouseId;
                $dispatch->branch_id = $branchId;
                $dispatch->buyer_id = $validated['buyer_id'] ?? $dispatch->buyer_id;
                $dispatch->dispatch_type = $validated['dispatch_type'] ?? $dispatch->dispatch_type;
                $dispatch->dispatch_date = $validated['dispatch_date'] ?? $dispatch->dispatch_date;
                $dispatch->delivery_note_reference = $validated['delivery_note_reference'] ?? $dispatch->delivery_note_reference;
                $dispatch->vehicle_id = $validated['vehicle_id'] ?? $dispatch->vehicle_id;
                $dispatch->vehicle_log_id = $validated['vehicle_log_id'] ?? $dispatch->vehicle_log_id;
                $dispatch->notes = $validated['notes'] ?? $dispatch->notes;
                $dispatch->updated_by = $userId;
                $dispatch->save();

                // Update Invoice
                $invoice = Invoice::where('stock_dispatch_id', $dispatch->id)->first();
                if ($invoice) {
                    $invoiceData = $validated['invoice'] ?? [];
                    
                    $subTotal = $dispatch->total_sales_amount;
                    $discountAmount = isset($invoiceData['discount_amount']) ? (float) $invoiceData['discount_amount'] : (float) $invoice->discount_amount;
                    $taxAmount = isset($invoiceData['tax_amount']) ? (float) $invoiceData['tax_amount'] : (float) $invoice->tax_amount;
                    $totalInvoiceAmount = $subTotal - $discountAmount + $taxAmount;

                    $invoice->update([
                        'invoice_number' => $invoiceData['invoice_number'] ?? $invoice->invoice_number,
                        'buyer_id' => $dispatch->buyer_id,
                        'invoice_date' => $dispatch->dispatch_date,
                        'due_date' => $dispatch->dispatch_date,
                        'sub_total' => $subTotal,
                        'discount_amount' => $discountAmount,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $totalInvoiceAmount,
                        'payment_method' => $invoiceData['payment_method'] ?? $invoice->payment_method,
                        'notes' => $invoiceData['notes'] ?? $invoice->notes,
                        'updated_by' => $userId,
                    ]);
                }
            });

            // Log activity
            $this->logActivity('UPDATE', 'StockDispatch', "Updated draft stock dispatch: {$dispatch->dispatch_number}", $validated);

            $dispatch->load(['buyer', 'warehouse', 'branch', 'vehicle', 'items.stockBag', 'invoice']);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock dispatch updated successfully',
                'data' => $dispatch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock dispatch',
                'error' => $th->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified stock dispatch (Draft Status Only).
     */
    public function destroy(string $id)
    {
        try {
            $dispatch = StockDispatch::find($id);

            if (! $dispatch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock dispatch not found',
                ], 404);
            }

            if ($dispatch->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft stock dispatches can be deleted. This record is: ' . $dispatch->status,
                ], 422);
            }

            DB::transaction(function () use ($dispatch) {
                $authUser = auth('api')->user();
                $userId = $authUser->id ?? 1;

                // Revert all associated bag statuses to in_stock
                $items = DispatchItem::where('stock_dispatch_id', $dispatch->id)->get();
                $bagIds = $items->pluck('stock_bag_id')->toArray();

                if (!empty($bagIds)) {
                    StockBag::whereIn('id', $bagIds)->update([
                        'status' => 'in_stock',
                        'selling_price' => 0.00,
                        'total_sales_amount' => 0.00,
                        'updated_by' => $userId,
                    ]);
                }

                // Delete the dispatch (which cascades items and invoices)
                $dispatch->delete();
            });

            $this->logActivity('DELETE', 'StockDispatch', "Deleted draft stock dispatch: {$dispatch->dispatch_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock dispatch deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock dispatch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Confirm dispatch and issue gate pass number.
     */
    public function confirmGatePass(string $id)
    {
        try {
            $dispatch = StockDispatch::find($id);

            if (!$dispatch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock dispatch not found',
                ], 404);
            }

            if ($dispatch->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft dispatches can be confirmed. Status is: ' . $dispatch->status,
                ], 422);
            }

            $dispatch->status = 'pending_gate_pass';
            
            // Generate gate pass number sequentially if not already set
            if (empty($dispatch->gate_pass_number)) {
                $datePrefix = date('Ymd');
                $randomStr = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                $dispatch->gate_pass_number = 'GP-' . $datePrefix . '-' . $randomStr;
            }

            $dispatch->updated_by = auth()->id() ?? 1;
            $dispatch->save();

            $this->logActivity('CONFIRM', 'StockDispatch', "Confirmed dispatch and issued gate pass: {$dispatch->gate_pass_number} for {$dispatch->dispatch_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock dispatch confirmed. Gate pass issued successfully',
                'data' => $dispatch->load(['buyer', 'invoice']),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm stock dispatch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Record the gate exit of a dispatch.
     */
    public function recordGateExit(Request $request, string $id)
    {
        try {
            $dispatch = StockDispatch::find($id);

            if (!$dispatch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock dispatch not found',
                ], 404);
            }

            if ($dispatch->status === 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dispatch must be confirmed (pending gate pass) before recording exit.',
                ], 422);
            }

            if ($dispatch->status === 'dispatched' && $dispatch->gate_exit_at) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gate exit has already been recorded for this dispatch.',
                ], 422);
            }

            DB::transaction(function () use ($dispatch) {
                $userId = auth()->id() ?? 1;
                $dispatch->status = 'dispatched';
                $dispatch->gate_exit_at = now();
                $dispatch->updated_by = $userId;
                $dispatch->save();

                // If linked to a vehicle log, record vehicle log exit as well
                if ($dispatch->vehicle_log_id) {
                    $log = VehicleLog::find($dispatch->vehicle_log_id);
                    if ($log && !$log->exit_time) {
                        $log->direction = 'out';
                        $log->exit_time = now();
                        $log->save();
                    }
                }
            });

            $this->logActivity('EXIT', 'StockDispatch', "Recorded gate exit for dispatch: {$dispatch->dispatch_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Gate exit recorded successfully',
                'data' => $dispatch->load(['buyer', 'invoice', 'vehicleLog']),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record gate exit',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
