<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBuyerRequest;
use App\Http\Requests\UpdateBuyerRequest;
use App\Models\Buyer;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BuyerController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Buyer Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Buyer List', ['only' => ['getActiveList']]),
            new Middleware('permission:Buyer Create', ['only' => ['store']]),
            new Middleware('permission:Buyer Update', ['only' => ['update']]),
            new Middleware('permission:Buyer Delete', ['only' => ['destroy']]),
            new Middleware('permission:Buyer Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of buyers.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Buyer::with(['country:id,name,code', 'district:id,name,code']);

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $buyers = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Buyers retrieved successfully',
                'data' => $buyers,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve buyers',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created buyer in storage.
     */
    public function store(CreateBuyerRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id() ?? 1;
            
            $buyer = Buyer::create($data);

            $this->logActivity('CREATE', 'Buyer', "Created buyer: {$buyer->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Buyer created successfully',
                'data' => $buyer,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create buyer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified buyer.
     */
    public function show(string $id)
    {
        try {
            $buyer = Buyer::with(['country', 'district'])->find($id);

            if (! $buyer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Buyer retrieved successfully',
                'data' => $buyer,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve buyer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified buyer in storage.
     */
    public function update(UpdateBuyerRequest $request, string $id)
    {
        try {
            $buyer = Buyer::find($id);

            if (! $buyer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer not found',
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->id() ?? 1;
            
            $buyer->update($data);

            $this->logActivity('UPDATE', 'Buyer', "Updated buyer: {$buyer->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Buyer updated successfully',
                'data' => $buyer,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update buyer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified buyer from storage.
     */
    public function destroy(string $id)
    {
        try {
            $buyer = Buyer::find($id);

            if (! $buyer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer not found',
                ], 404);
            }

            // Prevent deletion if buyer is associated with dispatches/invoices
            if ($buyer->dispatches()->exists() || $buyer->invoices()->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete buyer as they are linked to stock dispatches or invoices.',
                ], 422);
            }

            $buyerName = $buyer->name;
            $buyer->delete();

            $this->logActivity('DELETE', 'Buyer', "Deleted buyer: {$buyerName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Buyer deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete buyer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active buyers (lightweight list for dropdowns).
     */
    public function getActiveList()
    {
        try {
            $buyers = Buyer::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active buyers retrieved successfully',
                'data' => $buyers,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active buyers',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the status of the buyer.
     */
    public function toggleStatus(string $id)
    {
        try {
            $buyer = Buyer::find($id);

            if (!$buyer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer not found'
                ], 404);
            }

            $buyer->is_active = !$buyer->is_active;
            $buyer->updated_by = auth()->id() ?? 1;
            $buyer->save();

            $this->logActivity('TOGGLE_STATUS', 'Buyer', "Toggled buyer status: {$buyer->name} (" . ($buyer->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Buyer status updated successfully',
                'data' => [
                    'id' => $buyer->id,
                    'is_active' => $buyer->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle buyer status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
