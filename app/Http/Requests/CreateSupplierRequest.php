<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSupplierRequest extends FormRequest
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
            'code' => 'required|string|unique:suppliers,code|max:50',
            'name' => 'required|string|max:255',
            'phone_primary' => 'required|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'country_id' => 'required|integer|exists:countries,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'id_type' => 'nullable|string|in:nic,driving,passport,other',
            'id_number' => 'nullable|required_with:id_type|string|max:50',
            'payment_terms' => 'sometimes|string|in:immediate,net_7,net_15,net_30',
            'outstanding_balance' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            
            // Nested Bank accounts validation
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_id' => 'required|integer|exists:banks,id',
            'bank_accounts.*.bank_account_no' => 'required|string|max:50',
            'bank_accounts.*.bank_branch' => 'nullable|string|max:100',
            'bank_accounts.*.account_type' => 'sometimes|string|in:savings,current,fixed_deposit',
            'bank_accounts.*.is_primary' => 'sometimes|boolean',
            'bank_accounts.*.is_active' => 'sometimes|boolean',
            'bank_accounts.*.notes' => 'nullable|string',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $bankAccounts = $this->input('bank_accounts', []);
            if (is_array($bankAccounts)) {
                $primaryCount = 0;
                foreach ($bankAccounts as $acct) {
                    if (isset($acct['is_primary']) && filter_var($acct['is_primary'], FILTER_VALIDATE_BOOLEAN)) {
                        $primaryCount++;
                    }
                }
                if ($primaryCount > 1) {
                    $validator->errors()->add('bank_accounts', 'A supplier can only have at most one primary bank account.');
                }
            }

            $countryId = $this->input('country_id');
            if ($countryId) {
                $country = \DB::table('countries')->find($countryId);
                if ($country && (strtoupper($country->code) === 'LK' || strtoupper($country->code) === 'SL' || strtolower($country->name) === 'sri lanka')) {
                    if (empty($this->input('district_id'))) {
                        $validator->errors()->add('district_id', 'The district field is required for Sri Lankan suppliers.');
                    }
                }
            }

            $idType = $this->input('id_type');
            $idNumber = $this->input('id_number');
            if ($idType && $idNumber) {
                $exists = \DB::table('suppliers')
                    ->where('id_type', $idType)
                    ->where('id_number', $idNumber)
                    ->exists();
                if ($exists) {
                    $validator->errors()->add('id_number', 'The combination of ID type and ID number already exists.');
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
