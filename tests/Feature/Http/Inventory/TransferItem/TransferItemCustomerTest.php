<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Master\Item;
use App\Model\Accounting\ChartOfAccount;
use App\Model\Inventory\TransferItem\TransferItemCustomer;
use Tests\TestCase;

class TransferItemCustomerTest extends TestCase
{
    use TransferItemCustomerSetup;
    
    public static $path = '/api/v1/inventory/transfer-item-customers';

    
    /** @test */
    public function branch_not_default_create_transfer_item_customer()
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
    public function warehouse_not_default_create_transfer_item_customer()
    {
        $this->setCreatePermission();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();
        
        $this->unsetDefaultWarehouse();

        $data = $this->dummyData($item);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to create this form',
            ]);
    }
    
    /** @test */
    public function unauthorized_create_transfer_item_customer()
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
    public function invalid_required_data_create_transfer_item_customer()
    {
        $this->setCreatePermission();

        $data = $this->dummyData();

        $data = data_set($data, 'date', null);
        $data = data_set($data, 'increment_group', null);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
        ->assertJson([
            'code' => 422,
            'message' => 'The given data was invalid.',
            'errors' => [
                'date' => ['The date field is required.'],
                'increment_group' => ['The increment group field is required.']
            ],
        ]);
    }

    public function invalid_unique_field_create_transfer_item()
    {
        $transferItemCustomer1 = $this->createTransferItemCustomer();
        $transferItemCustomer2 = $this->createTransferItemCustomer();

        $formTransfer1 = $transferItemCustomer1->form;
        $formTransfer2 = $transferItemCustomer2->form;
        
        try {
            $formTransfer1->number = $formTransfer2->number;
            $formTransfer1->save();
        } catch (\Throwable $th) {
            $this->assertStringContainsString('Integrity constraint violation: 1062 Duplicate entry', $th->getMessage());
        }
    }
    
    /** @test */
    public function invalid_data_item_create_transfer_item_customer()
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
    public function invalid_data_warehouse_create_transfer_item_customer()
    {
        $this->setCreatePermission();
        
        $data = $this->dummyData();

        $data = data_set($data, 'warehouse_id', 1000);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Warehouse  not set as default"
            ]);
    }
    
    /** @test */
    public function invalid_data_notes_create_transfer_item_customer()
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
                ],
            ]);
    }

    /** @test */
    public function replace_first_and_last_space_in_notes_create_transfer_item_customer()
    {
        $this->setCreatePermission();

        $data = $this->dummyData();

        $data = data_set($data, 'notes', ' Transfer item notes ');

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $this->assertEquals($response->json('data.form.notes'), 'Transfer item notes');
    }

    /** @test */
    public function check_current_stock_create_transfer_item_customer()
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
    public function check_final_balance_create_transfer_item_customer()
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
    public function invalid_unit_create_transfer_item_customer()
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
    public function stock_not_enough_create_transfer_item_customer()
    {
        $this->setCreatePermission();
        
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $data = data_set($data, 'items.0.quantity', 200);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Stock ". $item->name ." not enough! Current stock = 100 "
            ]);
    }
    
    /** @test */
    public function invalid_journal_create_transfer_item_customer()
    {
        $this->setCreatePermission();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = null;
        $item->save();

        $data = $this->dummyData($item);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Please set item account!"
            ]);
    }
    
    /** @test */
    public function success_create_transfer_item_customer()
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

        $endStock = $this->getStock($item, $this->warehouse, []);

        $items = [];
        foreach ($data['items'] as $item) {
            array_push($items, [
                "transfer_item_customer_id" => $response->json('data.id'),
                "item_id" => $item['item_id'],
                "item_name" => $item['item_name'],
                "unit" => $item['unit'],
                "converter" => $item['converter'],
                "quantity" => $item['quantity'],
                "stock" => $item['stock'],
                "balance" => $item['balance']
            ]);

            $this->assertEquals(
                $endStock,
                $firstStock - $item['quantity']
            );
        }
        
        $response->assertStatus(201)
            ->assertJson([
                "data" => [
                    "id" => $response->json('data.id'),
                    "warehouse_id" => $data['warehouse_id'],
                    "customer_id" => $data['customer_id'],
                    "expedition_id" => $data['expedition_id'],
                    "plat" => $data['plat'],
                    "stnk" => $data['stnk'],
                    "phone" => $data['phone'],
                    "form" => [
                        "id" => $response->json('data.form.id'),
                        "date" => $data['date'],
                        "number" => $response->json('data.form.number'),
                        "request_approval_to" => $data['request_approval_to'],
                        "approval_status" => 1,
                        "notes" => $data['notes'],
                    ],
                    "items" => $items
                ]
            ]);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'approval_status' => 1,
            'done' => 0,
        ], 'tenant');

        $this->assertDatabaseHas('transfer_item_customers', [
            'id' => $response->json('data.id'),
            "warehouse_id" => $data['warehouse_id'],
            "customer_id" => $data['customer_id'],
            "expedition_id" => $data['expedition_id'],
            "plat" => $data['plat'],
            "stnk" => $data['stnk'],
            "phone" => $data['phone'],
        ], 'tenant');

        foreach ($data['items'] as $item) {
            $this->assertDatabaseHas('transfer_item_customer_items', [
                "item_id" => $item['item_id'],
                "item_name" => $item['item_name'],
                "unit" => $item['unit'],
                "converter" => $item['converter'],
                "quantity" => $item['quantity'],
                "stock" => $item['stock'],
                "balance" => $item['balance']
            ], 'tenant');
        }

        $transferItemCustomer = TransferItemCustomer::where('id', $response->json('data.id'))->first();
        
        foreach ($transferItemCustomer->items as $transferItemItem) {
            $itemAmount = $transferItemItem->item->cogs($transferItemItem->item_id) * $transferItemItem->quantity;
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItemCustomer->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => get_setting_journal('transfer item', 'inventory in distribution'),
                'debit' => $itemAmount
            ], 'tenant');
            
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItemCustomer->form->id,
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
    public function unauthorized_read_all_transfer_item_customer()
    {
        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_sent_customer.*',
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
    public function success_read_all_transfer_item_customer()
    {
        $this->setReadPermission();

        $this->createTransferItemCustomer();

        $transferItemCustomers = TransferItemCustomer::get();
        $transferItemCustomers = $transferItemCustomers->sortByDesc(function($q){
            return $q->form->number;
        });
        
        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_sent_customer.*',
            'group_by' => 'form.id',
            'sort_by' => '-form.number',
            'includes' => 'items;form'
        ], $this->headers);

        $data = [];
        foreach ($transferItemCustomers as $transferItemCustomer) {
            $items = [];
            foreach ($transferItemCustomer->items as $item) {
                array_push($items, [
                    "id" => $item->id,
                    "transfer_item_customer_id" => $item->transfer_item_customer_id,
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
                "id" => $transferItemCustomer->id,
                "warehouse_id" => $transferItemCustomer->warehouse_id,
                "customer_id" => $transferItemCustomer->customer_id,
                "expedition_id" => $transferItemCustomer->expedition_id,
                "plat" => $transferItemCustomer->plat,
                "stnk" => $transferItemCustomer->stnk,
                "phone" => $transferItemCustomer->phone,
                "form" => [
                    "id" => $transferItemCustomer->form->id,
                    "date" => $transferItemCustomer->form->date,
                    "number" => $transferItemCustomer->form->number,
                    "notes" => $transferItemCustomer->form->notes,
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
    public function unauthorized_read_single_transfer_item_customer()
    {   
        $transferItemCustomer = $this->createTransferItemCustomer();

        $response = $this->json('GET', self::$path.'/'.$transferItemCustomer->id, [
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
    public function success_read_single_transfer_item_customer()
    {
        $this->setReadPermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $response = $this->json('GET', self::$path.'/'.$transferItemCustomer->id, [
            'includes' => 'warehouse;customer;expedition;items.item;form.createdBy;form.requestApprovalTo;form.branch'
        ], $this->headers);
        
        $items = [];
        foreach ($transferItemCustomer->items as $item) {
            array_push($items, [
                "id" => $item->id,
                "transfer_item_customer_id" => $item->transfer_item_customer_id,
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
                    "id" => $transferItemCustomer->id,
                    "warehouse_id" => $transferItemCustomer->warehouse_id,
                    "customer_id" => $transferItemCustomer->customer_id,
                    "expedition_id" => $transferItemCustomer->expedition_id,
                    "plat" => $transferItemCustomer->plat,
                    "stnk" => $transferItemCustomer->stnk,
                    "phone" => $transferItemCustomer->phone,
                    "form" => [
                        "id" => $transferItemCustomer->form->id,
                        "date" => $transferItemCustomer->form->date,
                        "number" => $transferItemCustomer->form->number,
                        "notes" => $transferItemCustomer->form->notes,
                    ],
                    "items" => $items
                ]
            ]);
    }

    /** @test */
    public function branch_not_default_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();
        
        $this->unsetDefaultBranch();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);
        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function warehouse_not_default_update_transfer_item_customer()
    {
        $this->setUpdatePermission();

        $transferItemCustomer = $this->createTransferItemCustomer();

        $this->unsetDefaultWarehouse();

        $data = $this->dummyData();

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to update this form',
            ]);
    }

    /** @test */
    public function unauthorized_update_transfer_item_customer()
    {   
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);
        
        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
            ]);
    }

    /** @test */
    public function invalid_required_data_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;
        $data = data_set($data, 'date', null);
        $data = data_set($data, 'increment_group', null);

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

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

    public function invalid_unique_field_update_transfer_item()
    {
        $transferItemCustomer1 = $this->createTransferItemCustomer();
        $transferItemCustomer2 = $this->createTransferItemCustomer();

        $formTransfer1 = $transferItemCustomer1->form;
        $formTransfer2 = $transferItemCustomer2->form;
        
        try {
            $formTransfer1->number = $formTransfer2->number;
            $formTransfer1->save();
        } catch (\Throwable $th) {
            $this->assertStringContainsString('Integrity constraint violation: 1062 Duplicate entry', $th->getMessage());
        }
    }

    /** @test */
    public function invalid_data_warehouse_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;
        $data = data_set($data, 'warehouse_id', 1000);

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Warehouse  not set as default"
            ]);
    }
    
    /** @test */
    public function invalid_data_item_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;
        $data = data_set($data, 'items.0.item_id', 100);

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

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
    public function invalid_data_notes_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;
        $data = data_set($data, 'notes', $this->faker->words(256));

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

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
    public function replace_first_and_last_space_in_notes_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = $this->dummyData();

        $data["id"] = $transferItemCustomer->id;
        $data = data_set($data, 'notes', ' Transfer item notes ');

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.form.notes'), 'Transfer item notes');
    }

    /** @test */
    public function check_current_stock_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $firstStock = $this->getStock($item, $this->warehouse, []);

        $data["id"] = $transferItemCustomer->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.items.0.stock'), $firstStock);
    }

    /** @test */
    public function check_final_balance_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $firstStock = $this->getStock($item, $this->warehouse, []);

        $data["id"] = $transferItemCustomer->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $this->assertEquals($response->json('data.items.0.balance'), $firstStock - $data['items'][0]['quantity']);
    }

    /** @test */
    public function stock_not_enough_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $data["id"] = $transferItemCustomer->id;
        $data = data_set($data, 'items.0.quantity', 200);

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Stock ". $item->name ." not enough! Current stock = 100 "
            ]);
    }

    /** @test */
    public function invalid_journal_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = null;
        $item->save();

        $data = $this->dummyData($item);

        $data["id"] = $transferItemCustomer->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Please set item account!"
            ]);
    }

    /** @test */
    public function success_update_transfer_item_customer()
    {
        $this->setUpdatePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $data["id"] = $transferItemCustomer->id;
        $data["notes"] = "Edit notes";
        $formNumber = $transferItemCustomer->form->number;

        $firstStock = $this->getStock($item, $this->warehouse, []);

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $endStock = $this->getStock($item, $this->warehouse, []);

        $items = [];
        foreach ($data['items'] as $item) {
            array_push($items, [
                "transfer_item_customer_id" => $response->json('data.id'),
                "item_id" => $item['item_id'],
                "item_name" => $item['item_name'],
                "unit" => $item['unit'],
                "converter" => $item['converter'],
                "quantity" => $item['quantity'],
                "stock" => $item['stock'],
                "balance" => $item['balance']
            ]);

            $this->assertEquals(
                $endStock,
                $firstStock - $item['quantity']
            );
        }

        $this->assertEquals($formNumber, $response->json('data.form.number'));
        
        $response->assertStatus(201)
            ->assertJson([
                "data" => [
                    "id" => $response->json('data.id'),
                    "warehouse_id" => $data['warehouse_id'],
                    "customer_id" => $data['customer_id'],
                    "expedition_id" => $data['expedition_id'],
                    "plat" => $data['plat'],
                    "stnk" => $data['stnk'],
                    "phone" => $data['phone'],
                    "form" => [
                        "id" => $response->json('data.form.id'),
                        "date" => $data['date'],
                        "number" => $response->json('data.form.number'),
                        "request_approval_to" => $data['request_approval_to'],
                        "approval_status" => 1,
                        "notes" => $data['notes'],
                    ],
                    "items" => $items
                ]
            ]);
        
        $this->assertDatabaseHas('forms', [
            'id' => $transferItemCustomer->form->id,
            'number' => null,
            'edited_number' => $response->json('data.form.number'),
            'notes' => $transferItemCustomer->form->notes,
            'approval_status' => 1,
            'done' => 0,
        ], 'tenant');

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'edited_number' => null,
            'notes' => $data["notes"],
            'approval_status' => 1,
            'done' => 0,
        ], 'tenant');

        $transferItemCustomer = TransferItemCustomer::where('id', $response->json('data.id'))->first();
        
        foreach ($transferItemCustomer->items as $transferItemItem) {
            $itemAmount = $transferItemItem->item->cogs($transferItemItem->item_id) * $transferItemItem->quantity;
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItemCustomer->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => get_setting_journal('transfer item', 'inventory in distribution'),
                'debit' => $itemAmount
            ], 'tenant');
            
            $this->assertDatabaseHas('journals', [
                'form_id' => $transferItemCustomer->form->id,
                'journalable_type' => 'Item',
                'journalable_id' => $transferItemItem->item_id,
                'chart_of_account_id' => $transferItemItem->item->chart_of_account_id,
                'credit' => $itemAmount
            ], 'tenant');
        }
    }

    /** @test */
    public function branch_not_default_delete_transfer_item_customer()
    {
        $this->setDeletePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();
        
        $this->unsetDefaultBranch();

        $response = $this->json('DELETE', self::$path.'/'.$transferItemCustomer->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function warehouse_not_default_delete_transfer_item_customer()
    {
        $this->setDeletePermission();

        $transferItemCustomer = $this->createTransferItemCustomer();

        $this->unsetDefaultWarehouse();

        $response = $this->json('DELETE', self::$path.'/'.$transferItemCustomer->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to delete this form',
            ]);
    }

    /** @test */
    public function unauthorized_delete_transfer_item_customer()
    {
        $transferItemCustomer = $this->createTransferItemCustomer();

        $response = $this->json('DELETE', self::$path.'/'.$transferItemCustomer->id, [
            'reason' => 'some reason'
        ], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                "code" => 422,
                "message" => "Unauthorized"
            ]);
    }

    /** @test */
    public function invalid_reason_delete_transfer_item_customer()
    {
        $this->setDeletePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $response = $this->json('DELETE', self::$path.'/'.$transferItemCustomer->id, [], [$this->headers]);

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
    public function success_delete_transfer_item_customer()
    {
        $this->setDeletePermission();
        
        $transferItemCustomer = $this->createTransferItemCustomer();

        $reason = $this->faker->text(200);
        
        $response = $this->json('DELETE', self::$path.'/'.$transferItemCustomer->id, ['reason' => $reason], [$this->headers]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('forms', [
            'id' => $transferItemCustomer->form->id,
            'number' => $transferItemCustomer->form->number,
            'request_cancellation_to' => $transferItemCustomer->form->request_approval_to,
            'request_cancellation_by' => $this->user->id,
            'request_cancellation_reason' => $reason,
            'cancellation_status' => 0,
            'done' => 0
        ], 'tenant');
    }

    /** @test */
    public function export_transfer_item_customer()
    {
        $transferItemCustomer = $this->createTransferItemCustomer();

        $data = [
            "data" => [
                "ids" => [$transferItemCustomer->id],
                "date_start" => date("Y-m-d", strtotime("-1 days")),
                "date_end" => date("Y-m-d", strtotime("+1 days")),
                "tenant_name" => "development"
            ]
        ];

        $response = $this->json('POST', self::$path.'/export', $data, $this->headers);
        
        $response->assertStatus(200)->assertJsonStructure([ 'data' => ['url'] ]);
    }
}
