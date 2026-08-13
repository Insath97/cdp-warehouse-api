<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => 'required_without:supplier|nullable|integer|exists:suppliers,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'item_variety_id' => 'required|integer|exists:item_varieties,id',
            'variety_type' => 'required|string|in:wet,dry,midwet',
            'purchase_price_per_kg' => 'required|numeric|min:0',
            'market_price_per_kg' => 'nullable|numeric|min:0',
            'number_of_bags' => 'required|integer|min:1',
            'total_weights' => 'required|numeric|min:0',
            'total_sales_price' => 'sometimes|nullable|numeric|min:0',
            'total_market_price' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'nullable|string',

            // Nested supplier creation fields
            'supplier' => 'required_without:supplier_id|nullable|array',
            'supplier.code' => 'required_with:supplier|string|unique:suppliers,code|max:50',
            'supplier.name' => 'required_with:supplier|string|max:255',
            'supplier.phone_primary' => 'required_with:supplier|string|max:20',
            'supplier.phone_secondary' => 'nullable|string|max:20',
            'supplier.email' => 'nullable|email|max:255',
            'supplier.address_line1' => 'required_with:supplier|string|max:255',
            'supplier.address_line2' => 'nullable|string|max:255',
            'supplier.city' => 'required_with:supplier|string|max:100',
            'supplier.country_id' => 'required_with:supplier|integer|exists:countries,id',
            'supplier.district_id' => 'nullable|integer|exists:districts,id',
            'supplier.id_type' => 'nullable|string|in:nic,driving,passport,other',
            'supplier.id_number' => 'nullable|required_with:supplier.id_type|string|max:50',
            'supplier.payment_terms' => 'sometimes|string|in:immediate,net_7,net_15,net_30',
            'supplier.notes' => 'nullable|string',

            // Nested supplier bank accounts
            'supplier.bank_accounts' => 'nullable|array',
            'supplier.bank_accounts.*.bank_id' => 'required|integer|exists:banks,id',
            'supplier.bank_accounts.*.bank_account_no' => 'required|string|max:50',
            'supplier.bank_accounts.*.bank_branch' => 'nullable|string|max:100',
            'supplier.bank_accounts.*.account_type' => 'sometimes|string|in:savings,current,fixed_deposit',
            'supplier.bank_accounts.*.is_primary' => 'sometimes|boolean',
            'supplier.bank_accounts.*.is_active' => 'sometimes|boolean',
            'supplier.bank_accounts.*.notes' => 'nullable|string',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Apply warehouse accessibility check based on user scope
            $authUser = auth('api')->user();
            if ($authUser) {
                $accessibleIds = $authUser->getAccessibleWarehouseIds();
                if (is_array($accessibleIds)) {
                    $warehouseId = (int) $this->input('warehouse_id');
                    if ($warehouseId && !in_array($warehouseId, $accessibleIds)) {
                        $validator->errors()->add('warehouse_id', 'You do not have access to the selected warehouse.');
                    }
                }
            }

            // Perform nested supplier profile validation checks if present
            if ($this->has('supplier')) {
                $supplier = $this->input('supplier');
                if (is_array($supplier)) {
                    // Check ID type & ID number uniqueness combination
                    $idType = $supplier['id_type'] ?? null;
                    $idNumber = $supplier['id_number'] ?? null;
                    if ($idType && $idNumber) {
                        $exists = \DB::table('suppliers')
                            ->where('id_type', $idType)
                            ->where('id_number', $idNumber)
                            ->exists();
                        if ($exists) {
                            $validator->errors()->add('supplier.id_number', 'The combination of ID type and ID number already exists.');
                        }
                    }

                    // Check bank account primary constraint
                    $bankAccounts = $supplier['bank_accounts'] ?? [];
                    if (is_array($bankAccounts)) {
                        $primaryCount = 0;
                        foreach ($bankAccounts as $acct) {
                            if (isset($acct['is_primary']) && filter_var($acct['is_primary'], FILTER_VALIDATE_BOOLEAN)) {
                                $primaryCount++;
                            }
                        }
                        if ($primaryCount > 1) {
                            $validator->errors()->add('supplier.bank_accounts', 'A supplier can only have at most one primary bank account.');
                        }
                    }
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
