<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Accounting\ChartOfAccount;
use App\Model\Form;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Master\Item;
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
    public function warehouse_not_default_create_transfer_item()
    {
        $this->setCreatePermission();

        $this->unsetDefaultWarehouse();
        
        $data = $this->dummyData();
        $data = data_set($data, 'warehouse_id', 5);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to create this form',
            ]);
    }

    /** @test */
    public function unauthorized_create_transfer_item()
    {
        $data = $this->dummyData();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
            ]);
    }

    /** @test */
    public function invalid_required_data_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'date', null);
        $data = data_set($data, 'increment_group', null);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid.",
                'errors' => [
                    'date' => ['The date field is required.'],
                    'increment_group' => ['The increment group field is required.']
                ],
            ]);
    }

    public function invalid_unique_field_create_transfer_item()
    {
        $transferItem1 = $this->createTransferItem();
        $transferItem2 = $this->createTransferItem();

        $formTransfer1 = $transferItem1->form;
        $formTransfer2 = $transferItem2->form;
        
        try {
            $formTransfer1->number = $formTransfer2->number;
            $formTransfer1->save();
        } catch (\Throwable $th) {
            $this->assertStringContainsString('Integrity constraint violation: 1062 Duplicate entry', $th->getMessage());
        }
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
                "message" => "The given data was invalid.",
                'errors' => [
                    "items.0.item_id" => [
                        "The selected items.0.item_id is invalid."
                    ]
                ],
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
                "message" => "Warehouse  not set as default"
            ]);
    }

    /** @test */
    public function invalid_data_notes_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'notes', $this->faker->words(256));

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid.",
                'errors' => [
                    'notes' => [
                        'The notes must be a string.',
                        'The notes may not be greater than 255 characters.',
                    ],
                ]
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
        
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);
        
        $firstStock = $this->getStock($item, $this->warehouse, []);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $this->assertEquals($response->json('data.items.0.stock'), $firstStock);
    }

    /** @test */
    public function check_final_balance_create_transfer_item()
    {
        $this->setCreatePermission();
        
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $firstStock = $this->getStock($item, $this->warehouse, []);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $this->assertEquals($response->json('data.items.0.balance'), $firstStock - $data['items'][0]['quantity']);
    }

    /** @test */
    public function invalid_unit_create_transfer_item()
    {
        $this->setCreatePermission();

        $data = $this->dummyData();

        $data['items'][0]['unit'] = 'unitTest';

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid.",
                'errors' => [
                    'items.0.unit' => [
                        'The selected items.0.unit is invalid.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function quantity_bigger_then_stock_create_transfer_item()
    {
        $this->setCreatePermission();

        $data = $this->dummyData();
        $data['items'][0]['quantity'] = 1000000000;

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.quantity' => [
                        'The quantity cannot be greater than stock warehouse',
                    ],
                ],
            ]);
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

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
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
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
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
    public function branch_not_default_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $this->unsetDefaultBranch();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);
        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function warehouse_not_default_update_transfer_item()
    {
        $this->setUpdatePermission();

        $transferItem = $this->createTransferItem();

        $this->unsetDefaultWarehouse();

        $data = $this->dummyData();

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to update this form',
            ]);
    }

    /** @test */
    public function unauthorized_update_transfer_item()
    {   
        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
            ]);
    }

    /** @test */
    public function state_form_done_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $receiveItem = new ReceiveItem;
        $receiveItem->warehouse_id = $transferItem->to_warehouse_id;
        $receiveItem->from_warehouse_id = $transferItem->warehouse_id;
        $receiveItem->transfer_item_id = $transferItem->id;
        $receiveItem->save();

        $form = new Form;
        $form->formable_id = $receiveItem->id;
        $form->formable_type = ReceiveItem::$morphName;
        $form->number = 'TIRECEIVE001';
        $form->save();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Cannot edit form because referenced by transfer receive"
            ]);
    }

    /** @test */
    public function state_form_close_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $transferItem->form->close_status = 1;
        $transferItem->form->save();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Cannot edit form because the status of the form is close"
            ]);
    }

    /** @test */
    public function invalid_required_data_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();

        $data = $this->dummyData();

        $data["id"] = $transferItem->id;
        $data = data_set($data, 'date', null);
        $data = data_set($data, 'increment_group', null);

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid.",
                'errors' => [
                    'date' => ['The date field is required.'],
                    'increment_group' => ['The increment group field is required.']
                ],
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
                "message" => "Warehouse  not set as default"
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
                "message" => "The given data was invalid.",
                'errors' => [
                    "items.0.item_id" => [
                        "The selected items.0.item_id is invalid."
                    ]
                ],
            ]);
    }

    public function invalid_unique_field_update_transfer_item()
    {
        $transferItem1 = $this->createTransferItem();
        $transferItem2 = $this->createTransferItem();

        $formTransfer1 = $transferItem1->form;
        $formTransfer2 = $transferItem2->form;
        
        try {
            $formTransfer1->number = $formTransfer2->number;
            $formTransfer1->save();
        } catch (\Throwable $th) {
            $this->assertStringContainsString('Integrity constraint violation: 1062 Duplicate entry', $th->getMessage());
        }
    }

    /** @test */
    public function invalid_data_notes_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $data = $this->dummyData();

        $data = data_set($data, 'notes', $this->faker->words(256));

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid.",
                'errors' => [
                    'notes' => [
                        'The notes must be a string.',
                        'The notes may not be greater than 255 characters.',
                    ]
                ]
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
        
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $firstStock = $this->getStock($item, $this->warehouse, []);

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.items.0.stock'), $firstStock);
    }

    /** @test */
    public function check_final_balance_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();
        
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $firstStock = $this->getStock($item, $this->warehouse, []);

        $data["id"] = $transferItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.items.0.balance'), $firstStock - $data['items'][0]['quantity']);
    }

    /** @test */
    public function quantity_bigger_then_stock_update_transfer_item()
    {
        $this->setUpdatePermission();

        $transferItem = $this->createTransferItem();
        
        $data = $this->dummyData();
        $data["id"] = $transferItem->id;
        $data['items'][0]['quantity'] = 1000000000;

        $response = $this->json('PATCH', self::$path.'/'.$transferItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.quantity' => [
                        'The quantity cannot be greater than stock warehouse',
                    ],
                ],
            ]);
    }

    /** @test */
    public function success_update_transfer_item()
    {
        $this->setUpdatePermission();
        
        $transferItem = $this->createTransferItem();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $data["id"] = $transferItem->id;
        $data["notes"] = "Edit notes";
        $formNumber = $transferItem->form->number;

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

        $this->assertEquals($formNumber, $response->json('data.form.number'));
        
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
    public function branch_not_default_delete_transfer_item()
    {
        $this->setDeletePermission();
        
        $transferItem = $this->createTransferItem();
        
        $this->unsetDefaultBranch();

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function warehouse_not_default_delete_transfer_item()
    {
        $this->setDeletePermission();

        $transferItem = $this->createTransferItem();

        $this->unsetDefaultWarehouse();

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to delete this form',
            ]);
    }

    /** @test */
    public function unauthorized_delete_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
            ]);
    }

    /** @test */
    public function state_form_done_delete_transfer_item()
    {
        $this->setDeletePermission();
        
        $transferItem = $this->createTransferItem();

        $transferItem->form->done = 1;
        $transferItem->form->save();

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Cannot delete form because the status of the form is done",
            ]);
    }

    /** @test */
    public function invalid_reason_delete_transfer_item()
    {
        $this->setDeletePermission();
        
        $transferItem = $this->createTransferItem();

        $response = $this->json('DELETE', self::$path.'/'.$transferItem->id, [], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "The given data was invalid.",
                "errors" => [
                    "reason" => [
                        "The reason field is required."
                    ]
                ]
            ]);
    }
    
    /** @test */
    public function success_delete_transfer_item()
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
            'done' => 0,
        ], 'tenant');
    }

    /** @test */
    public function state_form_done_close_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $transferItem->form->done = 1;
        $transferItem->form->save();

        $reason = "close transfer item reason";

        $data = [
            "id" => $transferItem->id,
            "data" => ["reason" => $reason]
        ];

        $response = $this->json('POST', self::$path.'/'.$transferItem->id.'/close', $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Cannot close form because the status of the form is done",
            ]);
    }

    /** @test */
    public function branch_not_default_close_transfer_item()
    {   
        $transferItem = $this->createTransferItem();
        
        $this->unsetDefaultBranch();

        $reason = "close transfer item reason";

        $data = [
            "id" => $transferItem->id,
            "data" => ["reason" => $reason]
        ];

        $response = $this->json('POST', self::$path.'/'.$transferItem->id.'/close', $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function warehouse_not_default_close_transfer_item()
    {
        $transferItem = $this->createTransferItem();

        $this->unsetDefaultWarehouse();

        $reason = "close transfer item reason";

        $data = [
            "id" => $transferItem->id,
            "data" => ["reason" => $reason]
        ];

        $response = $this->json('POST', self::$path.'/'.$transferItem->id.'/close', $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to close this form',
            ]);
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

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
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
