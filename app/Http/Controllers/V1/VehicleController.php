<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VehicleController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Vehicle Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Vehicle List', ['only' => ['getActiveList']]),
            new Middleware('permission:Vehicle Create', ['only' => ['store']]),
            new Middleware('permission:Vehicle Update', ['only' => ['update', 'updateAvailabilityStatus']]),
            new Middleware('permission:Vehicle Delete', ['only' => ['destroy']]),
            new Middleware('permission:Vehicle Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of vehicles.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Vehicle::with([
                'supplier:id,code,name',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id'
            ]);

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('vehicle_type') && $request->vehicle_type != '') {
                $query->where('vehicle_type', $request->vehicle_type);
            }

            if ($request->has('ownership_type') && $request->ownership_type != '') {
                $query->byOwnershipType($request->ownership_type);
            }

            if ($request->has('availability_status') && $request->availability_status != '') {
                $query->byAvailabilityStatus($request->availability_status);
            }

            if ($request->has('supplier_id') && $request->supplier_id != '') {
                $query->where('supplier_id', $request->supplier_id);
            }

            $vehicles = $query->orderBy('vehicle_number', 'asc')->paginate($perPage);

            $this->logActivity('INDEX', 'Vehicle', 'Retrieved vehicles listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicles retrieved successfully',
                'data' => $vehicles,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve vehicles',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created vehicle in storage.
     */
    public function store(CreateVehicleRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth('api')->id() ?? auth()->id();
            $vehicle = Vehicle::create($data);
            $vehicle->load([
                'supplier:id,code,name',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id'
            ]);

            $this->logActivity('CREATE', 'Vehicle', "Created vehicle: {$vehicle->vehicle_number}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle created successfully',
                'data' => $vehicle,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create vehicle',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified vehicle.
     */
    public function show(string $id)
    {
        try {
            $vehicle = Vehicle::with([
                'supplier:id,code,name,phone_primary',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
                'updater:id,name,username,email'
            ])->find($id);

            if (! $vehicle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'Vehicle', "Retrieved vehicle details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle retrieved successfully',
                'data' => $vehicle,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve vehicle',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified vehicle in storage.
     */
    public function update(UpdateVehicleRequest $request, string $id)
    {
        try {
            $vehicle = Vehicle::find($id);

            if (! $vehicle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle not found',
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth('api')->id() ?? auth()->id();
            $vehicle->update($data);
            $vehicle->load([
                'supplier:id,code,name',
                'creator:id,name,username,email,user_scope,branch_id,warehouse_id',
                'updater:id,name,username,email'
            ]);

            $this->logActivity('UPDATE', 'Vehicle', "Updated vehicle: {$vehicle->vehicle_number}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle updated successfully',
                'data' => $vehicle,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update vehicle',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified vehicle from storage.
     */
    public function destroy(string $id)
    {
        try {
            $vehicle = Vehicle::find($id);

            if (! $vehicle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle not found',
                ], 404);
            }

            $vehicleNumber = $vehicle->vehicle_number;
            $vehicle->delete();

            $this->logActivity('DELETE', 'Vehicle', "Deleted vehicle: {$vehicleNumber}");

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete vehicle',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active vehicles (lightweight list).
     */
    public function getActiveList()
    {
        try {
            $vehicles = Vehicle::active()->orderBy('vehicle_number', 'asc')->get(['id', 'vehicle_number']);

            if ($vehicles->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active vehicles found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active vehicles retrieved successfully',
                'data' => $vehicles,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active vehicles',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the status of the vehicle.
     */
    public function toggleStatus(string $id)
    {
        try {
            $vehicle = Vehicle::find($id);

            if (!$vehicle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle not found'
                ], 404);
            }

            $vehicle->is_active = !$vehicle->is_active;
            $vehicle->save();

            $this->logActivity('TOGGLE_STATUS', 'Vehicle', "Toggled vehicle status: {$vehicle->vehicle_number} (" . ($vehicle->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle status updated successfully',
                'data' => [
                    'id' => $vehicle->id,
                    'is_active' => $vehicle->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle vehicle status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update vehicle operational availability status.
     */
    public function updateAvailabilityStatus(Request $request, string $id)
    {
        try {
            $vehicle = Vehicle::find($id);

            if (!$vehicle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle not found'
                ], 404);
            }

            $validated = $request->validate([
                'availability_status' => 'required|string|in:available,in_transit,maintenance,out_of_service',
            ]);

            $vehicle->availability_status = $validated['availability_status'];
            $vehicle->updated_by = auth('api')->id() ?? auth()->id();
            $vehicle->save();

            $this->logActivity('UPDATE_AVAILABILITY', 'Vehicle', "Updated vehicle availability status: {$vehicle->vehicle_number} to {$vehicle->availability_status}");

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle availability status updated successfully',
                'data' => [
                    'id' => $vehicle->id,
                    'vehicle_number' => $vehicle->vehicle_number,
                    'availability_status' => $vehicle->availability_status,
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update vehicle availability status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
