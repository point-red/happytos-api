<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Accounting\ChartOfAccount;
use App\Model\Form;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Inventory\TransferItem\ReceiveItemItem;
use App\Model\Master\Warehouse;
use App\Model\SettingJournal;
use Tests\TestCase;

class TransferItemCloseApprovalTest extends TestCase
{
    use TransferItemSetup;

    /** @test */
    public function form_has_been_approved_approve_close_transfer_item_customer()
    {
        $this->setApprovePermission();
        
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();

        $setting = new SettingJournal();
        $setting->feature = 'transfer item';
        $setting->name = 'difference stock expenses';
        $setting->chart_of_account_id = $coa->id;
        $setting->save();

        $transferItem = $this->createTransferItem();

        $transferItem->form->close_status = 1;
        $transferItem->form->save();

        $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
            'id' => $transferItem->id
        ], $this->headers);

        $difference = 2;

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/close-approve', [
            'id' => $transferItem->id,
            "items" => [
                [
                  "item_id" => $transferItem->items[0]->item_id,
                  "difference" => $difference
                ]
              ]
        ], $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "This form has been approved"
            ]);
    }
    
    /**
     * @test 
     */
    public function unauthorized_approve_close_transfer_item()
    {
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();

        $setting = new SettingJournal();
        $setting->feature = 'transfer item';
        $setting->name = 'difference stock expenses';
        $setting->chart_of_account_id = $coa->id;
        $setting->save();

        $transferItem = $this->createTransferItem();

        $this->assertEquals($transferItem->form->approval_status, 0);

        $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
            'id' => $transferItem->id
        ], $this->headers);

        $difference = 2;

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/close-approve', [
            'id' => $transferItem->id,
            "items" => [
                [
                  "item_id" => $transferItem->items[0]->item_id,
                  "difference" => $difference
                ]
              ]
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
            ]);
    }

    /**
     * @test 
     */
    public function success_close_approve_transfer_item()
    {
        $this->setApprovePermission();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();

        $setting = new SettingJournal();
        $setting->feature = 'transfer item';
        $setting->name = 'difference stock expenses';
        $setting->chart_of_account_id = $coa->id;
        $setting->save();

        $transferItem = $this->createTransferItem();
        $transferItem->form->close_status = 0;
        $transferItem->form->save();

        $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
            'id' => $transferItem->id
        ], $this->headers);

        $receiveItem = new ReceiveItem;
        $receiveItem->warehouse_id = $transferItem->to_warehouse_id;
        $receiveItem->from_warehouse_id = $transferItem->warehouse_id;
        $receiveItem->transfer_item_id = $transferItem->id;
        $receiveItem->save();

        $difference = 2;

        $receiveItemItem = new ReceiveItemItem;
        $receiveItemItem->receive_item_id = $receiveItem->id;
        $receiveItemItem->item_id = $transferItem->items[0]->item_id;
        $receiveItemItem->item_name = $transferItem->items[0]->item_name;
        $receiveItemItem->quantity = $transferItem->items[0]->quantity - $difference;
        $receiveItemItem->expiry_date = $transferItem->items[0]->expiry_date;
        $receiveItemItem->production_number = $transferItem->items[0]->production_number;
        $receiveItemItem->save();

        $form = new Form;
        $form->formable_id = $receiveItem->id;
        $form->formable_type = ReceiveItem::$morphName;
        $form->number = 'TIRECEIVE001';
        $form->save();

        $distributionWarehouse = Warehouse::where('name', 'DISTRIBUTION WAREHOUSE')->first();
        $firstStockDistributionWarehouse = $this->getStock($transferItem->items[0]->item, $distributionWarehouse, []);

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/close-approve', [
            'id' => $transferItem->id,
            "items" => [
                [
                  "item_id" => $transferItem->items[0]->item_id,
                  "difference" => $difference
                ]
              ]
        ], $this->headers);

        $distributionWarehouse = Warehouse::where('name', 'DISTRIBUTION WAREHOUSE')->first();
        $endStockDistributionWarehouse = $this->getStock($transferItem->items[0]->item, $distributionWarehouse, []);
        
        $this->assertEquals(
            $endStockDistributionWarehouse,
            $firstStockDistributionWarehouse - $difference
        );
        
        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    "id" => $transferItem->id,
                    "warehouse_id" => $transferItem->warehouse_id,
                    "to_warehouse_id" => $transferItem->to_warehouse_id,
                    "driver" => $transferItem->driver,
                    "form" => [
                        "id" => $transferItem->form->id,
                        "date" => $transferItem->form->date,
                        "number" => $transferItem->form->number,
                        "id" => $transferItem->form->id,
                        "notes" => $transferItem->form->notes,
                        "close_approval_by" => $this->user->id,
                        "close_status" => 1,
                        'done' => 1
                    ]
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'close_approval_by' => $this->user->id,
            'close_status' => 1,
            'done' => 1
        ], 'tenant');

        foreach ($transferItem->items as $transferItemItem) {
            $itemAmount = $transferItemItem->item->cogs($transferItemItem->item_id) * $difference;
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItem->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => get_setting_journal('transfer item', 'inventory in distribution'),
                'credit' => $itemAmount
            ], 'tenant');
            
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItem->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => get_setting_journal('transfer item', 'difference stock expenses'),
                'debit' => $itemAmount
            ], 'tenant');
        }
    }
}
