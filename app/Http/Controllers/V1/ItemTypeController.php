<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateItemTypeRequest;
use App\Http\Requests\UpdateItemTypeRequest;
use App\Models\ItemType;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ItemTypeController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ItemType Index', ['only' => ['index', 'show']]),
            new Middleware('permission:ItemType List', ['only' => ['getActiveList']]),
            new Middleware('permission:ItemType Create', ['only' => ['store']]),
            new Middleware('permission:ItemType Update', ['only' => ['update']]),
            new Middleware('permission:ItemType Delete', ['only' => ['destroy']]),
            new Middleware('permission:ItemType Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of item types.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ItemType::query();

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $itemTypes = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Item types retrieved successfully',
                'data' => $itemTypes,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve item types',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created item type in storage.
     */
    public function store(CreateItemTypeRequest $request)
    {
        try {
            $data = $request->validated();
            $itemType = ItemType::create($data);

            $this->logActivity('CREATE', 'ItemType', "Created item type: {$itemType->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Item type created successfully',
                'data' => $itemType,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create item type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified item type.
     */
    public function show(string $id)
    {
        try {
            $itemType = ItemType::find($id);

            if (! $itemType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item type not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Item type retrieved successfully',
                'data' => $itemType,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve item type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified item type in storage.
     */
    public function update(UpdateItemTypeRequest $request, string $id)
    {
        try {
            $itemType = ItemType::find($id);

            if (! $itemType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item type not found',
                ], 404);
            }

            $data = $request->validated();
            $itemType->update($data);

            $this->logActivity('UPDATE', 'ItemType', "Updated item type: {$itemType->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Item type updated successfully',
                'data' => $itemType,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update item type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified item type from storage.
     */
    public function destroy(string $id)
    {
        try {
            $itemType = ItemType::find($id);

            if (! $itemType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item type not found',
                ], 404);
            }

            $itemTypeName = $itemType->name;
            $itemType->delete();

            $this->logActivity('DELETE', 'ItemType', "Deleted item type: {$itemTypeName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Item type deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete item type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active item types (lightweight list).
     */
    public function getActiveList()
    {
        try {
            $itemTypes = ItemType::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            if ($itemTypes->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active item types found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active item types retrieved successfully',
                'data' => $itemTypes,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active item types',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the status of the item type.
     */
    public function toggleStatus(string $id)
    {
        try {
            $itemType = ItemType::find($id);

            if (!$itemType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item type not found'
                ], 404);
            }

            $itemType->is_active = !$itemType->is_active;
            $itemType->save();

            $this->logActivity('TOGGLE_STATUS', 'ItemType', "Toggled item type status: {$itemType->name} (" . ($itemType->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Item type status updated successfully',
                'data' => [
                    'id' => $itemType->id,
                    'is_active' => $itemType->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle item type status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
