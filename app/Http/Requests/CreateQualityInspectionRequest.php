<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateQualityInspectionRequest extends FormRequest
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
            'stock_in_batch_id' => 'nullable|exists:stock_in_batches,id',
            'stock_bag_id'      => 'nullable|exists:stock_bags,id',
            'barcode_code'      => 'nullable|string|max:100',
            'qr_code'           => 'nullable|string|max:100',
            'bag_code'          => 'nullable|string|max:100',
            'item_variety_id'   => 'nullable|exists:item_varieties,id',
            'item_type_id'      => 'nullable|exists:item_types,id',
            'original_weight'   => 'nullable|numeric|min:0',
            'current_weight'    => 'nullable|numeric|min:0',
            'moisture_percentage' => 'nullable|numeric|min:0|max:100',
            'grade'             => 'nullable|string|in:A,B,C,reject',
            'broken_percentage' => 'nullable|numeric|min:0|max:100',
            'colour_quality'    => 'nullable|string|in:good,acceptable,poor',
            'inspection_result' => 'nullable|string|in:approved,conditional,rejected,passed,failed',
            'remarks'           => 'nullable|string',
            'inspected_at'      => 'nullable|date',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasBagOrBatch = $this->filled('stock_bag_id') ||
                             $this->filled('barcode_code') ||
                             $this->filled('qr_code') ||
                             $this->filled('bag_code') ||
                             $this->filled('stock_in_batch_id');

            if (!$hasBagOrBatch) {
                $validator->errors()->add('stock_bag_id', 'Please provide a valid bag ID, scanned Barcode, QR code, bag code, or batch ID.');
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
