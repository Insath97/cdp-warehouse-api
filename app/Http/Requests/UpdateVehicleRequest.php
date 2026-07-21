<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVehicleRequest extends FormRequest
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
        $id = $this->route('vehicle');
        return [
            'vehicle_number' => 'sometimes|string|max:20|unique:vehicles,vehicle_number,' . $id,
            'driver_name' => 'nullable|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
            'driver_nic' => 'nullable|string|max:20',
            'vehicle_type' => 'sometimes|string|in:lorry,pickup,van,tractor,other',
            'ownership_type' => 'sometimes|string|in:own,supplier,third_party',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'availability_status' => 'sometimes|string|in:available,in_transit,maintenance,out_of_service',
            'tare_weight' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
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
