<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendSmsRequest;
use App\Models\SmsLog;
use App\Services\SmsService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SmsController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Sms Index', ['only' => ['index', 'show', 'balance']]),
            new Middleware('permission:Sms Send', ['only' => ['send']]),
        ];
    }

    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Display a listing of sent SMS logs.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query = SmsLog::with([
                'supplier:id,code,name',
                'buyer:id,code,name',
                'user:id,name,username',
                'sender:id,name,username',
            ]);

            // Search
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filters
            if ($request->filled('status')) {
                $query->byStatus($request->status);
            }

            if ($request->filled('supplier_id')) {
                $query->bySupplier((int) $request->supplier_id);
            }

            if ($request->filled('buyer_id')) {
                $query->byBuyer((int) $request->buyer_id);
            }

            if ($request->filled('sent_by')) {
                $query->bySender((int) $request->sent_by);
            }

            if ($request->filled('phone_number')) {
                $query->where('phone_number', 'like', '%' . $request->phone_number . '%');
            }

            if ($request->filled('start_date') || $request->filled('end_date')) {
                $query->dateRange($request->get('start_date'), $request->get('end_date'));
            }

            // Allowed sort columns
            $allowedSorts = ['id', 'phone_number', 'status', 'created_at'];
            if (!in_array($sortBy, $allowedSorts, true)) {
                $sortBy = 'id';
            }

            $logs = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $this->logActivity('INDEX', 'Sms', 'Retrieved SMS audit logs listing');

            return response()->json([
                'status' => 'success',
                'message' => 'SMS logs retrieved successfully',
                'data' => $logs,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve SMS logs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified SMS log (Get By ID).
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = SmsLog::with([
                'supplier:id,code,name',
                'buyer:id,code,name',
                'user:id,name,username',
                'sender:id,name,username',
            ])->find($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SMS log not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'Sms', "Retrieved SMS log details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'SMS log retrieved successfully',
                'data' => $log,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve SMS log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send custom single or bulk SMS.
     */
    public function send(SendSmsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $numbers = [];

            if (!empty($validated['phone_number'])) {
                $numbers[] = $validated['phone_number'];
            }
            if (!empty($validated['numbers'])) {
                $numbers = array_merge($numbers, $validated['numbers']);
            }
            if (!empty($validated['target_type'])) {
                $targetNumbers = $this->smsService->getTargetPhoneNumbers($validated['target_type'], $validated['target_id'] ?? null);
                $numbers = array_merge($numbers, $targetNumbers);
            }

            $numbers = array_unique(array_filter($numbers));

            if (empty($numbers)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid phone numbers found for SMS dispatch.',
                ], 422);
            }

            $paymentMethod = $validated['payment_method'] ?? 0;
            $success = $this->smsService->sendSms($numbers, $validated['message'], $paymentMethod);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'SMS dispatched successfully to ' . count($numbers) . ' recipient(s).',
                    'data' => [
                        'recipients_count' => count($numbers),
                        'recipients' => array_values($numbers)
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to dispatch SMS via Dialog Gateway API.',
            ], 500);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'SMS dispatch exception',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check Dialog SMS account balance.
     */
    public function balance(Request $request): JsonResponse
    {
        try {
            $key = $request->get('key', env('DIALOG_SMS_ESMSQK'));
            if (!$key) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dialog ESMSQK key is required to query balance.',
                ], 422);
            }

            $balance = $this->smsService->getBalance($key);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'balance' => $balance,
                    'currency' => 'LKR'
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to query SMS balance',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
