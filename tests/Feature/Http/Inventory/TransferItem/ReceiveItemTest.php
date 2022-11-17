<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Inventory\TransferItem\ReceiveItem;
use Tests\TestCase;

class ReceiveItemTest extends TestCase
{
    use ReceiveItemSetup;

    /** @test */
    public function create_receive_item_no_permission()
    {
        $data = $this->dummyDataReceiveItem();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function create_receive_item_branch_not_default()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->unsetDefaultBranch();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function create_receive_item_warehouse_not_default()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['warehouse_id'] = 1;

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Warehouse  not set as default',
            ]);
    }

    /** @test */
    public function create_receive_item_warehouse_failed()
    {
        $this->setRole();

        $data = ['warehouse_id' => $this->warehouse->id];

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'date' => ['The date field is required.'],
                    'increment_group' => ['The increment group field is required.'],
                    'from_warehouse_id' => ['The from warehouse id field is required.'],
                    'transfer_item_id' => ['The transfer item id field is required.'],
                    'items' => ['The items field is required when services is not present.'],
                ],
            ]);
    }

    /** @test */
    public function create_receive_item_warehouse_quantity_zero()
    {
        $this->setRole();

        $data = ['warehouse_id' => $this->warehouse->id];
        $data['items'][0] = data_set($data['items'][0], 'quantity', 0);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.quantity' => [
                        'The items.0.quantity must be at least 1.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function create_receive_item_warehouse_notes_long_text()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['notes'] = $this->faker->words(256);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'notes' => [
                        'The notes must be a string.',
                        'The notes may not be greater than 255 characters.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function create_receive_item()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(201);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
        ], 'tenant');

        // approve create
        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);
    }

    /** @test */
    public function create_check_quantity_match()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $transferItem = TransferItem::orderBy('id', 'asc')->first();

        $this->assertEquals(
            $transferItem->items[0]->quantity,
            $receiveItem->items[0]->quantity
        );
    }

    /** @test */
    public function create_receive_item_check_balance()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(201);

        $this->assertEquals(
            $response->json('data.items.0.balance'),
            (int) ($data['items'][0]['stock'] + $data['items'][0]['quantity'])
        );
    }

    /** @test */
    public function read_all_receive_item_no_permissions()
    {
        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_receive.*',
            'group_by' => 'form.id',
            'sort_by' => '-form.number',
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function read_single_receive_item_no_permissions()
    {
        $this->create_receive_item();

        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('GET', self::$path.'/'.$receiveItem->id, [
            'includes' => 'warehouse;from_warehouse;items.item;form.createdBy;form.requestApprovalTo;form.branch'
        ], $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function read_all_receive_item()
    {
        $this->setRole();

        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_receive.*',
            'group_by' => 'form.id',
            'sort_by' => '-form.number',
        ], $this->headers);

        $response->assertStatus(200);
    }

    /** @test */
    public function read_single_receive_item()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('GET', self::$path.'/'.$receiveItem->id, [
            'includes' => 'warehouse;from_warehouse;items.item;form.createdBy;form.requestApprovalTo;form.branch'
        ], $this->headers);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function update_receive_item_no_permission()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->unsetUserRole();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function update_receive_item_branch_not_default()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->unsetDefaultBranch();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function update_receive_item_warehouse_not_default()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;
        $data['warehouse_id'] = 1;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Warehouse  not set as default',
            ]);
    }

    /** @test */
    public function update_receive_item_not_same_data()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.($receiveItem->id+1), $data, [$this->headers]);

        $response->assertStatus(404)
            ->assertJson([
                'code' => 404,
                'message' => 'Model not found.',
            ]);
    }

    /** @test */
    public function update_receive_item_warehouse_notes_long_text()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;
        $data['notes'] = $this->faker->words(256);

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'notes' => [
                        'The notes must be a string.',
                        'The notes may not be greater than 255 characters.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function update_receive_item_warehouse_failed()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = [
            'id' => $receiveItem->id,
            'warehouse_id' => $this->warehouse->id,
        ];

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'date' => ['The date field is required.'],
                    'increment_group' => ['The increment group field is required.'],
                    'from_warehouse_id' => ['The from warehouse id field is required.'],
                    'items' => ['The items field is required when services is not present.'],
                ],
            ]);
    }

    /** @test */
    public function update_receive_item_warehouse_quantity_zero()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;
        $data['items'][0] = data_set($data['items'][0], 'quantity', 0);

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.quantity' => [
                        'The items.0.quantity must be at least 1.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function update_receive_item_stock_not_enough()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $item = $receiveItem->items[0]->item;
        $unit = $item->units[0];
        $options = [
            'expiry_date' => $item->expiry_date,
            'production_number' => $item->production_number,
        ];

        $minus = 4; // current stock - 4
        $this->decreaseStock($receiveItem->form, $this->warehouse, $item, $minus, $unit, $options);

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $this->dummyDataReceiveItem(), [$this->headers]);

        $response->assertStatus(422);

        $this->assertRegexp('/Stock(.*)not enough/', $response->json('message'));
    }

    /** @test */
    public function update_receive_item()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'approval_status' => 0,
        ], 'tenant');
    }

    /** @test */
    public function update_receive_item_archived()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(201);

        $this->assertTrue(! empty($receiveItem->form->edited_number));
    }

    /** @test */
    public function update_receive_item_pending()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(201);

        $transferItem = TransferItem::find($data['transfer_item_id']);
        $this->assertTrue(empty($transferItem->form->done));
    }

    /** @test */
    public function update_check_quantity_match()
    {
        $this->update_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $transferItem = TransferItem::orderBy('id', 'asc')->first();

        $this->assertEquals(
            $transferItem->items[0]->quantity,
            $receiveItem->items[0]->quantity
        );
    }

    /** @test */
    public function update_receive_item_check_balance()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        $response->assertStatus(201);

        $this->assertEquals(
            $response->json('data.items.0.balance'),
            (int) ($data['items'][0]['stock'] + $data['items'][0]['quantity'])
        );
    }

    /** @test */
    public function delete_receive_item_no_permission()
    {
        $this->create_receive_item();
        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function delete_receive_item_branch_not_default()
    {
        $this->create_receive_item();
        $this->unsetDefaultBranch();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default branch to save this form',
            ]);
    }

    /** @test */
    public function delete_receive_item_warehouse_not_default()
    {
        $this->create_receive_item();
        $this->unsetDefaultWarehouse();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'please set default warehouse to delete this form',
            ]);
    }

    /** @test */
    public function delete_receive_item_warehouse_not_found()
    {
        $this->setRole();

        $response = $this->json('DELETE', self::$path.'/10000000', [], [$this->headers]);

        $response->assertStatus(404)
            ->assertJson([
                'code' => 404,
                'message' => 'Model not found.',
            ]);
    }

    /** @test */
    public function delete_receive_item_stock_not_enough()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $item = $receiveItem->items[0]->item;
        $unit = $item->units[0];
        $options = [
            'expiry_date' => $item->expiry_date,
            'production_number' => $item->production_number,
        ];

        $minus = 4; // current stock - 4
        $this->decreaseStock($receiveItem->form, $this->warehouse, $item, $minus, $unit, $options);

        $response = $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $response->assertStatus(422);

        $this->assertRegexp('/Stock(.*)not enough/', $response->json('message'));
    }

    /** @test */
    public function delete_receive_item()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('forms', [
            'number' => $receiveItem->form->number,
            'cancellation_status' => 0,
        ], 'tenant');
    }

    /** @test */
    public function export_receive_item_success()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = [
            'data' => [
                'ids' => [$receiveItem->id],
                'date_start' => date('Y-m-d', strtotime('-1 days')),
                'date_end' => date('Y-m-d', strtotime('+1 days')),
                'tenant_name' => 'development'
            ]
        ];

        $response = $this->json('POST', self::$path.'/export', $data, $this->headers);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function send_receive_item_approval()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = [
            'id' => $receiveItem->id,
            'form_send_done' => 1,
            'crud_type' => 'update'
        ];

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/send', $data, $this->headers);
        
        $response->assertStatus(200);
    }
}
