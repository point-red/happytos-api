<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Accounting\Journal;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Master\Warehouse;
use App\Model\SettingJournal;
use Tests\TestCase;

class ReceiveItemTest extends TestCase
{
    use ReceiveItemSetup;

    /** @test */
    public function create_receive_item_no_permission()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->signIn();

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
    public function create_receive_item_quantity_bigger_then_send_item()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['items'][0]['quantity'] = 1000000000;

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.quantity' => [
                        'The quantity cannot be greater than the quantity of the transfer item.',
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
    public function create_receive_item_journal_not_created()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->json('POST', self::$path, $data, $this->headers);

        $journal = SettingJournal::where('feature', 'transfer item')
            ->where('name', 'inventory in distribution')
            ->first();

        $journal->chart_of_account_id = null;
        $journal->save();

        // approve create
        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Journal transfer item account - inventory in distribution not found'
            ]);
    }

    /** @test */
    public function create_receive_item_stock_warehouse_not_update()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->json('POST', self::$path, $data, $this->headers);

        // approve create
        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $receiveItem->items[0]->quantity = 1;
        $receiveItem->items[0]->save();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Stock not updated in '.$this->warehouse->name,
            ]);
    }

    /** @test */
    public function create_receive_item_not_balance()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['items'][0]['balance'] = 9999;

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.balance' => [
                        'The balance value does not match.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function create_receive_item_unit_not_default()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['items'][0]['unit'] = 'unit123';

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.unit' => [
                        'The selected items.0.unit is invalid.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function create_receive_item_not_same_warehouse_send()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $transferItem = TransferItem::orderBy('id', 'asc')->first();
        $transferItem->warehouse_id = factory(Warehouse::class)->create()->id;
        $transferItem->to_warehouse_id = factory(Warehouse::class)->create()->id;
        $transferItem->save();

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'warehouse_id' => [
                        'warehouse of "transfer item" (to_warehouse_id) is not the same with warehouse of "receive item" (warehouse_id)',
                    ],
                    'from_warehouse_id' => [
                        'warehouse of "transfer item" (warehouse_id) is not the same with warehouse of "receive item" (from_warehouse_id)',
                    ],
                ],
            ]);
    }

    /** @test */
    public function create_receive_item_unique_field()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $transferItem = TransferItem::orderBy('id', 'asc')->first();

        $formTransfer = $transferItem->form;
        $formReceive = $receiveItem->form;
        
        try {
            $formReceive->number = $formTransfer->number;
            $formReceive->save();
        } catch (\Throwable $th) {
            $this->assertStringContainsString('Integrity constraint violation: 1062 Duplicate entry', $th->getMessage());
        }
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
    public function create_receive_item_relace_multiple_whitespaces_in_notes()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['notes'] = ' This is  a  notes ';

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(201);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
        ], 'tenant');

        // approve create
        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->assertEquals($receiveItem->form->notes, 'This is a notes');
    }

    /** @test */
    public function create_receive_item_check_quantity_match()
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
    public function create_receive_item_debit_credit_same_value()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $journals = Journal::where('form_id', '=', $receiveItem->form->id)->get();

        $debit = 0;
        $credit = 0;

        foreach ($journals as $journal) {
            if (! empty($journal->credit)) {
                $credit = $journal->credit;
            } else {
                $debit = $journal->debit;
            }
        }

        $this->assertEmpty($debit, $credit);
    }

    /** @test */
    public function create_receive_item_hpp_quantity()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $journals = Journal::where('form_id', '=', $receiveItem->form->id)->get();

        $item = $receiveItem->items[0];
        $amount = $item->item->cogs($item->item_id) * $item->quantity;
        $debit = 0;
        $credit = 0;

        foreach ($journals as $journal) {
            if (! empty($journal->credit)) {
                $credit = $journal->credit;
            } else {
                $debit = $journal->debit;
            }
        }

        $this->assertEmpty($debit, $amount);
        $this->assertEmpty($credit, $amount);
    }

    /** @test */
    public function create_receive_item_transfer_item_done()
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

        $transferItem = TransferItem::find($receiveItem->transfer_item_id);
        $this->assertTrue(! empty($transferItem->form->done));
    }

    /** @test */
    public function create_receive_item_transfer_item_still_pending()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();
        $data['items'][0]['quantity'] = 2;
        $data['items'][0]['stock'] = 58;

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
            'form_send_done' => 0
        ], $this->headers);

        $transferItem = TransferItem::find($receiveItem->transfer_item_id);
        $this->assertTrue(empty($transferItem->form->done));
    }

    /** @test */
    public function create_receive_item_decrease_quantity_warehouse_send()
    {
        $this->setRole();
        $this->create_transfer_item();

        $transferItem = TransferItem::orderBy('id', 'asc')->first();
        $item = $transferItem->items[0]->item;
        $options = [
            'expiry_date' => $transferItem->items[0]->expiry_date,
            'production_number' => $transferItem->items[0]->production_number,
        ];

        $firstStock = $this->getStock($item, $transferItem->warehouse, $options);

        $this->approve_tranfer_item();

        // create
        $data = $this->dummyDataReceiveItem(false);

        $this->json('POST', self::$path, $data, $this->headers);

        // approve create
        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        $currentStock = $this->getStock($item, $transferItem->warehouse, $options);

        $this->assertEquals((int) $currentStock, ((int) $firstStock - $transferItem->items[0]->quantity));
    }

    /** @test */
    public function create_receive_item_increase_quantity_warehouse_receive()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->json('POST', self::$path, $data, $this->headers);

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $item = $receiveItem->items[0]->item;
        $options = [
            'expiry_date' => $receiveItem->items[0]->expiry_date,
            'production_number' => $receiveItem->items[0]->production_number,
        ];

        $firstStock = $this->getStock($item, $this->warehouse, $options);

        // approve create
        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        $currentStock = $this->getStock($item, $this->warehouse, $options);

        $this->assertEquals((int) $currentStock, ((int) $firstStock + $receiveItem->items[0]->quantity));
    }

    /** @test */
    public function read_receive_item_all_no_permissions()
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
    public function read_receive_item_single_no_permissions()
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
    public function read_receive_item_all()
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
    public function read_receive_item_single()
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
    public function update_receive_item_quantity_bigger_then_send_item()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;
        $data['items'][0]['quantity'] = 1000000000;

        $response = $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'items.0.quantity' => [
                        'The quantity cannot be greater than the quantity of the transfer item.',
                    ],
                ],
            ]);
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
    public function update_receive_item_check_quantity_match()
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
    public function update_receive_item_decrease_stock_warehouse_send()
    {
        $this->setRole();
        $this->create_transfer_item();

        $transferItem = TransferItem::orderBy('id', 'asc')->first();
        $item = $transferItem->items[0]->item;
        $options = [
            'expiry_date' => $transferItem->items[0]->expiry_date,
            'production_number' => $transferItem->items[0]->production_number,
        ];

        $firstStock = $this->getStock($item, $transferItem->warehouse, $options);

        $this->approve_tranfer_item();

        // create
        $data = $this->dummyDataReceiveItem(false);

        $this->json('POST', self::$path, $data, $this->headers);

        // approve create
        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        // update
        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;
        $data['items'][0]['quantity'] = 5;

        $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        // approve update
        $receiveItem = ReceiveItem::orderBy('id', 'desc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        $currentStock = $this->getStock($item, $transferItem->warehouse, $options);

        $this->assertEquals((int) $currentStock, ((int) $firstStock - $transferItem->items[0]->quantity));
    }

    /** @test */
    public function update_receive_item_increase_stock_warehouse_receive()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->json('POST', self::$path, $data, $this->headers);

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $item = $receiveItem->items[0]->item;
        $options = [
            'expiry_date' => $receiveItem->items[0]->expiry_date,
            'production_number' => $receiveItem->items[0]->production_number,
        ];

        $firstStock = $this->getStock($item, $this->warehouse, $options);

        // approve create
        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        // update
        $data = $this->dummyDataReceiveItem();
        $data['id'] = $receiveItem->id;
        $data['items'][0]['quantity'] = 5;

        $this->json('PATCH', self::$path.'/'.$receiveItem->id, $data, [$this->headers]);

        // approve update
        $receiveItem = ReceiveItem::orderBy('id', 'desc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);

        $currentStock = $this->getStock($item, $this->warehouse, $options);

        $this->assertEquals((int) $currentStock, ((int) $firstStock + $receiveItem->items[0]->quantity));
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

        $this->assertEmpty($receiveItem->form->cancellation_status);
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
