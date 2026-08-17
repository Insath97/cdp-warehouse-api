<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePurchaseOrderRequest extends FormRequest
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
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'item_variety_id' => 'sometimes|integer|exists:item_varieties,id',
            'variety_type' => 'sometimes|string|in:wet,dry,midwet',
            'purchase_price_per_kg' => 'sometimes|numeric|min:0',
            'market_price_per_kg' => 'nullable|numeric|min:0',
            'number_of_bags' => 'sometimes|integer|min:1',
            'total_weights' => 'sometimes|numeric|min:0',
            'total_sales_price' => 'sometimes|nullable|numeric|min:0',
            'total_market_price' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $authUser = auth('api')->user();
            if ($authUser && $this->has('warehouse_id')) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $warehouseId = (int) $this->input('warehouse_id');
                    if ($warehouseId && !in_array($warehouseId, $accessibleIds)) {
                        $validator->errors()->add('warehouse_id', 'You do not have access to the selected warehouse.');
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
