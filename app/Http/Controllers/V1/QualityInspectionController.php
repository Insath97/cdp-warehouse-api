<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateQualityInspectionRequest;
use App\Http\Requests\UpdateQualityInspectionRequest;
use App\Models\ItemVariety;
use App\Models\QualityInspection;
use App\Models\StockBag;
use App\Models\StockInBatch;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class QualityInspectionController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:QualityInspection Index', ['only' => ['index', 'show']]),
            new Middleware('permission:QualityInspection Create', ['only' => ['store']]),
            new Middleware('permission:QualityInspection Update', ['only' => ['update']]),
            new Middleware('permission:QualityInspection Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of quality inspections.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = QualityInspection::with([
                'stockInBatch:id,batch_number,grn_number',
                'stockBag:id,bag_code,bag_number',
                'itemType:id,name,code',
                'itemVariety:id,name,code',
                'inspector:id,name,username,email,user_scope,branch_id,warehouse_id',
            ]);

            // Apply Search Scope
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filters
            if ($request->has('batch_id') && $request->batch_id != '') {
                $query->byBatch($request->batch_id);
            }

            if ($request->has('bag_id') && $request->bag_id != '') {
                $query->byBag($request->bag_id);
            }

            if ($request->has('item_variety_id') && $request->item_variety_id != '') {
                $query->where('item_variety_id', $request->item_variety_id);
            }

            if ($request->has('grade') && $request->grade != '') {
                $query->byGrade($request->grade);
            }

            if ($request->has('inspection_result') && $request->inspection_result != '') {
                $query->byResult($request->inspection_result);
            }

            if ($request->has('weight_change_type') && $request->weight_change_type != '') {
                $query->where('weight_change_type', $request->weight_change_type);
            }

            $inspections = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Quality inspections retrieved successfully',
                'data' => $inspections,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve quality inspections',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created quality inspection in storage.
     */
    public function store(CreateQualityInspectionRequest $request)
    {
        try {
            $validated = $request->validated();

            $validated['inspected_by'] = auth('api')->id() ?? auth()->id() ?? 1;
            $validated['inspected_at'] = $validated['inspected_at'] ?? now();

            // Auto fetch item_type_id if missing
            if (empty($validated['item_type_id']) && !empty($validated['item_variety_id'])) {
                $variety = ItemVariety::find($validated['item_variety_id']);
                $validated['item_type_id'] = $variety->item_type_id ?? null;
            }

            // Auto fetch original_weight if missing
            if (!isset($validated['original_weight'])) {
                if (!empty($validated['stock_bag_id'])) {
                    $bag = StockBag::find($validated['stock_bag_id']);
                    if ($bag) {
                        $validated['original_weight'] = $bag->bag_weight;
                    }
                } elseif (!empty($validated['stock_in_batch_id'])) {
                    $batch = StockInBatch::find($validated['stock_in_batch_id']);
                    if ($batch) {
                        $validated['original_weight'] = $batch->net_weight;
                    }
                }
            }

            $inspection = QualityInspection::create($validated);

            // Log activity
            $targetStr = $inspection->stock_bag_id ? "bag ID: {$inspection->stock_bag_id}" : "batch ID: {$inspection->stock_in_batch_id}";
            $this->logActivity('CREATE', 'QualityInspection', "Created quality inspection for {$targetStr} with result: {$inspection->inspection_result}", $validated);

            $inspection->load([
                'stockInBatch:id,batch_number,grn_number',
                'stockBag:id,bag_code,bag_number',
                'itemType:id,name,code',
                'itemVariety:id,name,code',
                'inspector:id,name,username,email,user_scope,branch_id,warehouse_id',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Quality inspection created successfully',
                'data' => $inspection,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create quality inspection',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified quality inspection.
     */
    public function show(string $id)
    {
        try {
            $inspection = QualityInspection::with([
                'stockInBatch:id,batch_number,grn_number,received_date',
                'stockBag:id,bag_code,bag_number,bag_weight,status',
                'itemType:id,name,code',
                'itemVariety:id,name,code,slug',
                'inspector:id,name,username,email,user_scope,branch_id,warehouse_id',
            ])->find($id);

            if (!$inspection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Quality inspection not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Quality inspection retrieved successfully',
                'data' => $inspection,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve quality inspection',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified quality inspection in storage.
     */
    public function update(UpdateQualityInspectionRequest $request, string $id)
    {
        try {
            $inspection = QualityInspection::find($id);

            if (!$inspection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Quality inspection not found',
                ], 404);
            }

            $validated = $request->validated();
            $inspection->update($validated);

            $this->logActivity('UPDATE', 'QualityInspection', "Updated quality inspection ID: {$inspection->id}", $validated);

            $inspection->load([
                'stockInBatch:id,batch_number',
                'stockBag:id,bag_code',
                'itemType:id,name,code',
                'itemVariety:id,name,code',
                'inspector:id,name,username,email,user_scope,branch_id,warehouse_id',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Quality inspection updated successfully',
                'data' => $inspection,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update quality inspection',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified quality inspection from storage.
     */
    public function destroy(string $id)
    {
        try {
            $inspection = QualityInspection::find($id);

            if (!$inspection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Quality inspection not found',
                ], 404);
            }

            $inspectionId = $inspection->id;
            $inspection->delete();

            $this->logActivity('DELETE', 'QualityInspection', "Deleted quality inspection ID: {$inspectionId}");

            return response()->json([
                'status' => 'success',
                'message' => 'Quality inspection deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete quality inspection',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
