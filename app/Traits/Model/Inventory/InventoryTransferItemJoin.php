<?php

namespace App\Traits\Model\Inventory;

use App\Model\Form;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Inventory\TransferItem\ReceiveItemItem;
use App\Model\Master\Item;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Inventory\TransferItem\TransferItemItem;
use App\Model\UserActivity;

trait InventoryTransferItemJoin
{
    public static function joins($query, $joins)
    {
        $joins = explode(',', $joins);

        if (! $joins) {
            return $query;
        }

        if (in_array('form', $joins)) {
            $query = $query->join(Form::getTableName().' as '.Form::$alias, function ($q) {
                $q->on(Form::$alias.'.formable_id', '=', TransferItem::$alias.'.id')
                    ->where(Form::$alias.'.formable_type', TransferItem::$morphName);
            });
        }

        if (in_array('items', $joins)) {
            $query = $query->leftjoin(TransferItemItem::getTableName().' as '.TransferItemItem::$alias,
                TransferItemItem::$alias.'.transfer_item_id', '=', TransferItem::$alias.'.id');
            if (in_array('item', $joins)) {
                $query = $query->leftjoin(Item::getTableName().' as '.Item::$alias,
                    Item::$alias.'.id', '=', TransferItemItem::$alias.'.item_id');
            }
        }

        if (in_array('receive_item', $joins)) {
            $query = $query->leftjoin(ReceiveItem::getTableName().' as '.ReceiveItem::$alias,
                ReceiveItem::$alias.'.transfer_item_id', '=', TransferItem::$alias.'.id');
            if (in_array('receive_item_items', $joins)) {
                $query = $query->leftjoin(ReceiveItemItem::getTableName().' as '.ReceiveItemItem::$alias,
                    ReceiveItemItem::$alias.'.receive_item_id', '=', ReceiveItem::$alias.'.id');
            }
        }

        return $query;
    }
}
