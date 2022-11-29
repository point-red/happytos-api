<?php

namespace App\Http\Requests\Inventory\TransferItem;

use App\Http\Requests\ValidationRule;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Inventory\TransferItem\TransferItemItem;
use App\Model\Master\ItemUnit;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiveItemRequest extends FormRequest
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

        $rulesReceiveItem = [
            'warehouse_id' => ValidationRule::foreignKey('warehouses'),
            'from_warehouse_id' => ValidationRule::foreignKey('warehouses'),
            'transfer_item_id' => ValidationRule::foreignKey('transfer_items'),
            'notes' => 'nullable|string|max:255',
            'items' => 'required_without:services|array',
        ];

        $rulesReceiveItemItems = [
            'items.*.item_id' => ValidationRule::foreignKey('items'),
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => ValidationRule::unit(),
            'items.*.converter' => ValidationRule::converter()
        ];

        if (! empty($this->transfer_item_id)) {
            $transfer = TransferItem::findOrFail($this->transfer_item_id);
            $rulesReceiveItem['warehouse_id'] = $rulesReceiveItem['warehouse_id'].'|size:'.$transfer->to_warehouse_id;
            $rulesReceiveItem['from_warehouse_id'] = $rulesReceiveItem['from_warehouse_id'].'|size:'.$transfer->warehouse_id;

            foreach ($this->items as $key => $item) {
                if ($item['quantity'] <= 0) {
                    continue;
                }
                $unit = ItemUnit::where('item_id', $item['item_id'])->first();
                $transferItem = TransferItemItem::where('transfer_item_id', $this->transfer_item_id)
                    ->where('item_id', $item['item_id'])
                    ->first();

                $rulesKey = 'items.'.$key.'.quantity';
                $rulesValue = 'lte:'. $transferItem->quantity;

                $rulesKeyUnit = 'items.'.$key.'.unit';
                $rulesValueUnit = 'in:'. $unit->label.','.$unit->name;

                $rulesKeyBalance = 'items.'.$key.'.balance';
                $rulesValueBalance = 'numeric|size:'.($item['quantity'] + $item['stock']);

                $rulesReceiveItemItems[$rulesKey] = $rulesValue;
                $rulesReceiveItemItems[$rulesKeyUnit] = $rulesValueUnit;
                $rulesReceiveItemItems[$rulesKeyBalance] = $rulesValueBalance;
            }
        }

        return array_merge($rulesForm, $rulesReceiveItem, $rulesReceiveItemItems);
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'warehouse_id.size' => 'warehouse of "transfer item" (to_warehouse_id) is not the same with warehouse of "receive item" (warehouse_id)',
            'from_warehouse_id.size' => 'warehouse of "transfer item" (warehouse_id) is not the same with warehouse of "receive item" (from_warehouse_id)',
            'lte' => 'The quantity cannot be greater than the quantity of the transfer item.',
            'items.*.balance.size' => 'The balance value does not match.',
        ];
    }

    protected function passedValidation()
    {
        if ($this->has('notes')) {
            $this->merge(
                ['notes' => preg_replace('/\s+/', ' ', $this->notes)]
            );
        }
    }
}
