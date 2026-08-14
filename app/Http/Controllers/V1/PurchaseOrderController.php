<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrderBargainRequest;
use App\Http\Requests\PurchaseOrderPaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBargain;
use App\Models\Supplier;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\PurchaseOrderNotification;
use Illuminate\Support\Facades\Notification;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:PurchaseOrder Index', ['only' => ['index', 'show', 'getActiveList']]),
            new Middleware('permission:PurchaseOrder Create', ['only' => ['store', 'update', 'destroy']]),
            new Middleware('permission:PurchaseOrder Create|PurchaseOrder Approve', ['only' => ['bargain']]),
            new Middleware('permission:PurchaseOrder Verify', ['only' => ['verify', 'updatePayment']]),
        ];
    }

    /**
     * Display a listing of the purchase orders.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = PurchaseOrder::with(['supplier', 'warehouse', 'itemVariety', 'creator', 'latestBargain']);

            // Filter by authenticated user scope
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            // Apply Search Scope
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Apply Filters
            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->has('supplier_id') && $request->supplier_id != '') {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('status') && $request->status != '') {
                $query->byStatus($request->status);
            }

            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->where('payment_status', $request->payment_status);
            }

            $purchaseOrders = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $this->logActivity('INDEX', 'PurchaseOrder', 'Retrieved purchase orders listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase orders retrieved successfully',
                'data' => $purchaseOrders,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase orders',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created purchase order (with optional supplier profile on-the-fly).
     */
    public function store(CreatePurchaseOrderRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $authUser = auth('api')->user();

            // 1. Create Supplier on-the-fly if supplier block is present
            if (isset($data['supplier']) && is_array($data['supplier'])) {
                $supplierData = $data['supplier'];
                $bankAccountsData = $supplierData['bank_accounts'] ?? [];
                unset($supplierData['bank_accounts']);

                // Create supplier
                $supplier = Supplier::create($supplierData);

                // Create bank accounts if provided
                if (!empty($bankAccountsData)) {
                    $hasPrimary = collect($bankAccountsData)->contains('is_primary', true);
                    foreach ($bankAccountsData as $index => $acctData) {
                        if (!$hasPrimary && $index === 0) {
                            $acctData['is_primary'] = true;
                        }
                        $supplier->bankAccounts()->create($acctData);
                    }
                }

                $data['supplier_id'] = $supplier->id;
                unset($data['supplier']);

                $this->logActivity('CREATE', 'Supplier', "Created supplier on-the-fly: {$supplier->name} ({$supplier->code}) during PO creation");
            }

            // 2. Set defaults, calculate total prices, and save Purchase Order
            $data['created_by'] = $authUser->id;
            $data['status'] = 'pending_approval';
            $data['payment_status'] = 'pending';

            $data['total_sales_price'] = $data['purchase_price_per_kg'] * $data['total_weights'];
            if (isset($data['market_price_per_kg'])) {
                $data['total_market_price'] = $data['market_price_per_kg'] * $data['total_weights'];
            } else {
                $data['total_market_price'] = null;
            }

            $po = PurchaseOrder::create($data);

            // 3. Write initial bargain loop history record
            $po->bargains()->create([
                'user_id' => $authUser->id,
                'action' => 'created',
                'purchase_price_per_kg' => $po->purchase_price_per_kg,
                'total_sales_price' => $po->total_sales_price,
                'note' => $po->notes ?? 'Initial creation',
            ]);

            DB::commit();

            $this->logActivity('CREATE', 'PurchaseOrder', "Created purchase order: {$po->po_number}", $data);

            // Notify User B (Approvers) who have access to this PO's warehouse
            try {
                $approvers = User::permission('PurchaseOrder Approve')->get()->filter(function ($user) use ($po) {
                    $accessibleIds = $user->getAccessibleWarehouseIds();
                    return is_null($accessibleIds) || in_array($po->warehouse_id, $accessibleIds);
                });
                
                if ($approvers->isNotEmpty()) {
                    Notification::send($approvers, new PurchaseOrderNotification(
                        $po,
                        "New Purchase Order Created - {$po->po_number}",
                        "A new purchase order has been created and requires your review.",
                        "Review Purchase Order",
                        config('app.url') . "/purchase-orders/{$po->id}"
                    ));
                }
            } catch (\Throwable $e) {
                logger()->error("Failed to send PO creation notification: " . $e->getMessage());
            }

            $po->load(['supplier', 'warehouse', 'itemVariety', 'creator', 'latestBargain']);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order created successfully',
                'data' => $po,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create purchase order',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified purchase order details.
     */
    public function show(string $id)
    {
        try {
            $query = PurchaseOrder::with(['supplier.bankAccounts.bank', 'warehouse', 'itemVariety', 'creator', 'updater', 'verifier', 'bargains.user']);

            // User scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            $po = $query->find($id);

            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'PurchaseOrder', "Retrieved purchase order details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order retrieved successfully',
                'data' => $po,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase order',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified purchase order details (Only allowed during negotiation turn).
     */
    public function update(UpdatePurchaseOrderRequest $request, string $id)
    {
        try {
            $query = PurchaseOrder::query();

            // User scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            $po = $query->find($id);

            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            // Negotiation check
            if (!in_array($po->status, ['pending_approval', 'price_suggested'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order cannot be updated in its current status: ' . $po->status,
                ], 400);
            }

            if ($po->status === 'price_suggested' && !$po->isWaitingForCreator()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update the purchase order. It is currently waiting for the approver\'s response.',
                ], 400);
            }

            if ((int) $po->created_by !== (int) $authUser->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only the creator of the purchase order can update its basic details.',
                ], 403);
            }

            $data = $request->validated();
            $data['updated_by'] = $authUser->id;

            // Recalculate totals from backend parameters
            $purchasePrice = $data['purchase_price_per_kg'] ?? $po->purchase_price_per_kg;
            $marketPrice = array_key_exists('market_price_per_kg', $data) ? $data['market_price_per_kg'] : $po->market_price_per_kg;
            $totalWeights = $data['total_weights'] ?? $po->total_weights;

            $data['total_sales_price'] = $purchasePrice * $totalWeights;
            if ($marketPrice !== null) {
                $data['total_market_price'] = $marketPrice * $totalWeights;
            } else {
                $data['total_market_price'] = null;
            }

            if ($po->status === 'price_suggested') {
                $data['status'] = 'pending_approval';
            }

            $po->update($data);

            // Add edited bargain entry to history
            $po->bargains()->create([
                'user_id' => $authUser->id,
                'action' => 'updated',
                'purchase_price_per_kg' => $po->purchase_price_per_kg,
                'total_sales_price' => $po->total_sales_price,
                'note' => $data['notes'] ?? 'Updated purchase order details',
            ]);

            $this->logActivity('UPDATE', 'PurchaseOrder', "Updated purchase order: {$po->po_number}", $data);

            // Notify Approvers of the update
            try {
                $approvers = User::permission('PurchaseOrder Approve')->get()->filter(function ($user) use ($po) {
                    $accessibleIds = $user->getAccessibleWarehouseIds();
                    return is_null($accessibleIds) || in_array($po->warehouse_id, $accessibleIds);
                });
                if ($approvers->isNotEmpty()) {
                    Notification::send($approvers, new PurchaseOrderNotification(
                        $po,
                        "Purchase Order Updated - {$po->po_number}",
                        "The creator has updated the purchase order and it is ready for your review.",
                        "Review Purchase Order",
                        config('app.url') . "/purchase-orders/{$po->id}",
                        $data['notes'] ?? null
                    ));
                }
            } catch (\Throwable $e) {
                logger()->error("Failed to send PO update notification: " . $e->getMessage());
            }

            $po->load(['supplier', 'warehouse', 'itemVariety', 'creator', 'latestBargain']);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order updated successfully',
                'data' => $po,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update purchase order',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified purchase order (Only allowed if pending or cancelled).
     */
    public function destroy(string $id)
    {
        try {
            $query = PurchaseOrder::query();

            // User scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            $po = $query->find($id);

            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            if (!in_array($po->status, ['pending_approval', 'cancelled'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order can only be deleted if its status is pending_approval or cancelled.',
                ], 400);
            }

            $poNumber = $po->po_number;
            $po->delete();

            $this->logActivity('DELETE', 'PurchaseOrder', "Deleted purchase order: {$poNumber}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete purchase order',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle the loopable bargaining negotiation actions (Approve, Suggest Price, Reject, Cancel).
     */
    public function bargain(PurchaseOrderBargainRequest $request, string $id)
    {
        try {
            $query = PurchaseOrder::query();

            // User scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            $po = $query->find($id);

            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            // Check if negotiable
            if (!in_array($po->status, ['pending_approval', 'price_suggested'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This purchase order is not in a negotiable status: ' . $po->status,
                ], 400);
            }

            $data = $request->validated();
            $action = $data['action'];
            $isCreator = ((int) $po->created_by === (int) $authUser->id);

            // Only users with PurchaseOrder Approve permission can approve
            if ($action === 'approve') {
                if (!$authUser->hasPermissionTo('PurchaseOrder Approve', 'api')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You do not have permission to approve purchase orders.',
                    ], 403);
                }
            }

            // Enforce turn-taking loop constraints
            if ($po->status === 'price_suggested') {
                if ($isCreator && !$po->isWaitingForCreator()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'It is not your turn to act. Currently waiting for the approver\'s response.',
                    ], 400);
                }
                if (!$isCreator && !$po->isWaitingForApprover()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'It is not your turn to act. Currently waiting for the creator\'s response.',
                    ], 400);
                }
            } else {
                // status is 'pending_approval'
                // Creator cannot approve their own initial request
                if ($isCreator && $action === 'approve') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You cannot self-approve your own purchase order.',
                    ], 400);
                }
            }

            // Process Action
            DB::beginTransaction();
            switch ($action) {
                case 'approve':
                    $po->status = 'approved';
                    break;
                case 'reject':
                    $po->status = 'rejected';
                    $po->payment_status = 'cancel'; // If rejected, payment is cancelled
                    break;
                case 'cancel':
                    $po->status = 'cancelled';
                    $po->payment_status = 'cancel'; // If cancelled, payment is cancelled
                    break;
                case 'suggest_price':
                    $po->status = $isCreator ? 'pending_approval' : 'price_suggested';
                    $po->purchase_price_per_kg = $data['purchase_price_per_kg'];
                    $po->total_sales_price = $data['purchase_price_per_kg'] * $po->total_weights;
                    break;
            }

            $po->save();

            // Log entry into bargain history
            $po->bargains()->create([
                'user_id' => $authUser->id,
                'action' => $action,
                'purchase_price_per_kg' => $po->purchase_price_per_kg,
                'total_sales_price' => $po->total_sales_price,
                'note' => $data['note'] ?? null,
            ]);

            DB::commit();

            $this->logActivity('BARGAIN', 'PurchaseOrder', "Processed bargaining action '{$action}' on PO: {$po->po_number}", $data);

            // Dispatch Notifications based on action
            try {
                if ($action === 'suggest_price') {
                    // Counter offer: determine recipient
                    if ((int)$po->created_by === (int)$authUser->id) {
                        // Creator counter-offered: notify Approvers (User B)
                        $approvers = User::permission('PurchaseOrder Approve')->get()->filter(function ($user) use ($po) {
                            $accessibleIds = $user->getAccessibleWarehouseIds();
                            return is_null($accessibleIds) || in_array($po->warehouse_id, $accessibleIds);
                        });
                        if ($approvers->isNotEmpty()) {
                            Notification::send($approvers, new PurchaseOrderNotification(
                                $po,
                                "New Counter-Offer on PO - {$po->po_number}",
                                "The creator has counter-suggested a price of LKR " . number_format((float)$po->purchase_price_per_kg, 2) . "/kg.",
                                "View Counter-Offer",
                                config('app.url') . "/purchase-orders/{$po->id}",
                                $data['note'] ?? null
                            ));
                        }
                    } else {
                        // Approver counter-offered: notify Creator (User A)
                        $creator = $po->creator;
                        if ($creator) {
                            $creator->notify(new PurchaseOrderNotification(
                                $po,
                                "New Counter-Offer on PO - {$po->po_number}",
                                "An approver has counter-suggested a price of LKR " . number_format((float)$po->purchase_price_per_kg, 2) . "/kg.",
                                "View Counter-Offer",
                                config('app.url') . "/purchase-orders/{$po->id}",
                                $data['note'] ?? null
                            ));
                        }
                    }
                } elseif ($action === 'approve') {
                    // User B approved the PO: notify User C (Verifiers)
                    $verifiers = User::permission('PurchaseOrder Verify')->get()->filter(function ($user) use ($po) {
                        $accessibleIds = $user->getAccessibleWarehouseIds();
                        return is_null($accessibleIds) || in_array($po->warehouse_id, $accessibleIds);
                    });
                    if ($verifiers->isNotEmpty()) {
                        Notification::send($verifiers, new PurchaseOrderNotification(
                            $po,
                            "Purchase Order Approved - {$po->po_number}",
                            "A purchase order has been approved and requires administrative verification and payment progress.",
                            "Verify Purchase Order",
                            config('app.url') . "/purchase-orders/{$po->id}",
                            $data['note'] ?? null
                        ));
                    }
                } elseif (in_array($action, ['reject', 'cancel'])) {
                    // Notify creator and the approvers (other parties)
                    $recipients = collect();
                    if ($po->creator && (int)$po->created_by !== (int)$authUser->id) {
                        $recipients->push($po->creator);
                    }
                    // Fetch approvers who aren't the current user
                    $approvers = User::permission('PurchaseOrder Approve')->get()->filter(function ($user) use ($po, $authUser) {
                        if ((int)$user->id === (int)$authUser->id) return false;
                        $accessibleIds = $user->getAccessibleWarehouseIds();
                        return is_null($accessibleIds) || in_array($po->warehouse_id, $accessibleIds);
                    });
                    $recipients = $recipients->merge($approvers);

                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients, new PurchaseOrderNotification(
                            $po,
                            "Purchase Order " . ucfirst($action) . "ed - {$po->po_number}",
                            "The purchase order negotiation has been " . $action . "ed.",
                            "View Details",
                            config('app.url') . "/purchase-orders/{$po->id}",
                            $data['note'] ?? null
                        ));
                    }
                }
            } catch (\Throwable $e) {
                logger()->error("Failed to send PO bargain notification: " . $e->getMessage());
            }

            $po->load(['supplier', 'warehouse', 'itemVariety', 'creator', 'latestBargain']);

            return response()->json([
                'status' => 'success',
                'message' => "Bargaining action '{$action}' processed successfully",
                'data' => $po,
            ], 200);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process bargaining action',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify an approved purchase order (Moves status to verified).
     */
    public function verify(string $id)
    {
        DB::beginTransaction();
        try {
            $query = PurchaseOrder::query();

            // User scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            $po = $query->find($id);

            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            if ($po->status !== 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order can only be verified if its status is approved.',
                ], 400);
            }

            $po->status = 'verified';
            $po->verified_by = $authUser->id;
            $po->verified_at = now();
            $po->save();

            // Log verifier event in bargain history
            $po->bargains()->create([
                'user_id' => $authUser->id,
                'action' => 'verified',
                'purchase_price_per_kg' => $po->purchase_price_per_kg,
                'total_sales_price' => $po->total_sales_price,
                'note' => 'Purchase order verified by administrative staff',
            ]);

            DB::commit();

            $this->logActivity('VERIFY', 'PurchaseOrder', "Verified purchase order: {$po->po_number}");

            // Notify Creator (User A) and Approvers (User B) that the PO has been verified
            try {
                $recipients = collect();
                if ($po->creator) {
                    $recipients->push($po->creator);
                }
                
                // Get all users who negotiated this PO
                $bargainers = User::whereIn('id', $po->bargains()->pluck('user_id'))->get();
                $recipients = $recipients->merge($bargainers)->unique('id')->filter(function ($user) use ($authUser) {
                    return (int)$user->id !== (int)$authUser->id;
                });

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new PurchaseOrderNotification(
                        $po,
                        "Purchase Order Verified - {$po->po_number}",
                        "The purchase order has been officially verified by administrative staff and is ready for payment progress.",
                        "View Details",
                        config('app.url') . "/purchase-orders/{$po->id}"
                    ));
                }
            } catch (\Throwable $e) {
                logger()->error("Failed to send PO verification notification: " . $e->getMessage());
            }

            $po->load(['supplier', 'warehouse', 'itemVariety', 'creator', 'verifier', 'latestBargain']);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order verified successfully',
                'data' => $po,
            ], 200);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify purchase order',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the payment status of a verified purchase order (Allows uploading proof document).
     */
    public function updatePayment(PurchaseOrderPaymentRequest $request, string $id)
    {
        DB::beginTransaction();
        try {
            $query = PurchaseOrder::query();

            // User scope filtering
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $query->whereIn('warehouse_id', $accessibleIds);
                }
            }

            $po = $query->find($id);

            if (!$po) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            // Enforce verified status guard (User C must verify it first)
            if ($po->status !== 'verified') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment updates are only allowed for verified purchase orders.',
                ], 400);
            }

            $data = $request->validated();
            $po->payment_status = $data['payment_status'];

            // Handle file upload using FileUploadTrait
            if ($request->hasFile('payment_proof_document')) {
                $proofPath = $this->handleFileUpload(
                    $request,
                    'payment_proof_document',
                    $po->payment_proof_document,
                    'purchase_orders/payment_proofs',
                    'PO_PROOF_' . $po->id . '_' . time()
                );
                if ($proofPath) {
                    $po->payment_proof_document = $proofPath;
                }
            }

            // Sync PO status based on payment status update
            if ($po->payment_status === 'cancel') {
                $po->status = 'cancelled';
                $po->bargains()->create([
                    'user_id' => $authUser->id,
                    'action' => 'cancelled',
                    'purchase_price_per_kg' => $po->purchase_price_per_kg,
                    'total_sales_price' => $po->total_sales_price,
                    'note' => 'Payment cancelled by user. PO moved to cancelled state.',
                ]);
            }

            $po->save();
            DB::commit();

            $this->logActivity('PAYMENT_UPDATE', 'PurchaseOrder', "Updated payment status for PO: {$po->po_number} to {$po->payment_status}", $data);

            // Notify Creator (User A) and Approvers (User B) that the PO has been paid/cancelled
            try {
                $recipients = collect();
                if ($po->creator) {
                    $recipients->push($po->creator);
                }
                
                // Get all users who negotiated this PO
                $bargainers = User::whereIn('id', $po->bargains()->pluck('user_id'))->get();
                $recipients = $recipients->merge($bargainers)->unique('id')->filter(function ($user) use ($authUser) {
                    return (int)$user->id !== (int)$authUser->id;
                });

                if ($recipients->isNotEmpty()) {
                    $subject = $po->payment_status === 'paid' 
                        ? "Purchase Order Paid - {$po->po_number}" 
                        : "Purchase Order Payment Cancelled - {$po->po_number}";
                    $message = $po->payment_status === 'paid' 
                        ? "The purchase order has been successfully paid, and the payment proof document has been uploaded."
                        : "The payment for this purchase order has been cancelled, and the order is now marked as cancelled.";

                    Notification::send($recipients, new PurchaseOrderNotification(
                        $po,
                        $subject,
                        $message,
                        "View Details",
                        config('app.url') . "/purchase-orders/{$po->id}"
                    ));
                }
            } catch (\Throwable $e) {
                logger()->error("Failed to send PO payment update notification: " . $e->getMessage());
            }

            $po->load(['supplier', 'warehouse', 'itemVariety', 'creator', 'verifier', 'latestBargain']);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order payment status updated successfully',
                'data' => $po,
            ], 200);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get active purchase orders list for select box (no permission required).
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = PurchaseOrder::with([
                'supplier:id,name,code',
                'warehouse:id,name,code',
                'itemVariety:id,name,code',
            ]);

            // Apply tenancy constraints
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouseIds);
                }
            }

            // Optional status filter
            if ($request->has('status') && $request->status != '') {
                $query->where('status', $request->status);
            }

            $purchaseOrders = $query->orderBy('created_at', 'desc')
                ->get(['id', 'po_number', 'supplier_id', 'warehouse_id', 'item_variety_id', 'status', 'total_weights', 'number_of_bags']);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase orders list retrieved successfully',
                'data' => $purchaseOrders,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase orders list',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
