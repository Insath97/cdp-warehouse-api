<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateStockBagRequest extends FormRequest
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
            'stock_in_batch_id' => 'required|exists:stock_in_batches,id',
            'stock_in_batch_item_id' => 'nullable|exists:stock_in_batch_items,id',
            'item_type_id' => 'nullable|exists:item_types,id',
            'item_variety_id' => 'required_without:bags|exists:item_varieties,id',
            'bag_weight' => 'required_without:bags|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'bag_code' => 'nullable|string|max:100|unique:stock_bags,bag_code',
            'barcode_code' => 'nullable|string|max:100|unique:stock_bags,barcode_code',
            'qr_code' => 'nullable|string|max:255|unique:stock_bags,qr_code',
            'status' => 'nullable|string|in:in_stock,dispatched,damaged,returned',
            'location_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',

            // Support for bulk bag entry
            'bags' => 'nullable|array|min:1',
            'bags.*.stock_in_batch_item_id' => 'nullable|exists:stock_in_batch_items,id',
            'bags.*.item_type_id' => 'nullable|exists:item_types,id',
            'bags.*.item_variety_id' => 'required_with:bags|exists:item_varieties,id',
            'bags.*.bag_weight' => 'required_with:bags|numeric|min:0.01',
            'bags.*.unit_price' => 'nullable|numeric|min:0',
            'bags.*.selling_price' => 'nullable|numeric|min:0',
            'bags.*.bag_code' => 'nullable|string|max:100|unique:stock_bags,bag_code',
            'bags.*.barcode_code' => 'nullable|string|max:100|unique:stock_bags,barcode_code',
            'bags.*.qr_code' => 'nullable|string|max:255|unique:stock_bags,qr_code',
            'bags.*.location_id' => 'nullable|string|max:100',
            'bags.*.notes' => 'nullable|string',
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
