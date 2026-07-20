<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateItemVarietyRequest extends FormRequest
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
        $varietyId = $this->route('item_variety') ?? $this->route('id');

        return [
            'item_type_id' => 'required|integer|exists:item_types,id',
            'name' => [
                'required',
                'string',
                'max:255',
                // Unique name under the same item type category, excluding current record
                Rule::unique('item_varieties')->where(function ($query) {
                    return $query->where('item_type_id', $this->item_type_id);
                })->ignore($varietyId),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                // Unique code, excluding current record
                Rule::unique('item_varieties', 'code')->ignore($varietyId),
            ],
            'description' => 'nullable|string',
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
