<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateItemVarietyRequest;
use App\Http\Requests\UpdateItemVarietyRequest;
use App\Models\ItemVariety;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ItemVarietyController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ItemVariety Index', ['only' => ['index', 'show']]),
            new Middleware('permission:ItemVariety List', ['only' => ['getActiveList']]),
            new Middleware('permission:ItemVariety Create', ['only' => ['store']]),
            new Middleware('permission:ItemVariety Update', ['only' => ['update']]),
            new Middleware('permission:ItemVariety Delete', ['only' => ['destroy']]),
            new Middleware('permission:ItemVariety Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of the item varieties.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ItemVariety::query()->with('itemType');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filter by item type
            if ($request->has('item_type_id')) {
                $query->where('item_type_id', $request->get('item_type_id'));
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $varieties = $query->orderBy('name', 'asc')->paginate($perPage);

            $this->logActivity('INDEX', 'ItemVariety', 'Retrieved item varieties listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Item varieties retrieved successfully',
                'data' => $varieties,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve item varieties',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created item variety in storage.
     */
    public function store(CreateItemVarietyRequest $request)
    {
        try {
            $data = $request->validated();
            $variety = ItemVariety::create($data);

            // Log activity
            $this->logActivity('CREATE', 'ItemVariety', "Created item variety: {$variety->name} ({$variety->code}) under type ID: {$variety->item_type_id}", $data);

            $variety->load('itemType');

            return response()->json([
                'status' => 'success',
                'message' => 'Item variety created successfully',
                'data' => $variety,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create item variety',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified item variety.
     */
    public function show(string $id)
    {
        try {
            $variety = ItemVariety::with('itemType')->find($id);

            if (!$variety) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item variety not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'ItemVariety', "Retrieved item variety details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Item variety retrieved successfully',
                'data' => $variety,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve item variety',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified item variety in storage.
     */
    public function update(UpdateItemVarietyRequest $request, string $id)
    {
        try {
            $variety = ItemVariety::find($id);

            if (!$variety) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item variety not found',
                ], 404);
            }

            $data = $request->validated();
            $variety->update($data);

            // Log activity
            $this->logActivity('UPDATE', 'ItemVariety', "Updated item variety: {$variety->name} ({$variety->code})", $data);

            $variety->load('itemType');

            return response()->json([
                'status' => 'success',
                'message' => 'Item variety updated successfully',
                'data' => $variety,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update item variety',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified item variety from storage.
     */
    public function destroy(string $id)
    {
        try {
            $variety = ItemVariety::find($id);

            if (!$variety) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item variety not found',
                ], 404);
            }

            $varietyName = $variety->name;
            $varietyCode = $variety->code;
            $variety->delete();

            // Log activity
            $this->logActivity('DELETE', 'ItemVariety', "Deleted item variety: {$varietyName} ({$varietyCode})");

            return response()->json([
                'status' => 'success',
                'message' => 'Item variety deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete item variety',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the status of the item variety.
     */
    public function toggleStatus(string $id)
    {
        try {
            $variety = ItemVariety::find($id);

            if (!$variety) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item variety not found',
                ], 404);
            }

            $variety->is_active = !$variety->is_active;
            $variety->save();

            // Log activity
            $statusText = $variety->is_active ? 'Active' : 'Inactive';
            $this->logActivity('TOGGLE_STATUS', 'ItemVariety', "Toggled item variety status: {$variety->name} ({$statusText})");

            return response()->json([
                'status' => 'success',
                'message' => 'Item variety status updated successfully',
                'data' => [
                    'id' => $variety->id,
                    'is_active' => $variety->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle item variety status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a lightweight list of active item varieties for dropdowns.
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = ItemVariety::active();

            if ($request->has('item_type_id')) {
                $query->where('item_type_id', $request->get('item_type_id'));
            }

            $varieties = $query->orderBy('name', 'asc')->get(['id', 'name', 'code', 'slug', 'item_type_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active item varieties retrieved successfully',
                'data' => $varieties,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active item varieties',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
