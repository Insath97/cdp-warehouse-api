<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVehicleLogRequest;
use App\Http\Requests\ExitVehicleLogRequest;
use App\Http\Requests\UpdateVehicleLogRequest;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleLogController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:VehicleLog Index', ['only' => ['index', 'show', 'getByVehicle']]),
            new Middleware('permission:VehicleLog Create', ['only' => ['store']]),
            new Middleware('permission:VehicleLog View', ['only' => ['show', 'getByVehicle']]),
            new Middleware('permission:VehicleLog Update', ['only' => ['update']]),
            new Middleware('permission:VehicleLog Exit', ['only' => ['exitLog']]),
            new Middleware('permission:VehicleLog Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of vehicle logs.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = VehicleLog::with(['vehicle', 'creator:id,name,username,email,user_scope,branch_id,warehouse_id']);

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('log_type') && $request->log_type != '') {
                $query->where('log_type', $request->log_type);
            }

            if ($request->has('direction') && $request->direction != '') {
                $query->where('direction', $request->direction);
            }

            if ($request->has('vehicle_id') && $request->vehicle_id != '') {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            if ($request->has('start_date') && $request->start_date != '') {
                $query->whereDate('entry_time', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date != '') {
                $query->whereDate('entry_time', '<=', $request->end_date);
            }

            $logs = $query->orderBy('id', 'desc')->paginate($perPage);

            $this->logActivity('INDEX', 'VehicleLog', 'Retrieved vehicle logs listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle logs retrieved successfully',
                'data' => $logs,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve vehicle logs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created vehicle entry log (Find or Create Vehicle + Create Log).
     */
    public function store(CreateVehicleLogRequest $request)
    {
        try {
            $data = $request->validated();

            // 1. Find or register new Vehicle automatically
            $vehicle = Vehicle::firstOrCreate(
                ['vehicle_number' => strtoupper(trim($data['vehicle_number']))],
                [
                    'vehicle_type' => $data['vehicle_type'],
                    'is_active' => true,
                ]
            );

            // Log activity if vehicle was newly registered
            if ($vehicle->wasRecentlyCreated) {
                $this->logActivity('CREATE', 'Vehicle', "Auto-registered vehicle: {$vehicle->vehicle_number} on gate entry", $vehicle->toArray());
            }

            // 2. Generate unique log number
            $logNumber = 'VLOG-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            // 3. Prepare Vehicle Log data
            $logData = [
                'log_number' => $logNumber,
                'vehicle_id' => $vehicle->id,
                'log_type' => $data['log_type'],
                'direction' => 'in',
                'entry_time' => now(),
                'driver_name' => $data['driver_name'],
                'driver_phone' => $data['driver_phone'] ?? null,
                'driver_nic' => $data['driver_nic'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
                'logged_by' => auth()->id() ?? 1,
            ];

            // 4. Handle Entry Uploads
            if ($request->hasFile('entry_license_plate_image')) {
                $logData['entry_license_plate_image'] = $request->file('entry_license_plate_image')->store('vehicle_logs/entry', 'public');
            }

            if ($request->hasFile('entry_vehicle_image')) {
                $logData['entry_vehicle_image'] = $request->file('entry_vehicle_image')->store('vehicle_logs/entry', 'public');
            }

            if ($request->hasFile('entry_document')) {
                $logData['entry_document'] = $request->file('entry_document')->store('vehicle_logs/documents', 'public');
            }

            $vehicleLog = VehicleLog::create($logData);
            $vehicleLog->load(['vehicle', 'creator']);

            $this->logActivity('CREATE', 'VehicleLog', "Created vehicle log entry: {$vehicleLog->log_number} for vehicle {$vehicle->vehicle_number}", $logData);

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle entry log created successfully',
                'data' => $vehicleLog,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create vehicle log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified vehicle log.
     */
    public function show(string $id)
    {
        try {
            $vehicleLog = VehicleLog::with(['vehicle', 'creator:id,name,username,email,user_scope,branch_id,warehouse_id'])->find($id);

            if (!$vehicleLog) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle log not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'VehicleLog', "Retrieved vehicle log details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle log retrieved successfully',
                'data' => $vehicleLog,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve vehicle log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified vehicle log in storage.
     */
    public function update(UpdateVehicleLogRequest $request, string $id)
    {
        try {
            $vehicleLog = VehicleLog::find($id);

            if (!$vehicleLog) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle log not found',
                ], 404);
            }

            $data = $request->validated();

            // If vehicle_number is updated, find or create vehicle and update relation
            if (!empty($data['vehicle_number'])) {
                $vehicleNumber = strtoupper(trim($data['vehicle_number']));
                $vehicleType = $data['vehicle_type'] ?? $vehicleLog->vehicle->vehicle_type ?? 'other';

                $vehicle = Vehicle::firstOrCreate(
                    ['vehicle_number' => $vehicleNumber],
                    [
                        'vehicle_type' => $vehicleType,
                        'is_active' => true,
                    ]
                );

                $data['vehicle_id'] = $vehicle->id;
            }

            // Handle Entry File Upload updates
            if ($request->hasFile('entry_license_plate_image')) {
                if ($vehicleLog->entry_license_plate_image) {
                    Storage::disk('public')->delete($vehicleLog->entry_license_plate_image);
                }
                $data['entry_license_plate_image'] = $request->file('entry_license_plate_image')->store('vehicle_logs/entry', 'public');
            }

            if ($request->hasFile('entry_vehicle_image')) {
                if ($vehicleLog->entry_vehicle_image) {
                    Storage::disk('public')->delete($vehicleLog->entry_vehicle_image);
                }
                $data['entry_vehicle_image'] = $request->file('entry_vehicle_image')->store('vehicle_logs/entry', 'public');
            }

            if ($request->hasFile('entry_document')) {
                if ($vehicleLog->entry_document) {
                    Storage::disk('public')->delete($vehicleLog->entry_document);
                }
                $data['entry_document'] = $request->file('entry_document')->store('vehicle_logs/documents', 'public');
            }

            // Handle Exit File Upload updates
            if ($request->hasFile('exit_license_plate_image')) {
                if ($vehicleLog->exit_license_plate_image) {
                    Storage::disk('public')->delete($vehicleLog->exit_license_plate_image);
                }
                $data['exit_license_plate_image'] = $request->file('exit_license_plate_image')->store('vehicle_logs/exit', 'public');
            }

            if ($request->hasFile('exit_vehicle_image')) {
                if ($vehicleLog->exit_vehicle_image) {
                    Storage::disk('public')->delete($vehicleLog->exit_vehicle_image);
                }
                $data['exit_vehicle_image'] = $request->file('exit_vehicle_image')->store('vehicle_logs/exit', 'public');
            }

            if ($request->hasFile('exit_document')) {
                if ($vehicleLog->exit_document) {
                    Storage::disk('public')->delete($vehicleLog->exit_document);
                }
                $data['exit_document'] = $request->file('exit_document')->store('vehicle_logs/documents', 'public');
            }

            $vehicleLog->update($data);
            $vehicleLog->load(['vehicle', 'creator:id,name,username,email,user_scope,branch_id,warehouse_id']);

            $this->logActivity('UPDATE', 'VehicleLog', "Updated vehicle log entry: {$vehicleLog->log_number}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle log updated successfully',
                'data' => $vehicleLog,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update vehicle log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Record vehicle exit log.
     */
    public function exitLog(ExitVehicleLogRequest $request, string $id)
    {
        try {
            $vehicleLog = VehicleLog::find($id);

            if (!$vehicleLog) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle log not found',
                ], 404);
            }

            if ($vehicleLog->direction === 'out' && $vehicleLog->exit_time) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle exit has already been recorded for this log.',
                ], 422);
            }

            $data = $request->validated();
            $data['direction'] = 'out';
            $data['exit_time'] = now();

            // Handle Exit Uploads
            if ($request->hasFile('exit_license_plate_image')) {
                $data['exit_license_plate_image'] = $request->file('exit_license_plate_image')->store('vehicle_logs/exit', 'public');
            }

            if ($request->hasFile('exit_vehicle_image')) {
                $data['exit_vehicle_image'] = $request->file('exit_vehicle_image')->store('vehicle_logs/exit', 'public');
            }

            if ($request->hasFile('exit_document')) {
                $data['exit_document'] = $request->file('exit_document')->store('vehicle_logs/documents', 'public');
            }

            $vehicleLog->update($data);
            $vehicleLog->load(['vehicle', 'creator']);

            $this->logActivity('EXIT', 'VehicleLog', "Recorded exit for vehicle log: {$vehicleLog->log_number}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle exit recorded successfully',
                'data' => $vehicleLog,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record vehicle exit',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified vehicle log from storage.
     */
    public function destroy(string $id)
    {
        try {
            $vehicleLog = VehicleLog::find($id);

            if (!$vehicleLog) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle log not found',
                ], 404);
            }

            $logNumber = $vehicleLog->log_number;

            // Delete associated stored files if present
            $filesToDelete = [
                $vehicleLog->entry_license_plate_image,
                $vehicleLog->entry_vehicle_image,
                $vehicleLog->entry_document,
                $vehicleLog->exit_license_plate_image,
                $vehicleLog->exit_vehicle_image,
                $vehicleLog->exit_document,
            ];

            foreach ($filesToDelete as $filePath) {
                if (!empty($filePath) && Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            $vehicleLog->delete();

            $this->logActivity('DELETE', 'VehicleLog', "Deleted vehicle log: {$logNumber}");

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle log deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete vehicle log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get logs for a specific vehicle by vehicle ID.
     */
    public function getByVehicle(Request $request, string $vehicleId)
    {
        try {
            $vehicle = Vehicle::find($vehicleId);

            if (!$vehicle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vehicle not found',
                ], 404);
            }

            $perPage = $request->get('per_page', 15);
            $logs = VehicleLog::where('vehicle_id', $vehicleId)
                ->with('creator')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Vehicle logs retrieved successfully for vehicle: ' . $vehicle->vehicle_number,
                'data' => [
                    'vehicle' => $vehicle,
                    'logs' => $logs,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve vehicle logs for specified vehicle',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
