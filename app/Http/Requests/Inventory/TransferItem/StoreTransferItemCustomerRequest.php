<?php

namespace App\Http\Requests\Inventory\TransferItem;

use App\Http\Requests\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Model\Master\ItemUnit;

class StoreTransferItemCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        
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
        
        $rulesTransferItemCustomer = [
            'warehouse_id' => ValidationRule::foreignKey('warehouses'),
            'customer_id' => ValidationRule::foreignKey('customers'),
            'expedition_id' => ValidationRule::foreignKey('expeditions'),
            'plat' => 'required|string',
            'stnk' => 'required|string',
            'phone' => 'required|string',
            'notes' => 'nullable|string|max:255',

            'items' => 'required_without:services|array',
        ];

        $rulesTransferItemCustomerItems = [
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

            $unit = ItemUnit::where('item_id', $item['item_id'])->first();
            if ($unit) {
                $rulesKeyUnit = 'items.'.$key.'.unit';
                $rulesValueUnit = 'in:'. $unit->label.','.$unit->name;
    
                $rulesTransferItemCustomerItems[$rulesKeyUnit] = $rulesValueUnit;
            }
        }

        return array_merge($rulesForm, $rulesTransferItemCustomer, $rulesTransferItemCustomerItems);
    }
}
