<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SaveSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::guard('api')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => 'required|array|min:1',
            'settings.*' => 'nullable|string|max:255',
        ];
    }

    /**
     * Validate that submitted setting keys already exist in the database (preventing creation of new keys from frontend).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings');
            if (!is_array($settings)) {
                return;
            }

            $allowedKeys = SystemSetting::pluck('key')->toArray();
            $invalidKeys = array_diff(array_keys($settings), $allowedKeys);

            foreach ($invalidKeys as $invalidKey) {
                $validator->errors()->add(
                    'settings.' . $invalidKey,
                    "The setting key '{$invalidKey}' is invalid. New setting keys cannot be created from the frontend."
                );
            }
        });
    }

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
            'status' => 'error',
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
