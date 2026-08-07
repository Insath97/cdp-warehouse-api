<?php

namespace App\Services;

use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\SmsLog;
use App\Models\StockDispatch;
use App\Models\StockInBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Traits\ActivityLogTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsService
{
    use ActivityLogTrait;

    protected $baseUrl;
    protected $sendSmsUrl;
    protected $username;
    protected $password;
    protected $mask;

    public function __construct()
    {
        $this->baseUrl = config('services.dialog_sms.url', env('DIALOG_SMS_URL', 'https://esms.dialog.lk'));
        $this->sendSmsUrl = 'https://e-sms.dialog.lk/api/v2/sms';
        $this->username = config('services.dialog_sms.username', env('DIALOG_SMS_USERNAME'));
        $this->password = config('services.dialog_sms.password', env('DIALOG_SMS_PASSWORD'));
        $this->mask = config('services.dialog_sms.mask', env('DIALOG_SMS_MASK', 'CDP EMPIRE'));
    }

    /**
     * Get bearer access token from Dialog API v2
     */
    private function getAccessToken(): ?string
    {
        if (Cache::has('dialog_sms_token')) {
            return Cache::get('dialog_sms_token');
        }

        try {
            $response = Http::post($this->baseUrl . '/api/v2/user/login', [
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success') {
                    $expirationSeconds = $data['expiration'] ?? 43200;
                    Cache::put('dialog_sms_token', $data['token'], now()->addSeconds($expirationSeconds));

                    $this->logActivity('SMS_TOKEN_GENERATE', 'Sms', 'Dialog SMS Token Generated Successfully');

                    return $data['token'];
                }
            }

            $this->logActivity('SMS_TOKEN_FAILED', 'Sms', 'Failed to get Dialog SMS Token', ['response' => $response->body()], 'error');
            return null;

        } catch (\Throwable $th) {
            $this->logActivity('SMS_TOKEN_ERROR', 'Sms', 'Dialog SMS Token Error: ' . $th->getMessage(), null, 'error');
            return null;
        }
    }

    /**
     * Send Password Reset OTP code via SMS.
     */
    public function sendOtpSms(string $phoneNumber, string $otpCode): bool
    {
        $appName = config('app.name', 'CDP Warehouse');
        $message = "{$appName}: Your password reset verification code is: {$otpCode}. Valid for 10 minutes. Do not share this code.";
        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Send SMS via Dialog Gateway and record SmsLog
     */
    public function sendSms($numbers, string $message, int $paymentMethod = 0): bool
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                $this->logActivity('SMS_SEND_ERROR', 'Sms', 'Cannot send SMS: No valid access token', null, 'error');
                return false;
            }

            $numbers = is_array($numbers) ? $numbers : [$numbers];
            $formattedNumbers = [];

            foreach ($numbers as $number) {
                $formattedNumbers[] = [
                    'mobile' => $this->formatNumber($number)
                ];
            }

            $numberList = array_map(function ($item) {
                return $item['mobile'];
            }, $formattedNumbers);

            // Lookup entity IDs for recipient numbers (Suppliers, Buyers, Users)
            $queryNumbers = [];
            foreach ($numberList as $num) {
                $queryNumbers[] = $num;
                $queryNumbers[] = '0' . $num;
                $queryNumbers[] = '94' . $num;
                $queryNumbers[] = '+94' . $num;
            }
            $queryNumbers = array_unique($queryNumbers);

            $supplierMap = Supplier::whereIn('phone_primary', $queryNumbers)
                ->orWhereIn('phone_secondary', $queryNumbers)
                ->pluck('id', 'phone_primary')->toArray();

            $buyerMap = Buyer::whereIn('phone_primary', $queryNumbers)
                ->orWhereIn('phone_secondary', $queryNumbers)
                ->pluck('id', 'phone_primary')->toArray();

            $transactionId = time() . rand(100, 999);

            $payload = [
                'msisdn' => $formattedNumbers,
                'sourceAddress' => $this->mask,
                'message' => $message,
                'transaction_id' => $transactionId,
                'payment_method' => $paymentMethod,
            ];

            $this->logActivity('SENDING_SMS', 'Sms', 'Sending SMS via Dialog API', [
                'recipients' => count($formattedNumbers),
                'transaction_id' => $transactionId
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->sendSmsUrl, $payload);

            $success = false;
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 'success') {
                    $this->logActivity('SMS_SEND_SUCCESS', 'Sms', "SMS Sent Successfully to " . count($formattedNumbers) . " recipient(s)", [
                        'campaign_id' => $data['data']['campaignId'] ?? null,
                        'transaction_id' => $transactionId
                    ], 'info');
                    $success = true;
                }
            }

            if (!$success) {
                $this->logActivity('SMS_SEND_FAILED', 'Sms', 'SMS API Error Response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'transaction_id' => $transactionId
                ], 'error');
            }

            // Write SmsLog records
            $sentById = auth('api')->id() ?? auth()->id();
            foreach ($numberList as $num) {
                $supplierId = $supplierMap[$num] ?? $supplierMap['0' . $num] ?? $supplierMap['94' . $num] ?? null;
                $buyerId = $buyerMap[$num] ?? $buyerMap['0' . $num] ?? $buyerMap['94' . $num] ?? null;

                SmsLog::create([
                    'supplier_id' => $supplierId,
                    'buyer_id' => $buyerId,
                    'phone_number' => $num,
                    'message' => $message,
                    'status' => $success ? 'success' : 'failed',
                    'transaction_id' => $transactionId,
                    'sent_by' => $sentById,
                ]);
            }

            return $success;

        } catch (\Throwable $th) {
            $this->logActivity('SMS_SEND_EXCEPTION', 'Sms', 'SMS Service Exception: ' . $th->getMessage(), null, 'error');
            return false;
        }
    }

    /**
     * Send Stock Dispatch Gate Pass SMS notification
     */
    public function sendDispatchGatePassSms(StockDispatch $dispatch): bool
    {
        $dispatch->load(['buyer', 'warehouse', 'vehicle']);
        $buyerPhone = $dispatch->buyer->phone_primary ?? null;

        if (!$buyerPhone) {
            return false;
        }

        $msg = "CDP Warehouse Gate Pass Issued:\n";
        $msg .= "Dispatch Ref: " . ($dispatch->delivery_note_reference ?? "DSP-{$dispatch->id}") . "\n";
        $msg .= "Buyer: " . ($dispatch->buyer->name ?? 'Valued Customer') . "\n";
        $msg .= "Vehicle: " . ($dispatch->vehicle->vehicle_number ?? 'N/A') . "\n";
        $msg .= "Bags: {$dispatch->total_bags} | Weight: {$dispatch->total_weight} KG\n";
        $msg .= "Date: " . Carbon::parse($dispatch->dispatch_date)->format('Y-m-d');

        return $this->sendSms($buyerPhone, $msg);
    }

    /**
     * Send GRN Stock Batch Received SMS notification to Supplier
     */
    public function sendBatchReceivedSms(StockInBatch $batch): bool
    {
        $batch->load(['supplier', 'warehouse']);
        $supplierPhone = $batch->supplier->phone_primary ?? null;

        if (!$supplierPhone) {
            return false;
        }

        $msg = "CDP Warehouse Stock In Receipt:\n";
        $msg .= "Batch: {$batch->batch_number}\n";
        $msg .= "Bags: {$batch->total_bags} | Net Weight: {$batch->net_weight} KG\n";
        $msg .= "Supplier: {$batch->supplier->name}\n";
        $msg .= "Received: " . Carbon::parse($batch->received_date)->format('Y-m-d');

        return $this->sendSms($supplierPhone, $msg);
    }

    /**
     * Send Invoice Notification SMS to Buyer
     */
    public function sendInvoiceSms(Invoice $invoice): bool
    {
        $invoice->load('buyer');
        $buyerPhone = $invoice->buyer->phone_primary ?? null;

        if (!$buyerPhone) {
            return false;
        }

        $msg = "CDP Warehouse Invoice Notice:\n";
        $msg .= "Invoice #: {$invoice->invoice_number}\n";
        $msg .= "Total Amount: LKR " . number_format($invoice->total_amount, 2) . "\n";
        $msg .= "Status: " . strtoupper($invoice->payment_status) . "\n";
        $msg .= "Thank you for doing business with CDP Warehouse.";

        return $this->sendSms($buyerPhone, $msg);
    }

    /**
     * Get Dialog SMS Balance
     */
    public function getBalance(string $esmsqk): ?float
    {
        try {
            $response = Http::get($this->baseUrl . '/api/v1/message-via-url/check/balance', [
                'esmsqk' => $esmsqk
            ]);

            if ($response->successful()) {
                $parts = explode('|', $response->body());
                if ($parts[0] == 1) {
                    return floatval($parts[1]);
                }
            }
            return null;
        } catch (\Throwable $th) {
            Log::error('Get Balance Error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Format phone numbers to 9-digit format (7XXXXXXXX)
     */
    protected function formatNumber(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (str_starts_with($number, '94')) {
            $number = substr($number, 2);
        }

        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }

        if (strlen($number) > 9) {
            $number = substr($number, -9);
        }

        return $number;
    }

    /**
     * Target phone number resolver for warehouse entities
     */
    public function getTargetPhoneNumbers(string $targetType, ?int $targetId = null): array
    {
        $numbers = [];

        if (in_array($targetType, ['all', 'suppliers'])) {
            $suppliers = Supplier::where('is_active', true)->select('phone_primary', 'phone_secondary')->get();
            foreach ($suppliers as $supplier) {
                if (!empty($supplier->phone_primary)) $numbers[] = $supplier->phone_primary;
                if (!empty($supplier->phone_secondary)) $numbers[] = $supplier->phone_secondary;
            }
        }

        if (in_array($targetType, ['all', 'buyers'])) {
            $buyers = Buyer::where('is_active', true)->select('phone_primary', 'phone_secondary')->get();
            foreach ($buyers as $buyer) {
                if (!empty($buyer->phone_primary)) $numbers[] = $buyer->phone_primary;
                if (!empty($buyer->phone_secondary)) $numbers[] = $buyer->phone_secondary;
            }
        }

        if (in_array($targetType, ['all', 'users'])) {
            $users = User::where('is_active', true)->select('email')->get();
            // User primary contact lookup
        }

        return array_unique(array_filter($numbers));
    }
}
