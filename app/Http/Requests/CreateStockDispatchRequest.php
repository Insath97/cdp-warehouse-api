<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateStockDispatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'buyer_id' => 'required|integer|exists:buyers,id',
            'dispatch_type' => 'required|string|in:sales,customer_delivery,processing,transfer',
            'dispatch_date' => 'required|date',
            'delivery_note_reference' => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'vehicle_log_id' => 'nullable|integer|exists:vehicle_logs,id',
            'notes' => 'nullable|string',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'branch_id' => 'sometimes|integer|exists:branches,id',
            
            // Dispatch Items validation (supports stock_bag_id, barcode_code, qr_code, or bag_code)
            'items' => 'required|array|min:1',
            'items.*.stock_bag_id' => 'nullable|integer|exists:stock_bags,id',
            'items.*.barcode_code' => 'nullable|string|max:100',
            'items.*.qr_code'      => 'nullable|string|max:100',
            'items.*.bag_code'     => 'nullable|string|max:100',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            
            // Nested Invoice validation (optional)
            'invoice' => 'nullable|array',
            'invoice.invoice_number' => 'nullable|string|unique:invoices,invoice_number|max:50',
            'invoice.discount_amount' => 'sometimes|numeric|min:0',
            'invoice.tax_amount' => 'sometimes|numeric|min:0',
            'invoice.payment_method' => 'nullable|string|max:50',
            'invoice.notes' => 'nullable|string',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (is_array($items)) {
                $scannedIdentifiers = [];
                foreach ($items as $index => $item) {
                    $hasIdentifier = !empty($item['stock_bag_id']) ||
                                     !empty($item['barcode_code']) ||
                                     !empty($item['qr_code']) ||
                                     !empty($item['bag_code']);

                    if (!$hasIdentifier) {
                        $validator->errors()->add("items.{$index}", "Item at index {$index} must have a valid stock_bag_id, barcode_code, qr_code, or bag_code.");
                        continue;
                    }

                    // Check duplicate scan
                    $identifier = $item['stock_bag_id'] ?? $item['barcode_code'] ?? $item['qr_code'] ?? $item['bag_code'];
                    if (in_array($identifier, $scannedIdentifiers)) {
                        $validator->errors()->add("items.{$index}", "The bag identifier '{$identifier}' is duplicated in the dispatch items list.");
                    } else {
                        $scannedIdentifiers[] = $identifier;
                    }
                }
            }
        });
    }

    /**
     * Handle failed validation and return a JSON error response.
     */
    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();

        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return [
                'field' => $field,
                'messages' => $messages,
            ];
        })->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
