<?php

namespace App\Traits\Model\Inventory;

use App\Model\Form;
use App\Model\Master\Item;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Inventory\TransferItem\ReceiveItemItem;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Inventory\TransferItem\TransferItemItem;
use App\Model\Master\Warehouse;
use App\Model\UserActivity;

trait InventoryReceiveItemJoin
{
    public static function joins($query, $joins)
    {
        $joins = explode(',', $joins);

        if (! $joins) {
            return $query;
        }

        if (in_array('form', $joins)) {
            $query = $query->join(Form::getTableName().' as '.Form::$alias, function ($q) {
                $q->on(Form::$alias.'.formable_id', '=', ReceiveItem::$alias.'.id')
                    ->where(Form::$alias.'.formable_type', ReceiveItem::$morphName);
            });
        }

        if (in_array('items', $joins)) {
            $query = $query->leftjoin(ReceiveItemItem::getTableName().' as '.ReceiveItemItem::$alias,
                ReceiveItemItem::$alias.'.receive_item_id', '=', ReceiveItem::$alias.'.id');
            if (in_array('item', $joins)) {
                $query = $query->leftjoin(Item::getTableName().' as '.Item::$alias,
                    Item::$alias.'.id', '=', ReceiveItemItem::$alias.'.item_id');
            }
        }

        if (in_array('warehouse', $joins)) {
            $query = $query->leftjoin(Warehouse::getTableName().' as '.Warehouse::$alias, function ($q) {
                $q->on(Warehouse::$alias.'.id', '=', ReceiveItem::$alias.'.warehouse_id');
            });
            $query = $query->leftjoin(Warehouse::getTableName().' as from_'.Warehouse::$alias, function ($q) {
                $q->on('from_'.Warehouse::$alias.'.id', '=', ReceiveItem::$alias.'.from_warehouse_id');
            });
        }

        if (in_array('transfer_item', $joins)) {
            $query = $query->join(Form::getTableName().' as transfer_item_'.Form::$alias, function ($q) {
                $q->on('transfer_item_'.Form::$alias.'.formable_id', '=', ReceiveItem::$alias.'.transfer_item_id')
                    ->where('transfer_item_'.Form::$alias.'.formable_type', TransferItem::$morphName);
            });
            $query = $query->leftjoin(TransferItemItem::getTableName().' as '.TransferItemItem::$alias, function ($q) {
                $q->on(TransferItemItem::$alias.'.transfer_item_id', '=', ReceiveItem::$alias.'.transfer_item_id');
            });
        }

        return $query;
    }
}
