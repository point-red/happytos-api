<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Accounting\ChartOfAccount;
use App\Model\SettingJournal;
use Tests\TestCase;

class TransferItemCloseApprovalTest extends TestCase
{
    use TransferItemSetup;

    /**
     * @test 
     */
    public function unauthorized_approve_transfer_item()
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

        $response->assertStatus(403)
            ->assertJson([
                "code" => 403,
                "message" => "This action is unauthorized."
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
