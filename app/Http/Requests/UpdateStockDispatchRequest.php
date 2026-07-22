<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateStockDispatchRequest extends FormRequest
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
        $dispatchId = $this->route('stock_dispatch') ?? $this->route('id');
        $invoiceId = null;

        if ($dispatchId) {
            $invoiceId = \DB::table('invoices')->where('stock_dispatch_id', $dispatchId)->value('id');
        }

        return [
            'buyer_id' => 'sometimes|required|integer|exists:buyers,id',
            'dispatch_type' => 'sometimes|required|string|in:sales,customer_delivery,processing,transfer',
            'dispatch_date' => 'sometimes|required|date',
            'delivery_note_reference' => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'vehicle_log_id' => 'nullable|integer|exists:vehicle_logs,id',
            'notes' => 'nullable|string',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'branch_id' => 'sometimes|integer|exists:branches,id',
            
            // Dispatch Items validation
            'items' => 'sometimes|required|array|min:1',
            'items.*.stock_bag_id' => 'required_with:items|integer|exists:stock_bags,id',
            'items.*.selling_price' => 'required_with:items|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            
            // Nested Invoice validation
            'invoice' => 'nullable|array',
            'invoice.invoice_number' => [
                'nullable',
                'string',
                $invoiceId ? Rule::unique('invoices', 'invoice_number')->ignore($invoiceId) : 'unique:invoices,invoice_number',
                'max:50'
            ],
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
                $bagIds = [];
                foreach ($items as $index => $item) {
                    if (isset($item['stock_bag_id'])) {
                        $bagId = $item['stock_bag_id'];
                        if (in_array($bagId, $bagIds)) {
                            $validator->errors()->add("items.{$index}.stock_bag_id", "The stock bag ID {$bagId} is duplicated in the items list.");
                        } else {
                            $bagIds[] = $bagId;
                        }
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
