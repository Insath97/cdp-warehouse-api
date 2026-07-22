<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\BarcodeToken;

class VerifyBarcodeTokenRequest extends FormRequest
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
            'token_code' => 'required|string|size:13|regex:/^\d{13}$/',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tokenCode = $this->input('token_code');
            if ($tokenCode && strlen($tokenCode) === 13 && ctype_digit($tokenCode)) {
                $base = substr($tokenCode, 0, 12);
                $expectedCheck = BarcodeToken::calculateEanCheckDigit($base);
                $actualCheck = (int) $tokenCode[12];
                if ($expectedCheck !== $actualCheck) {
                    $validator->errors()->add('token_code', 'The scanned code is invalid (EAN-13 checksum verification failed).');
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
