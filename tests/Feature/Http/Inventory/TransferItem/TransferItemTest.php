<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Inventory\TransferItem\TransferItem;
use Tests\TestCase;

class TransferItemTest extends TestCase
{
    use TransferItemSetup;
    
    public static $path = '/api/v1/inventory/transfer-items';

    /** @test */
    public function branch_not_default_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $this->unsetDefaultBranch();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function unauthorized_create_transfer_item()
    {
        $data = $this->dummyData();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(403)
            ->assertJson([
                "code" => 403,
                "message" => "This action is unauthorized."
            ]);
    }

    /** @test */
    public function invalid_required_data_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'date', null);
        $data = data_set($data, 'request_approval_to', null);
        $data = data_set($data, 'items.0.item_id', null);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function invalid_data_item_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'items.0.item_id', 100);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function invalid_data_warehouse_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'warehouse_id', 100);
        $data = data_set($data, 'to_warehouse_id', 200);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function invalid_data_notes_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'notes', $this->faker->text(500));

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function replace_first_and_last_space_in_notes_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'notes', ' Transfer item notes ');

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $this->assertEquals($response->json('data.form.notes'), 'Transfer item notes');
    }

    /** @test */
    public function check_current_stock_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $this->assertEquals($response->json('data.items.0.stock'), $data['items'][0]['stock']);
    }

    /** @test */
    public function check_final_balance_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $this->assertEquals($response->json('data.items.0.balance'), $data['items'][0]['stock'] - $data['items'][0]['quantity']);
    }
    
    /** @test */
    public function success_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $items = [];
        foreach ($data['items'] as $item) {
            array_push($items, [
                "transfer_item_id" => $response->json('data.id'),
                "item_id" => $item['item_id'],
                "item_name" => $item['item_name'],
                "unit" => $item['unit'],
                "converter" => $item['converter'],
                "quantity" => $item['quantity'],
                "stock" => $item['stock'],
                "balance" => $item['balance']
            ]);
        }
        
        $response->assertStatus(201)
            ->assertJson([
                "data" => [
                    "id" => $response->json('data.id'),
                    "warehouse_id" => $data['warehouse_id'],
                    "to_warehouse_id" => $data['to_warehouse_id'],
                    "driver" => $data['driver'],
                    "form" => [
                        "id" => $response->json('data.form.id'),
                        "date" => $data['date'],
                        "number" => $response->json('data.form.number'),
                        "request_approval_to" => $data['request_approval_to'],
                        "approval_status" => 0,
                        "notes" => $data['notes'],
                    ],
                    "items" => $items
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'approval_status' => 0,
            'done' => 0,
        ], 'tenant');

        $this->assertDatabaseHas('transfer_items', [
            'id' => $response->json('data.id'),
            "warehouse_id" => $data['warehouse_id'],
            "to_warehouse_id" => $data['to_warehouse_id'],
            "driver" => $data['driver'],
        ], 'tenant');

        foreach ($data['items'] as $item) {
            $this->assertDatabaseHas('transfer_item_items', [
                "item_id" => $item['item_id'],
                "item_name" => $item['item_name'],
                "unit" => $item['unit'],
                "converter" => $item['converter'],
                "quantity" => $item['quantity'],
                "stock" => $item['stock'],
                "balance" => $item['balance']
            ], 'tenant');
        }
    }

    /**
     * @test 
     */
    public function unauthorized_read_all_transfer_item()
    {
        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_sent.*',
            'group_by' => 'form.id',
            'sort_by' => '-form.number',
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
    public function success_read_all_transfer_item()
    {
        $this->setReadPermission();

        $this->createTransferItem();

        $transferItems = TransferItem::get();
        $transferItems = $transferItems->sortByDesc(function($q){
            return $q->form->number;
        });
        
        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_sent.*',
            'group_by' => 'form.id',
            'sort_by' => '-form.number',
            'includes' => 'items;form'
        ], $this->headers);

        $data = [];
        foreach ($transferItems as $transferItem) {
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
                "warehouse_id" => $transferItem->warehouse_id,
                "to_warehouse_id" => $transferItem->to_warehouse_id,
                "driver" => $transferItem->driver,
                "form" => [
                    "id" => $transferItem->form->id,
                    "date" => $transferItem->form->date,
                    "number" => $transferItem->form->number,
                    "notes" => $transferItem->form->notes,
                ],
                "items" => $items
            ]);
        };

        $response->assertStatus(200)
            ->assertJson([
                "data" => $data
            ]);
    }

    /**
     * @test 
     */
    public function unauthorized_read_single_transfer_item()
    {   
        $transferItem = $this->createTransferItem();

        $response = $this->json('GET', self::$path.'/'.$transferItem->id, [
            'includes' => 'warehouse;to_warehouse;items.item;form.createdBy;form.requestApprovalTo;form.branch'
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
    public function success_read_single_transfer_item()
    {
        $this->setReadPermission();

        $transferItem = $this->createTransferItem();

        $response = $this->json('GET', self::$path.'/'.$transferItem->id, [
            'includes' => 'warehouse;to_warehouse;items.item;form.createdBy;form.requestApprovalTo;form.branch'
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
                        "notes" => $transferItem->form->notes,
                    ],
                    "items" => $items
                ]
            ]);
    }

    /** @test */
    public function unauthorized_update_transfer_item()
    {   
        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);
        
        $response->assertStatus(403)
            ->assertJson([
                "code" => 403,
                "message" => "This action is unauthorized."
            ]);
    }

    /** @test */
    public function invalid_required_data_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();

        $data = data_set($data, 'date', null);
        $data = data_set($data, 'request_approval_to', null);
        $data = data_set($data, 'items.0.item_id', null);

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function invalid_data_item_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();

        $data = data_set($data, 'items.0.item_id', 100);

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function invalid_data_warehouse_update_transfer_item()
    {
        $this->setUpdatePermission();

        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();
    
        $data = data_set($data, 'warehouse_id', 100);
        $data = data_set($data, 'to_warehouse_id', 200);

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function invalid_data_notes_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $data = $this->dummyData();

        $data = data_set($data, 'notes', $this->faker->text(500));

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid."
            ]);
    }

    /** @test */
    public function replace_first_and_last_space_in_notes_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $data = $this->dummyData();

        $data = data_set($data, 'notes', ' Transfer item notes ');

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.form.notes'), 'Transfer item notes');
    }

    /** @test */
    public function check_current_stock_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.items.0.stock'), $data['items'][0]['stock']);
    }

    /** @test */
    public function check_final_balance_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.items.0.balance'), $data['items'][0]['stock'] - $data['items'][0]['quantity']);
    }

    /** @test */
    public function success_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;
        $data["notes"] = "Edit notes";

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $items = [];
        foreach ($data['items'] as $item) {
            array_push($items, [
                "transfer_item_id" => $response->json('data.id'),
                "item_id" => $item['item_id'],
                "item_name" => $item['item_name'],
                "unit" => $item['unit'],
                "converter" => $item['converter'],
                "quantity" => $item['quantity'],
                "stock" => $item['stock'],
                "balance" => $item['balance']
            ]);
        }
        
        $response->assertStatus(201)
            ->assertJson([
                "data" => [
                    "id" => $response->json('data.id'),
                    "warehouse_id" => $data['warehouse_id'],
                    "to_warehouse_id" => $data['to_warehouse_id'],
                    "driver" => $data['driver'],
                    "form" => [
                        "id" => $response->json('data.form.id'),
                        "date" => $data['date'],
                        "number" => $response->json('data.form.number'),
                        "request_approval_to" => $data['request_approval_to'],
                        "approval_status" => 0,
                        "notes" => $data['notes'],
                    ],
                    "items" => $items
                ]
            ]);
        
        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => null,
            'edited_number' => $response->json('data.form.number'),
            'notes' => $transferItem->form->notes,
            'approval_status' => 0,
            'done' => 0,
        ], 'tenant');

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'edited_number' => null,
            'notes' => $data["notes"],
            'approval_status' => 0,
            'done' => 0,
        ], 'tenant');
    }

    /** @test */
    public function unauthorized_delete_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, [], [$this->headers]);

        $response->assertStatus(403)
            ->assertJson([
                "code" => 403,
                "message" => "This action is unauthorized."
            ]);
    }
    
    /** @test */
    public function delete_transfer_item()
    {
        $this->setDeletePermission();
        
        $transferItem = $this->createTransferItem();

        $reason = $this->faker->text(200);

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, ['reason' => $reason], [$this->headers]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'request_cancellation_to' => $transferItem->form->request_approval_to,
            'request_cancellation_by' => $this->user->id,
            'request_cancellation_reason' => $reason,
            'cancellation_status' => 0,
        ], 'tenant');
    }

    /** @test */
    public function unauthorized_close_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $this->unsetCreatePermission();

        $reason = "close transfer item reason";

        $data = [
            "id" => $transferItem->id,
            "data" => ["reason" => $reason]
        ];

        $response = $this->json('POST', self::$path.'/'.$transferItem->id.'/close', $data, [$this->headers]);

        $response->assertStatus(403)
            ->assertJson([
                "code" => 403,
                "message" => "This action is unauthorized."
            ]);
    }

    /** @test */
    public function success_close_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $this->assertEquals($transferItem->form->close_status, null);
        
        $reason = "close transfer item reason";

        $data = [
            "id" => $transferItem->id,
            "data" => ["reason" => $reason]
        ];

        $response = $this->json('POST', self::$path.'/'.$transferItem->id.'/close', $data, [$this->headers]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItem->form->id,
            'number' => $transferItem->form->number,
            'request_close_to' => $transferItem->form->request_approval_to,
            'request_close_by' => $this->user->id,
            'request_close_reason' => $reason,
            'close_status' => 0,
        ], 'tenant');
    }

    /** @test */
    public function export_transfer_item_success()
    {
        $transferItem = $this->createTransferItem();

        $transferItem = TransferItem::orderBy('id', 'asc')->first();

        $data = [
            "data" => [
                "ids" => [$transferItem->id],
                "date_start" => date("Y-m-d", strtotime("-1 days")),
                "date_end" => date("Y-m-d", strtotime("+1 days")),
                "tenant_name" => "development"
            ]
        ];

        $response = $this->json('POST', self::$path.'/export', $data, $this->headers);
        
        $response->assertStatus(200)->assertJsonStructure([ 'data' => ['url'] ]);
    }
}
