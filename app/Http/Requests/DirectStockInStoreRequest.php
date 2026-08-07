<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DirectStockInStoreRequest extends FormRequest
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
            // Vehicle Details (Optional)
            'vehicle_number' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|required_with:vehicle_number|string|in:lorry,pickup,van,tractor,other',
            'driver_name' => 'nullable|required_with:vehicle_number|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
            'driver_nic' => 'nullable|string|max:20',
            'purpose' => 'nullable|string|max:255',
            'vehicle_notes' => 'nullable|string',

            // Batch Details
            'warehouse_id' => 'required|exists:warehouses,id',
            'received_date' => 'required|date',
            'status' => 'nullable|string|in:received,pending,completed',
            'notes' => 'nullable|string',

            // Items Array
            'items' => 'required|array|min:1',
            'items.*.item_type_id' => 'required|exists:item_types,id',
            'items.*.item_variety_id' => 'required|exists:item_varieties,id',
            'items.*.unit_weight' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',

            // Bags Array nested inside items
            'items.*.bags' => 'required|array|min:1',
            'items.*.bags.*.bag_weight' => 'required|numeric|min:0',
            'items.*.bags.*.location_id' => 'nullable|string|max:100',
            'items.*.bags.*.notes' => 'nullable|string',
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
