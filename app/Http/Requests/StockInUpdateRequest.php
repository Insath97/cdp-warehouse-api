<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StockInUpdateRequest extends FormRequest
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
        $batchId = $this->route('stock_in');

        // Dynamically find type to apply rules
        $type = $this->input('type');
        if (empty($type) && $batchId) {
            $type = DB::table('stock_in_batches')->where('id', $batchId)->value('type');
        }
        $type = $type ?? 'direct';

        $rules = [
            'append' => 'nullable|boolean',
            'batch_number' => 'sometimes|required|string|max:50|unique:stock_in_batches,batch_number,' . $batchId,
            'type' => 'nullable|string|in:direct,supplier',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'received_date' => 'sometimes|required|date',
            'status' => 'nullable|string|in:draft,pending,received,completed,cancelled',
            'notes' => 'nullable|string',

            // Common item validations
            'items' => 'sometimes|required|array|min:1',
            'items.*.id' => 'nullable|exists:stock_in_batch_items,id',
            'items.*.item_type_id' => 'required_with:items|exists:item_types,id',
            'items.*.item_variety_id' => 'required_with:items|exists:item_varieties,id',
            'items.*.notes' => 'nullable|string',
        ];

        if ($type === 'direct') {
            $rules = array_merge($rules, [
                'supplier_id' => 'nullable',
                // Vehicle Details (Optional)
                'vehicle_number' => 'nullable|string|max:20',
                'vehicle_type' => 'nullable|required_with:vehicle_number|string|in:lorry,pickup,van,tractor,other',
                'driver_name' => 'nullable|required_with:vehicle_number|string|max:255',
                'driver_phone' => 'nullable|string|max:20',
                'driver_nic' => 'nullable|string|max:20',
                'purpose' => 'nullable|string|max:255',
                'vehicle_notes' => 'nullable|string',

                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.unit_weight' => 'nullable|numeric|min:0',

                // Bags Array nested inside items
                'items.*.bags' => 'required_with:items|array|min:1',
                'items.*.bags.*.id' => 'nullable|exists:stock_bags,id',
                'items.*.bags.*.bag_code' => 'nullable|string|exists:stock_bags,bag_code',
                'items.*.bags.*.bag_weight' => 'required_with:items|numeric|min:0',
                'items.*.bags.*.location_id' => 'nullable|string|max:100',
                'items.*.bags.*.notes' => 'nullable|string',
            ]);
        } else {
            $rules = array_merge($rules, [
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'supplier_id' => 'sometimes|required_without:purchase_order_id|nullable|exists:suppliers,id',
                'vehicle_id' => 'nullable|exists:vehicles,id',
                'vehicle_log_id' => 'nullable|exists:vehicle_logs,id',
                'gross_weight' => 'nullable|numeric|min:0',
                'tare_weight' => 'nullable|numeric|min:0',
                'net_weight' => 'nullable|numeric|min:0',

                'items.*.quantity_bags' => 'required_with:items|integer|min:1',
                'items.*.unit_weight' => 'nullable|numeric|min:0',
                'items.*.total_weight' => 'nullable|numeric|min:0',
                'items.*.unit_price' => 'nullable|numeric|min:0',
                'items.*.total_price' => 'nullable|numeric|min:0',
            ]);
        }

        return $rules;
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
