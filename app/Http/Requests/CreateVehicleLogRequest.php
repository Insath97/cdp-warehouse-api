<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateVehicleLogRequest extends FormRequest
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
            'vehicle_number' => 'required|string|max:20',
            'vehicle_type' => 'required|string|in:lorry,pickup,van,tractor,other',
            'log_type' => 'required|string|in:stock_in,stock_out',
            'driver_name' => 'required|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
            'driver_nic' => 'nullable|string|max:20',
            'purpose' => 'nullable|string|max:255',
            'notes' => 'nullable|string',

            // Gate IN verification uploads
            'entry_license_plate_image' => 'nullable|image|max:4096',
            'entry_vehicle_image' => 'nullable|image|max:4096',
            'entry_document' => 'nullable|file|mimes:pdf,jpg,png,doc,docx|max:10240',
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
