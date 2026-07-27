<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendSmsRequest extends FormRequest
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
            'numbers' => 'nullable|array',
            'numbers.*' => 'string',
            'phone_number' => 'nullable|string',
            'target_type' => 'nullable|string|in:all,suppliers,buyers,users,warehouse,branch',
            'target_id' => 'nullable|integer',
            'message' => 'required|string|min:2|max:1000',
            'payment_method' => 'nullable|integer|in:0,4',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasNumbers = !empty($this->input('numbers')) ||
                          !empty($this->input('phone_number')) ||
                          !empty($this->input('target_type'));

            if (!$hasNumbers) {
                $validator->errors()->add('numbers', 'Please specify a phone_number, array of numbers, or a target_type.');
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
            : 'There is an issue with the SMS input: ' . $fieldErrors->first()['messages'][0];

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
