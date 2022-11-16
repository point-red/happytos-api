<?php

namespace App\Model\Inventory\TransferItem;

use App\Model\Form;
use App\Model\Master\Warehouse;
use App\Model\TransactionModel;
use App\Model\Accounting\Journal;
use App\Model\Master\Item;
use App\Model\Inventory\TransferItem\TransferItemCustomerItem;
use App\Traits\Model\Inventory\InventoryTransferItemCustomerJoin;
use App\Helpers\Inventory\InventoryHelper;
use App\Model\Master\Customer;
use App\Model\Master\Expedition;

class TransferItemCustomer extends TransactionModel
{
    use InventoryTransferItemCustomerJoin;
    
    public static $morphName = 'TransferItemCustomer';

    protected $connection = 'tenant';

    public static $alias = 'transfer_sent_customer';

    public $timestamps = false;

    public $defaultNumberPrefix = 'TICUST';

    protected $fillable = [
        'warehouse_id',
        'customer_id',
        'expedition_id',
        'plat',
        'stnk',
        'phone'
    ];

    public function form()
    {
        return $this->morphOne(Form::class, 'formable');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function expedition()
    {
        return $this->belongsTo(Expedition::class);
    }

    public function items()
    {
        return $this->hasMany(TransferItemCustomerItem::class);
    }



    public static function create($data)
    {
        $transferItemCustomer = new self;
        $transferItemCustomer->fill($data);

        $items = self::mapItems($data['items'] ?? [], $data);

        $transferItemCustomer->save();

        $transferItemCustomer->items()->saveMany($items);

        $form = new Form;
        $form->saveData($data, $transferItemCustomer);

        return $transferItemCustomer;
    }

    private static function mapItems($items, $data)
    {
        $array = [];
        
        foreach ($items as $item) {
            $itemModel = Item::find($item['item_id']);
            if ($itemModel->require_production_number || $itemModel->require_expiry_date) {
                if ($item['dna']) {
                    foreach ($item['dna'] as $dna) {
                        if ($dna['quantity'] > 0) {
                            $dnaItem = $item;
                            $dnaItem['quantity'] = $dna['quantity'];
                            $dnaItem['production_number'] = $dna['production_number'];
                            $dnaItem['expiry_date'] = $dna['expiry_date'];
                            $dnaItem['stock'] = $dna['remaining'];
                            $dnaItem['balance'] = $dna['remaining'] - $dna['quantity'];
                            array_push($array, $dnaItem);
                        }
                    }
                } else {
                    abort(422, 'DNA item cannot be empty!');
                }
            } else {
                array_push($array, $item);
            }
        }
        
        return array_map(function ($item) {
            $transferItemCustomerItem = new TransferItemCustomerItem;
            $transferItemCustomerItem->fill($item);

            return $transferItemCustomerItem;
        }, $array);
    }

    /**
     * Update price, cogs in inventory.
     *
     * @param $form
     * @param $transferItemCustomer
     */
    public static function updateInventory($form, $transferItemCustomer)
    {
        foreach ($transferItemCustomer->items as $item) {
            if ($item->quantity > 0) {
                $options = [];
                if ($item->item->require_expiry_date) {
                    $options['expiry_date'] = $item->expiry_date;
                }
                if ($item->item->require_production_number) {
                    $options['production_number'] = $item->production_number;
                }

                $options['quantity_reference'] = $item->quantity;
                $options['unit_reference'] = $item->unit;
                $options['converter_reference'] = $item->converter;
                
                InventoryHelper::decrease($form, $item->TransferItemCustomer->warehouse, $item->item, $item->quantity, $item->unit, $item->converter, $options);
            }
        }
    }

    public static function updateJournal($transferItemCustomer)
    {
        /**
         * Journal Table
         * -----------------------------------------------------
         * Account                            | Debit | Credit |
         * -----------------------------------------------------
         * 1. Inventory in distribution       |   v   |        | 
         * 2. Inventories                     |       |   v    | Master Item
         */
        foreach ($transferItemCustomer->items as $transferItemCustomerItem) {
            $itemAmount = $transferItemCustomerItem->item->cogs($transferItemCustomerItem->item_id) * $transferItemCustomerItem->quantity;

            // 1. Inventory in distribution
            $journal = new Journal;
            $journal->form_id = $transferItemCustomer->form->id;
            $journal->journalable_type = Item::$morphName;
            $journal->journalable_id = $transferItemCustomerItem->item_id;
            $journal->chart_of_account_id = get_setting_journal('transfer item', 'inventory in distribution');
            $journal->debit = $itemAmount;
            $journal->save();

            // 2. Inventories
            $journal = new Journal;
            $journal->form_id = $transferItemCustomer->form->id;
            $journal->journalable_type = Item::$morphName;
            $journal->journalable_id = $transferItemCustomerItem->item_id;
            $journal->chart_of_account_id = $transferItemCustomerItem->item->chart_of_account_id;
            $journal->credit = $itemAmount;
            $journal->save();
        }
    }

    // public static function closeForm($transferItemCustomer, $items)
    // {
    //     /**
    //      * Journal Table
    //      * -----------------------------------------------------
    //      * Account                            | Debit | Credit |
    //      * -----------------------------------------------------
    //      * 1. Beban Selisih Persediaan       |   v   |        | 
    //      * 2. Persediaan                     |       |   v    | Master Item
    //      */
    //     foreach ($items as $item) {
    //         $itemAmount = Item::cogs($item['item_id']) * $item['difference'];
    //         // 1. Inventory in distribution
    //         $journal = new Journal;
    //         $journal->form_id = $transferItemCustomer->form->id;
    //         $journal->journalable_type = Item::$morphName;
    //         $journal->journalable_id = $item['item_id'];
    //         $journal->chart_of_account_id = get_setting_journal('transfer item', 'inventory in distribution');
    //         $journal->credit = $itemAmount;
    //         $journal->save();

    //         // 2. Difference stock expenses
    //         $journal = new Journal;
    //         $journal->form_id = $transferItemCustomer->form->id;
    //         $journal->journalable_type = Item::$morphName;
    //         $journal->journalable_id = $item['item_id'];
    //         $journal->chart_of_account_id = get_setting_journal('transfer item', 'difference stock expenses');
    //         $journal->debit = $itemAmount;
    //         $journal->save();
    //     }
    // }
}
