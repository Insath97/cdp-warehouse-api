<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WarehouseController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Warehouse Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Warehouse List', ['only' => ['getActiveList']]),
            new Middleware('permission:Warehouse Create', ['only' => ['store']]),
            new Middleware('permission:Warehouse Update', ['only' => ['update']]),
            new Middleware('permission:Warehouse Delete', ['only' => ['destroy']]),
            new Middleware('permission:Warehouse Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of warehouses.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Warehouse::with('branch');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('branch_id') && $request->branch_id != '') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $warehouses = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Warehouses retrieved successfully',
                'data' => $warehouses,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve warehouses',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created warehouse in storage.
     */
    public function store(CreateWarehouseRequest $request)
    {
        try {
            $data = $request->validated();
            $warehouse = Warehouse::create($data);
            $warehouse->load('branch');

            $this->logActivity('CREATE', 'Warehouse', "Created warehouse: {$warehouse->name} ({$warehouse->code})", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse created successfully',
                'data' => $warehouse,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create warehouse',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified warehouse.
     */
    public function show(string $id)
    {
        try {
            $warehouse = Warehouse::with('branch')->find($id);

            if (!$warehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Warehouse not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse retrieved successfully',
                'data' => $warehouse,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve warehouse',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified warehouse in storage.
     */
    public function update(UpdateWarehouseRequest $request, string $id)
    {
        try {
            $warehouse = Warehouse::find($id);

            if (!$warehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Warehouse not found',
                ], 404);
            }

            $data = $request->validated();
            $warehouse->update($data);
            $warehouse->load('branch');

            $this->logActivity('UPDATE', 'Warehouse', "Updated warehouse: {$warehouse->name} ({$warehouse->code})", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse updated successfully',
                'data' => $warehouse,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update warehouse',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified warehouse from storage.
     */
    public function destroy(string $id)
    {
        try {
            $warehouse = Warehouse::find($id);

            if (!$warehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Warehouse not found',
                ], 404);
            }

            $name = $warehouse->name;
            $code = $warehouse->code;
            $warehouse->delete();

            $this->logActivity('DELETE', 'Warehouse', "Deleted warehouse: {$name} ({$code})");

            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete warehouse',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a lightweight list of active warehouses (for dropdowns).
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = Warehouse::active();

            if ($request->has('branch_id') && $request->branch_id != '') {
                $query->where('branch_id', $request->branch_id);
            }

            $warehouses = $query->orderBy('name', 'asc')->get(['id', 'branch_id', 'name', 'code', 'city']);

            if ($warehouses->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active warehouses found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active warehouses retrieved successfully',
                'data' => $warehouses,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active warehouses',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the warehouse.
     */
    public function toggleStatus(string $id)
    {
        try {
            $warehouse = Warehouse::find($id);

            if (!$warehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Warehouse not found',
                ], 404);
            }

            $warehouse->is_active = !$warehouse->is_active;
            $warehouse->save();

            $this->logActivity('TOGGLE_STATUS', 'Warehouse', "Toggled warehouse status: {$warehouse->name} (" . ($warehouse->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Warehouse status updated successfully',
                'data' => [
                    'id' => $warehouse->id,
                    'is_active' => $warehouse->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle warehouse status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
