<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
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
        $invoiceId = $this->route('invoice') ?? $this->route('id');

        return [
            'invoice_number' => [
                'nullable',
                'string',
                Rule::unique('invoices', 'invoice_number')->ignore($invoiceId),
                'max:50'
            ],
            'buyer_id' => 'sometimes|required|integer|exists:buyers,id',
            'stock_dispatch_id' => 'nullable|integer|exists:stock_dispatches,id',
            'invoice_date' => 'sometimes|required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'sub_total' => 'sometimes|required|numeric|min:0',
            'discount_amount' => 'sometimes|numeric|min:0',
            'tax_amount' => 'sometimes|numeric|min:0',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'payment_status' => 'sometimes|string|in:unpaid,partially_paid,paid,void',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
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
