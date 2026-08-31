<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetDailyPriceReportRequest extends FormRequest
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
            'group_by' => 'sometimes|string|in:daily,monthly,yearly',
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
            'year' => 'sometimes|integer|min:2000|max:2099',
            'month' => 'sometimes|integer|min:1|max:12',
            'item_variety_id' => 'sometimes|integer|exists:item_varieties,id',
            'item_variety_ids' => 'sometimes|array',
            'item_variety_ids.*' => 'integer|exists:item_varieties,id',
            'item_type_id' => 'sometimes|integer|exists:item_types,id',
            'min_buying_price' => 'sometimes|numeric|min:0',
            'max_buying_price' => 'sometimes|numeric|min:0',
            'min_selling_price' => 'sometimes|numeric|min:0',
            'max_selling_price' => 'sometimes|numeric|min:0',
            'per_page' => 'sometimes|integer|min:1|max:500',
            'paginate' => 'sometimes|boolean',
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
            ? 'There are multiple validation errors. Please review the query parameters and correct the issues.'
            : 'There is an issue with the query parameter ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
