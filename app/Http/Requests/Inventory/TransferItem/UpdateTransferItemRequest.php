<?php

namespace App\Http\Requests\Inventory\TransferItem;

use App\Http\Requests\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransferItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (! tenant(auth()->user()->id)->hasPermissionTo('update transfer item')) {
            return false;
        }
        
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rulesForm = ValidationRule::form();

        $rulesTransferItem = [
            'warehouse_id' => ValidationRule::foreignKey('warehouses'),
            'to_warehouse_id' => ValidationRule::foreignKey('warehouses'),
            'driver' => 'required|string',
            'notes' => 'nullable|string|max:255',

            'items' => 'required_without:services|array',
        ];

        $rulesTransferItemItems = [
            'items.*.item_id' => ValidationRule::foreignKey('items'),
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => ValidationRule::quantity(),
            'items.*.unit' => ValidationRule::unit(),
            'items.*.converter' => ValidationRule::converter()
        ];

        foreach ($this->items as $key => $item) {
            if ($item['quantity'] <= 0) {
                continue;
            }

            $rulesKey = 'items.'.$key.'.quantity';
            $rulesValue = 'lte:'. $item['stock'];

            $rulesTransferItemItems[$rulesKey] = $rulesValue;
        }

        return array_merge($rulesForm, $rulesTransferItem, $rulesTransferItemItems);
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'lte' => 'The quantity cannot be greater than stock warehouse',
        ];
    }
}
