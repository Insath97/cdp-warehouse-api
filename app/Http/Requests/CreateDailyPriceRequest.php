<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateDailyPriceRequest extends FormRequest
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
        $targetDate = $this->input('date') ?: \Carbon\Carbon::today()->toDateString();

        return [
            'date' => 'nullable|date_format:Y-m-d',
            'item_variety_id' => [
                'required',
                'integer',
                'exists:item_varieties,id',
                Rule::unique('daily_prices')->where(function ($query) use ($targetDate) {
                    return $query->where('date', $targetDate);
                }),
            ],
            'buying_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'item_variety_id.unique' => 'Today\'s price for this item variety has already been entered. You can only enter the price once per day for each variety.',
        ];
    }

    /**
     * Handle failed validation and return a structured JSON error response.
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
