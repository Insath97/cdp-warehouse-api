<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Supplier Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Supplier List', ['only' => ['getActiveList']]),
            new Middleware('permission:Supplier Create', ['only' => ['store']]),
            new Middleware('permission:Supplier Update', ['only' => ['update']]),
            new Middleware('permission:Supplier Delete', ['only' => ['destroy']]),
            new Middleware('permission:Supplier Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of the suppliers.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Supplier::query()->with(['bankAccounts.bank', 'district', 'country']);

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $suppliers = $query->orderBy('name', 'asc')->paginate($perPage);

            $this->logActivity('INDEX', 'Supplier', 'Retrieved suppliers listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Suppliers retrieved successfully',
                'data' => $suppliers,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve suppliers',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created supplier with optional bank details.
     */
    public function store(CreateSupplierRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $bankAccountsData = $data['bank_accounts'] ?? [];
            unset($data['bank_accounts']);

            // Create supplier profile
            $supplier = Supplier::create($data);

            // Handle bank accounts if provided
            if (!empty($bankAccountsData)) {
                // Determine if a primary bank account is explicitly set
                $hasPrimary = collect($bankAccountsData)->contains('is_primary', true);

                foreach ($bankAccountsData as $index => $acctData) {
                    // Default the first bank account to primary if none is explicitly set
                    if (!$hasPrimary && $index === 0) {
                        $acctData['is_primary'] = true;
                    }
                    $supplier->bankAccounts()->create($acctData);
                }
            }

            DB::commit();

            // Log activity
            $this->logActivity('CREATE', 'Supplier', "Created supplier: {$supplier->name} ({$supplier->code})", $request->validated());

            // Reload supplier relationships
            $supplier->load(['bankAccounts.bank', 'district', 'country']);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier created successfully',
                'data' => $supplier,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified supplier.
     */
    public function show(string $id)
    {
        try {
            $supplier = Supplier::with(['bankAccounts.bank', 'district', 'country'])->find($id);

            if (!$supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'Supplier', "Retrieved supplier details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier retrieved successfully',
                'data' => $supplier,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve supplier',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified supplier and sync bank accounts.
     */
    public function update(UpdateSupplierRequest $request, string $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();
            $bankAccountsData = $data['bank_accounts'] ?? null;
            unset($data['bank_accounts']);

            // Update supplier profile
            $supplier->update($data);

            // Sync bank accounts if array is provided
            if ($bankAccountsData !== null) {
                // 1. Delete bank accounts that are not in the new payload
                $inputIds = collect($bankAccountsData)->pluck('id')->filter()->toArray();
                $supplier->bankAccounts()->whereNotIn('id', $inputIds)->delete();

                // 2. Insert or Update accounts in payload
                $hasPrimary = collect($bankAccountsData)->contains('is_primary', true);

                foreach ($bankAccountsData as $index => $acctData) {
                    // Set primary flag if none explicitly marked
                    if (!$hasPrimary && $index === 0) {
                        $acctData['is_primary'] = true;
                    }

                    if (isset($acctData['id'])) {
                        // Update existing bank account
                        SupplierBankAccount::where('id', $acctData['id'])->update($acctData);
                    } else {
                        // Create new bank account
                        $supplier->bankAccounts()->create($acctData);
                    }
                }

                // If a primary account was defined, make sure other accounts are marked as non-primary
                $primaryAccount = $supplier->bankAccounts()->where('is_primary', true)->first();
                if ($primaryAccount) {
                    $supplier->bankAccounts()->where('id', '!=', $primaryAccount->id)->update(['is_primary' => false]);
                }
            }

            DB::commit();

            // Log activity
            $this->logActivity('UPDATE', 'Supplier', "Updated supplier: {$supplier->name} ({$supplier->code})", $request->validated());

            // Reload supplier relationships
            $supplier->load(['bankAccounts.bank', 'district', 'country']);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier updated successfully',
                'data' => $supplier,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update supplier',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::find($id);

            if (!$supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found',
                ], 404);
            }

            $supplierCode = $supplier->code;
            $supplierName = $supplier->name;
            $supplier->delete(); // Cascading delete will remove associated bank accounts

            // Log activity
            $this->logActivity('DELETE', 'Supplier', "Deleted supplier: {$supplierName} ({$supplierCode})");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete supplier',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle active status of supplier.
     */
    public function toggleStatus(string $id)
    {
        try {
            $supplier = Supplier::find($id);

            if (!$supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found',
                ], 404);
            }

            $supplier->is_active = !$supplier->is_active;
            $supplier->save();

            // Log activity
            $statusText = $supplier->is_active ? 'Active' : 'Inactive';
            $this->logActivity('TOGGLE_STATUS', 'Supplier', "Toggled supplier status: {$supplier->name} ({$statusText})");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier status updated successfully',
                'data' => [
                    'id' => $supplier->id,
                    'is_active' => $supplier->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle supplier status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a lightweight list of active suppliers for dropdowns.
     */
    public function getActiveList()
    {
        try {
            $suppliers = Supplier::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active suppliers list retrieved successfully',
                'data' => $suppliers,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active suppliers list',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
