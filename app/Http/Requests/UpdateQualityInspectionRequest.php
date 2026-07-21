<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateQualityInspectionRequest extends FormRequest
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
            'original_weight' => 'nullable|numeric|min:0',
            'current_weight' => 'nullable|numeric|min:0',
            'moisture_percentage' => 'nullable|numeric|min:0|max:100',
            'grade' => 'nullable|string|in:A,B,C,reject',
            'broken_percentage' => 'nullable|numeric|min:0|max:100',
            'colour_quality' => 'nullable|string|in:good,acceptable,poor',
            'inspection_result' => 'nullable|string|in:approved,conditional,rejected',
            'remarks' => 'nullable|string',
            'inspected_at' => 'nullable|date',
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
