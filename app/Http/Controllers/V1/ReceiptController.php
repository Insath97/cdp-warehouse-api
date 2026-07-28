<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReceiptStatusRequest;
use App\Models\Receipt;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReceiptController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Receipt Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Receipt Update Status', ['only' => ['updateStatus']]),
        ];
    }

    /**
     * Display a listing of receipts.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Receipt::with([
                'stockInBatch:id,batch_number,grn_number,received_date,vehicle_id',
                'stockInBatch.vehicle:id,vehicle_number',
                'stockInBatch.items.itemType:id,name,code',
                'stockInBatch.items.itemVariety:id,name,code',
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'branch:id,code,name',
                'creator:id,name,username,email',
                'printedBy:id,name,username',
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
                $query->whereDate('receipt_date', '>=', $request->from_date);
            }

            if ($request->has('to_date') && $request->to_date != '') {
                $query->whereDate('receipt_date', '<=', $request->to_date);
            }

            $receipts = $query->orderBy('id', 'desc')->paginate($perPage);

            $this->logActivity('INDEX', 'Receipt', 'Retrieved receipts listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Receipts retrieved successfully',
                'data' => $receipts,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve receipts',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified receipt.
     */
    public function show(string $id)
    {
        try {
            $receipt = Receipt::with([
                'stockInBatch',
                'stockInBatch.supplier:id,code,name',
                'stockInBatch.warehouse:id,code,name',
                'stockInBatch.vehicle:id,vehicle_number',
                'stockInBatch.items.itemType:id,name,code',
                'stockInBatch.items.itemVariety:id,name,code',
                'supplier:id,code,name,phone,email',
                'warehouse:id,code,name',
                'branch:id,code,name',
                'creator:id,name,username,email',
                'printedBy:id,name,username',
            ])->find($id);

            if (!$receipt) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Receipt not found',
                ], 404);
            }

            // User Branch/Warehouse Scope Authorization Check
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && !in_array($receipt->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access. You do not have permission to view receipts for this warehouse.',
                    ], 403);
                }
            }

            $this->logActivity('SHOW', 'Receipt', "Retrieved receipt details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Receipt details retrieved successfully',
                'data' => $receipt,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve receipt details',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update status of the specified receipt.
     */
    public function updateStatus(UpdateReceiptStatusRequest $request, string $id)
    {
        try {
            $receipt = Receipt::find($id);

            if (!$receipt) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Receipt not found',
                ], 404);
            }

            // User Branch/Warehouse Scope Authorization Check
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleWarehouseIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleWarehouseIds) && !in_array($receipt->warehouse_id, $accessibleWarehouseIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access. You do not have permission to update receipts for this warehouse.',
                    ], 403);
                }
            }

            $validated = $request->validated();
            $oldStatus = $receipt->status;

            $receipt->status = $validated['status'];

            if ($validated['status'] === 'received') {
                $receipt->printed_at = now();
                $receipt->printed_by = $authUser->id ?? null;
            }

            if (isset($validated['notes'])) {
                $receipt->notes = $validated['notes'];
            }

            $receipt->save();

            $receipt->load([
                'stockInBatch:id,batch_number,grn_number',
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'creator:id,name,username,email',
                'printedBy:id,name,username',
            ]);

            $this->logActivity(
                'UPDATE_STATUS',
                'Receipt',
                "Updated status for receipt {$receipt->receipt_number} from {$oldStatus} to {$receipt->status}",
                $validated
            );

            return response()->json([
                'status' => 'success',
                'message' => "Receipt status updated to {$receipt->status} successfully",
                'data' => $receipt,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update receipt status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
