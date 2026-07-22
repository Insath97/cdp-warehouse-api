<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBarcodeTokenRequest;
use App\Http\Requests\VerifyBarcodeTokenRequest;
use App\Models\BarcodeTokenBatch;
use App\Models\BarcodeToken;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class BarcodeTokenController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:BarcodeToken Index', ['only' => ['index', 'show', 'verifyStatus']]),
            new Middleware('permission:BarcodeToken Create', ['only' => ['store']]),
            new Middleware('permission:BarcodeToken Verify', ['only' => ['verifyAndUse']]),
        ];
    }

    /**
     * Display a listing of barcode token batches.
     * Shows how many codes are generated, which variety, and how many are used.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = BarcodeTokenBatch::with([
                'itemType:id,code,name',
                'itemVariety:id,code,name',
                'creator:id,name,username',
            ]);

            // Apply search
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filters
            if ($request->has('token_type') && $request->token_type != '') {
                $query->where('token_type', $request->token_type);
            }

            if ($request->has('item_type_id') && $request->item_type_id != '') {
                $query->where('item_type_id', $request->item_type_id);
            }

            if ($request->has('item_variety_id') && $request->item_variety_id != '') {
                $query->where('item_variety_id', $request->item_variety_id);
            }

            $batches = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Barcode token batches retrieved successfully',
                'data' => $batches,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve barcode token batches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store bulk barcode/QR tokens under a parent batch.
     */
    public function store(CreateBarcodeTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $quantity = (int) $validated['quantity'];
            $userId = auth()->id() ?? 1;

            $batch = DB::transaction(function () use ($validated, $quantity, $userId) {
                // 1. Create the parent batch entry
                $batch = BarcodeTokenBatch::create([
                    'item_type_id' => $validated['item_type_id'] ?? null,
                    'item_variety_id' => $validated['item_variety_id'] ?? null,
                    'token_type' => $validated['token_type'],
                    'quantity_requested' => $quantity,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                // 2. Generate child token codes
                for ($i = 0; $i < $quantity; $i++) {
                    $code = BarcodeToken::generateUniqueCode();
                    BarcodeToken::create([
                        'barcode_token_batch_id' => $batch->id,
                        'token_code' => $code,
                        'status' => 'unused',
                    ]);
                }

                return $batch;
            });

            // Log activity
            $this->logActivity(
                'CREATE',
                'BarcodeTokenBatch',
                "Bulk generated batch {$batch->batch_number} containing {$quantity} {$batch->token_type} tokens",
                $request->validated()
            );

            $batch->load(['itemType', 'itemVariety', 'creator', 'tokens']);

            return response()->json([
                'status' => 'success',
                'message' => "Successfully generated batch {$batch->batch_number} with {$quantity} token(s)",
                'data' => $batch,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate barcode tokens batch',
                'error' => $th->getMessage(),
            ], 422);
        }
    }

    /**
     * Display a specific barcode token batch and list its child tokens.
     */
    public function show(string $id)
    {
        try {
            $batch = BarcodeTokenBatch::with([
                'itemType',
                'itemVariety',
                'creator',
                'tokens.validator:id,name,username',
            ])->find($id);

            if (!$batch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Barcode token batch not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Barcode token batch retrieved successfully',
                'data' => $batch,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve barcode token batch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check status of a specific code without scanning/using it.
     */
    public function verifyStatus(string $code)
    {
        try {
            $token = BarcodeToken::where('token_code', $code)->first();

            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Barcode token not found',
                    'data' => ['isValid' => false, 'status' => 'unknown']
                ], 404);
            }

            $isValid = $token->status === 'unused';

            return response()->json([
                'status' => 'success',
                'message' => "Token status is: {$token->status}",
                'data' => [
                    'isValid' => $isValid,
                    'status' => $token->status,
                    'token' => $token->load(['batch.itemType', 'batch.itemVariety'])
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve token status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify the scanned code and mark it as 'used' in the database.
     */
    public function verifyAndUse(VerifyBarcodeTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $code = $validated['token_code'];
            $userId = auth()->id() ?? 1;

            $token = BarcodeToken::where('token_code', $code)->first();

            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Barcode token not found',
                ], 404);
            }

            if ($token->status === 'used') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This token code has already been scanned and used.',
                ], 422);
            }

            if ($token->status === 'cancelled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This token code has been cancelled and cannot be used.',
                ], 422);
            }

            DB::transaction(function () use ($token, $userId) {
                $token->status = 'used';
                $token->used_at = now();
                $token->used_by = $userId;
                $token->save();
            });

            // Log activity
            $this->logActivity(
                'VERIFY_USE',
                'BarcodeToken',
                "Scanned and used token code: {$token->token_code} from batch ID: {$token->barcode_token_batch_id}"
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Token scanned and verified successfully.',
                'data' => $token->load(['batch.itemType', 'batch.itemVariety', 'validator']),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify and use token',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
