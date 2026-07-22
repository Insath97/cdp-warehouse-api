<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InvoiceController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Invoice Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Invoice Create', ['only' => ['store']]),
            new Middleware('permission:Invoice Update', ['only' => ['update', 'updatePaymentStatus']]),
            new Middleware('permission:Invoice Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Invoice::with(['buyer:id,code,name', 'dispatch:id,dispatch_number']);

            // Apply search
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filter by payment status
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->byPaymentStatus($request->payment_status);
            }

            // Filter by buyer
            if ($request->has('buyer_id') && $request->buyer_id != '') {
                $query->where('buyer_id', $request->buyer_id);
            }

            $invoices = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Invoices retrieved successfully',
                'data' => $invoices,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invoices',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created invoice.
     */
    public function store(CreateInvoiceRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id() ?? 1;

            $invoice = Invoice::create($data);
            $invoice->load(['buyer', 'dispatch']);

            $this->logActivity('CREATE', 'Invoice', "Created invoice: {$invoice->invoice_number}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice created successfully',
                'data' => $invoice,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create invoice',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(string $id)
    {
        try {
            $invoice = Invoice::with(['buyer', 'dispatch'])->find($id);

            if (! $invoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice retrieved successfully',
                'data' => $invoice,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invoice',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(UpdateInvoiceRequest $request, string $id)
    {
        try {
            $invoice = Invoice::find($id);

            if (! $invoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice not found',
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->id() ?? 1;

            $invoice->update($data);
            $invoice->load(['buyer', 'dispatch']);

            $this->logActivity('UPDATE', 'Invoice', "Updated invoice: {$invoice->invoice_number}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice updated successfully',
                'data' => $invoice,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update invoice',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy(string $id)
    {
        try {
            $invoice = Invoice::find($id);

            if (! $invoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice not found',
                ], 404);
            }

            $invNum = $invoice->invoice_number;
            $invoice->delete();

            $this->logActivity('DELETE', 'Invoice', "Deleted invoice: {$invNum}");

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete invoice',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the payment status of the invoice.
     */
    public function updatePaymentStatus(Request $request, string $id)
    {
        try {
            $invoice = Invoice::find($id);

            if (! $invoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice not found',
                ], 404);
            }

            $request->validate([
                'payment_status' => 'required|string|in:unpaid,partially_paid,paid,void',
                'payment_method' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
            ]);

            $invoice->payment_status = $request->payment_status;
            if ($request->has('payment_method')) {
                $invoice->payment_method = $request->payment_method;
            }
            if ($request->has('notes')) {
                $invoice->notes = $request->notes;
            }
            $invoice->updated_by = auth()->id() ?? 1;
            $invoice->save();

            $this->logActivity('UPDATE_PAYMENT', 'Invoice', "Updated invoice payment status: {$invoice->invoice_number} to {$invoice->payment_status}");

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice payment status updated successfully',
                'data' => $invoice,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update invoice payment status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
