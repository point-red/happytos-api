<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Master\Item;
use App\Model\Accounting\ChartOfAccount;
use App\Model\Inventory\TransferItem\TransferItem;
use Tests\TestCase;

class TransferItemApprovalTest extends TestCase
{
    public static $path = '/api/v1/inventory/approval/transfer-items';

    use TransferItemSetup;
    
    public function createTransferItemNotApproved()
    {
        sleep(1);
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $this->json('POST', '/api/v1/inventory/transfer-items', $data, $this->headers);

    }

    public function createtransferItemApproved()
    {
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $transferItem = $this->json('POST', '/api/v1/inventory/transfer-items', $data, $this->headers);

        $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->json('data')["id"].'/approve', [
            'id' => $transferItem->json('data')["id"]
        ], $this->headers);
    }

    /**
     * @test 
     */
    public function read_all_transfer_item_approval()
    {
        $this->setCreatePermission();
        $this->setApprovePermission();
        
        $this->createTransferItemNotApproved();
        $this->createTransferItemNotApproved();
        $this->createTransferItemNotApproved();
        $this->createTransferItemApproved();
        $this->createTransferItemApproved();

        $transferItemNotApproved = transferItem::whereHas('form', function($query){
            $query->whereApprovalStatus(0); 
        })->get();

        $transferItemNotApproved = $transferItemNotApproved->sortByDesc(function($q){
            return $q->form->date;
        });
        
        $response = $this->json('GET', self::$path, [
            'limit' => '10',
            'page' => '1',
        ], $this->headers);

        $data = [];
        foreach ($transferItemNotApproved as $transferItem) {
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
            array_push($data, [
                "id" => $transferItem->id,
                "date" => $transferItem->form->date,
                "number" => $transferItem->form->number,
                "warehouse_send" => $transferItem->warehouse->name,
                "warehouse_receive" => $transferItem->to_warehouse->name
            ]);
        };

        $response->assertStatus(200)
            ->assertJson([
                "data" => $data,
                "links" => [
                    "prev" => null,
                    "next" => null
                ],
                "meta" => [
                    "total" => count($transferItemNotApproved)
                ]
            ]);
    }

    /**
     * @test 
     */
    public function unauthorized_approve_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
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
    public function stock_not_enough_approve_transfer_item()
    {
        $this->setCreatePermission();
        $this->setApprovePermission();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
            
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $data = data_set($data, 'items.0.quantity', 200);

        $response = $this->json('POST', '/api/v1/inventory/transfer-items', $data, $this->headers);

        $transferItem = TransferItem::where('id', $response->json('data')["id"])->first();

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
            'id' => $transferItem->id
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Stock ". $item->name ." not enough! Current stock = 100 "
            ]);
    }

    /**
     * @test 
     */
    public function success_approve_transfer_item()
    {
        $this->setApprovePermission();

        $transferItem = $this->createTransferItem();

        $this->assertEquals($transferItem->form->approval_status, 0);

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
            'id' => $transferItem->id
        ], $this->headers);

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
                        "approval_by" => $this->user->id,
                        "approval_status" => 1,
                    ],
                    "items" => $items
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

    /**
     * @test 
     */
    public function unauthorized_reject_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/reject', [
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
    public function success_reject_transfer_item()
    {
        $this->setApprovePermission();

        $transferItem = $this->createTransferItem();
        
        $this->assertEquals($transferItem->form->approval_status, 0);

        $response = $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/reject', [
            'id' => $transferItem->id,
            'reason' => 'some reason'
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
                        "notes" => $transferItem->form->notes,
                        'approval_by' => $this->user->id,
                        'approval_status' => -1,
                        'approval_reason' => 'some reason'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'approval_by' => $this->user->id,
            'approval_status' => -1,
            'approval_reason' => 'some reason'
        ], 'tenant');
    }

    /** @test */
    public function send_transfer_item_approval()
    {
        $transferItem = $this->createTransferItem();

        $data = [
            "ids" => [
                "id" => $transferItem->id,
            ],
        ];

        $response = $this->json('POST', self::$path.'/send', $data, $this->headers);
        
        $response->assertStatus(200)
            ->assertJson([
                "input" => [
                    "ids" => [
                        "id" => $transferItem->id,
                    ]
                ]
            ]);
    }
}
