<?php

namespace App\Traits\Model\Inventory;

use App\Model\Form;
use App\Model\Master\Item;
use App\Model\Inventory\TransferItem\TransferItemCustomer;
use App\Model\Inventory\TransferItem\TransferItemCustomerItem;

trait InventoryTransferItemCustomerJoin
{
    public static function joins($query, $joins)
    {
        $joins = explode(',', $joins);

        if (! $joins) {
            return $query;
        }
        
        if (in_array('form', $joins)) {
            $query = $query->join(Form::getTableName().' as '.Form::$alias, function ($q) {
                $q->on(Form::$alias.'.formable_id', '=', TransferItemCustomer::$alias.'.id')
                    ->where(Form::$alias.'.formable_type', TransferItemCustomer::$morphName);
            });
        }

        if (in_array('items', $joins)) {
            $query = $query->leftjoin(TransferItemCustomerItem::getTableName().' as '.TransferItemCustomerItem::$alias,
            TransferItemCustomerItem::$alias.'.transfer_item_customer_id', '=', TransferItemCustomer::$alias.'.id');
            if (in_array('item', $joins)) {
                $query = $query->leftjoin(Item::getTableName().' as '.Item::$alias,
                    Item::$alias.'.id', '=', TransferItemCustomerItem::$alias.'.item_id');
            }
        }

        return $query;
    }
}
