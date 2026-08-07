<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateStockInBatchRequest extends FormRequest
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
        $batchId = $this->route('stock_in_batch');

        return [
            'batch_number' => 'sometimes|required|string|max:50|unique:stock_in_batches,batch_number,' . $batchId,
            'type' => 'nullable|string|in:direct,supplier',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'vehicle_log_id' => 'nullable|exists:vehicle_logs,id',
            'received_date' => 'sometimes|required|date',
            'gross_weight' => 'nullable|numeric|min:0',
            'tare_weight' => 'nullable|numeric|min:0',
            'net_weight' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:draft,pending,received,completed,cancelled',
            'notes' => 'nullable|string',

            // Items array validation
            'items' => 'sometimes|required|array|min:1',
            'items.*.id' => 'nullable|exists:stock_in_batch_items,id',
            'items.*.item_type_id' => 'required_with:items|exists:item_types,id',
            'items.*.item_variety_id' => 'required_with:items|exists:item_varieties,id',
            'items.*.quantity_bags' => 'required_with:items|integer|min:1',
            'items.*.unit_weight' => 'nullable|numeric|min:0',
            'items.*.total_weight' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ];
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
