<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDistrictRequest;
use App\Http\Requests\UpdateDistrictRequest;
use App\Models\District;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DistrictController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:District Index', ['only' => ['index', 'show', 'getDistrictList']]),
            new Middleware('permission:District Create', ['only' => ['store']]),
            new Middleware('permission:District Update', ['only' => ['update']]),
            new Middleware('permission:District Delete', ['only' => ['destroy']]),
            new Middleware('permission:District Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of districts.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = District::with('province.country');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('province_id')) {
                $query->where('province_id', $request->province_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $districts = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Districts retrieved successfully',
                'data' => $districts,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve districts',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created district in storage.
     */
    public function store(CreateDistrictRequest $request)
    {
        try {
            $data = $request->validated();
            $district = District::create($data);

            $this->logActivity('CREATE', 'District', "Created district: {$district->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'District created successfully',
                'data' => $district->load('province.country'),
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create district',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified district.
     */
    public function show(string $id)
    {
        try {
            $district = District::with('province.country')->find($id);

            if (! $district) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'District not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'District retrieved successfully',
                'data' => $district,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve district',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified district in storage.
     */
    public function update(UpdateDistrictRequest $request, string $id)
    {
        try {
            $district = District::find($id);

            if (! $district) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'District not found',
                ], 404);
            }

            $data = $request->validated();
            $district->update($data);

            $this->logActivity('UPDATE', 'District', "Updated district: {$district->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'District updated successfully',
                'data' => $district->load('province.country'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update district',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified district from storage.
     */
    public function destroy(string $id)
    {
        try {
            $district = District::find($id);

            if (! $district) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'District not found',
                ], 404);
            }

            $districtName = $district->name;
            $district->delete();

            $this->logActivity('DELETE', 'District', "Deleted district: {$districtName}");

            return response()->json([
                'status' => 'success',
                'message' => 'District deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete district',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of districts (lightweight list).
     */
    public function getDistrictList(Request $request)
    {
        try {
            $query = District::active();

            if ($request->has('province_id')) {
                $query->where('province_id', $request->province_id);
            }

            $districts = $query->orderBy('name', 'asc')->get(['id', 'name', 'code', 'province_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Districts retrieved successfully',
                'data' => $districts,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve districts',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $district = District::find($id);

            if (!$district) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'District not found'
                ], 404);
            }

            $district->is_active = !$district->is_active;
            $district->save();

            $this->logActivity('TOGGLE_STATUS', 'District', "Toggled district status: {$district->name} (" . ($district->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'District status updated successfully',
                'data' => [
                    'id' => $district->id,
                    'is_active' => $district->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle district status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
