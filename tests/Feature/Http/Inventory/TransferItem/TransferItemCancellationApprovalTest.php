<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use Tests\TestCase;

class TransferItemCancellationApprovalTest extends TestCase
{
    use TransferItemSetup;
    
    public static $path = '/api/v1/inventory/transfer-items';

    /**
     * @test 
     */
    public function unauthorized_cancellation_approve_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $response = $this->json('POST', self::$path . '/' . $transferItem->id.'/cancellation-approve', [
            'id' => $transferItem->id
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
    public function success_cancellation_approve_transfer_item()
    {
        $this->setApprovePermission();
        $this->setDeletePermission();

        $transferItem = $this->createTransferItem();

        $this->json('DELETE', self::$path . '/'.$transferItem->id, ['reason' => 'some rason'], [$this->headers]);

        $this->assertEquals($transferItem->form->cancellation_status, 0);

        $this->json('POST', self::$path . '/' .$transferItem->id.'/approve', [
            'id' => $transferItem->id
        ], $this->headers);

        foreach ($transferItem->items as $transferItemItem) {
            $itemAmount = $transferItemItem->item->cogs($transferItemItem->item_id) * $transferItemItem->quantity;
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItem->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => get_setting_journal('transfer item', 'inventory in distribution'),
                'debit' => $itemAmount
            ], 'tenant');
            
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItem->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => $transferItemItem->item->chart_of_account_id,
                'credit' => $itemAmount
            ], 'tenant');
        }

        $response = $this->json('POST', self::$path . '/' .$transferItem->id.'/cancellation-approve', [
            'id' => $transferItem->id
        ], $this->headers);
        
        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    "id" => $transferItem->id,
                    "form" => [
                        "id" => $transferItem->form->id,
                        "date" => $transferItem->form->date,
                        "number" => $transferItem->form->number,
                        "id" => $transferItem->form->id,
                        "notes" => $transferItem->form->notes,
                        "cancellation_approval_by" => $this->user->id,
                        "cancellation_status" => 1,
                    ]
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'cancellation_approval_by' => $this->user->id,
            'cancellation_status' => 1,
        ], 'tenant');

        foreach ($transferItem->items as $transferItemItem) {
            $itemAmount = $transferItemItem->item->cogs($transferItemItem->item_id) * $transferItemItem->quantity;
            $this->assertDatabaseMissing('journals', [
                'form_id' => $transferItem->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => get_setting_journal('transfer item', 'inventory in distribution'),
                'debit' => $itemAmount
            ], 'tenant');
            
            $this->assertDatabaseMissing('journals', [
                'form_id' => $transferItem->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => $transferItemItem->item->chart_of_account_id,
                'credit' => $itemAmount
            ], 'tenant');
        }
    }

    /**
     * @test 
     */
    public function unauthorized_cancellation_reject_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $response = $this->json('POST', self::$path . '/' .$transferItem->id.'/cancellation-reject', [
            'id' => $transferItem->id,
            'reason' => 'some reason'
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
    public function invalid_cancellation_reject_transfer_item()
    {
        $this->setApprovePermission();

        $transferItem = $this->createTransferItem();

        $response = $this->json('POST', self::$path . '/' .$transferItem->id.'/cancellation-reject', [], $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /**
     * @test 
     */
    public function success_cancellation_reject_transfer_item()
    {
        $this->setApprovePermission();
        $this->setDeletePermission();

        $transferItem = $this->createTransferItem();

        $this->json('DELETE', self::$path . '/' .$transferItem->id, ['reason' => 'some rason'], [$this->headers]);

        $this->assertEquals($transferItem->form->cancellation_status, 0);

        $response = $this->json('POST', self::$path . '/' .$transferItem->id.'/cancellation-reject', [
            'id' => $transferItem->id,
            'reason' => 'some reason'
        ], $this->headers);
        
        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    "id" => $transferItem->id,
                    "form" => [
                        "id" => $transferItem->form->id,
                        "date" => $transferItem->form->date,
                        "number" => $transferItem->form->number,
                        "id" => $transferItem->form->id,
                        "notes" => $transferItem->form->notes,
                        'cancellation_approval_by' => $this->user->id,
                        'cancellation_approval_reason' => 'some reason',
                        'cancellation_status' => -1,
                    ]
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'cancellation_approval_by' => $this->user->id,
            'cancellation_approval_reason' => 'some reason',
            'cancellation_status' => -1,
        ], 'tenant');
    }
}
