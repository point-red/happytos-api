<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Token;
use Tests\TestCase;

class TransferItemApprovalByEmailTest extends TestCase
{
    use TransferItemSetup;
    
    public static $path = '/api/v1/inventory/transfer-items';

    private function findOrCreateToken($tenantUser)
    {
        $approverToken = Token::where('user_id', $tenantUser->id)->first();
        if (!$approverToken) {
            $approverToken = new Token();
            $approverToken->user_id = $tenantUser->id;
            $approverToken->token = md5($tenantUser->email.''.now());
            $approverToken->save();
        }

        return $approverToken;
    }
    
    /** @test */
    public function unauthorized_approve_by_email_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $data = [
            'action' => 'approve',
            'approver_id' => $transferItem->form->request_approval_to,
            'token' => 'invalid token',
            'resource-type' => 'TransferItem',
            'ids' => [
                ['id' => $transferItem->id]
            ],
            'crud-type' => 'create'
        ];

        $response = $this->json('POST', self::$path . '/approve', $data, $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Approve email failed"
            ]);
    }
    
    /** @test */
    public function success_approve_by_email_transfer_item()
    {
        $transferItem =  $this->createTransferItem();

        $this->assertEquals($transferItem->form->approval_status, 0);

        $approver = $transferItem->form->requestApprovalTo;
        $approverToken = $this->findOrCreateToken($approver);

        $data = [
            'action' => 'approve',
            'approver_id' => $transferItem->form->request_approval_to,
            'token' => $approverToken->token,
            'resource-type' => 'TransferItem',
            'ids' => [
                ['id' => $transferItem->id]
            ],
            'crud-type' => 'create'
        ];

        $response = $this->json('POST', self::$path . '/approve', $data, $this->headers);
        
        $items = [];
        foreach ($transferItem->items as $item) {
            array_push($items, [
                "id" => $item->id,
                "transfer_item_id" => $item->transfer_item_id,
                "item_id" => $item->item_id,
                "item_name" => $item->item_name,
                "unit" => $item->unit,
                "converter" => $item->converter,
                "quantity" => $item->quantity,
                "stock" => $item->stock,
                "balance" => $item->balance
            ]);
        }
        
        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    [
                        "id" => $transferItem->id,
                        "form" => [
                            "id" => $transferItem->form->id,
                            "date" => $transferItem->form->date,
                            "number" => $transferItem->form->number,
                            "id" => $transferItem->form->id,
                            "notes" => $transferItem->form->notes,
                            "approval_by" => $this->user->id,
                            "approval_status" => 1,
                        ],
                        "items" => $items
                    ]
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'approval_by' => $this->user->id,
            'approval_status' => 1,
        ], 'tenant');

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
    }

    /** @test */
    public function unauthorized_reject_by_email_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $data = [
            'action' => 'reject',
            'approver_id' => $transferItem->form->request_approval_to,
            'token' => 'invalid token',
            'resource-type' => 'TransferItem',
            'ids' => [
                ['id' => $transferItem->id]
            ],
            'crud-type' => 'create'
        ];

        $response = $this->json('POST', self::$path . '/reject', $data, $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Reject email failed"
            ]);
    }
    
    /** @test */
    public function success_reject_by_email_transfer_item()
    {   
        $transferItem = $this->createTransferItem();

        $this->assertEquals($transferItem->form->approval_status, 0);

        $approver = $transferItem->form->requestApprovalTo;
        $approverToken = $this->findOrCreateToken($approver);

        $data = [
            'action' => 'reject',
            'approver_id' => $transferItem->form->request_approval_to,
            'token' => $approverToken->token,
            'resource-type' => 'TransferItem',
            'ids' => [
                ['id' => $transferItem->id]
            ],
            'crud-type' => 'create'
        ];

        $response = $this->json('POST', self::$path . '/reject', $data, $this->headers);
        
        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    [
                        "id" => $transferItem->id,
                        "form" => [
                            "id" => $transferItem->form->id,
                            "date" => $transferItem->form->date,
                            "number" => $transferItem->form->number,
                            "id" => $transferItem->form->id,
                            "notes" => $transferItem->form->notes,
                            'approval_by' => $this->user->id,
                            'approval_status' => -1
                        ]
                    ]
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'approval_by' => $this->user->id,
            'approval_status' => -1
        ], 'tenant');
    }
}
