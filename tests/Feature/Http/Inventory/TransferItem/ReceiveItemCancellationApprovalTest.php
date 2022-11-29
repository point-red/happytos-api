<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Accounting\Journal;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Master\Warehouse;
use Tests\TestCase;

class ReceiveItemCancellationApprovalTest extends TestCase
{
    use ReceiveItemSetup;

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
    public function delete_receive_item()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $response->assertStatus(204);
    }

    /** @test */
    public function approve_cancel_receive_item_no_permission()
    {
        $this->delete_receive_item();
        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function approve_cancel_receive_item_already_canceled()
    {
        $this->delete_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        // first approve cancel
        $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [], $this->headers);

        // second approve cancel
        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'This form has been canceled',
            ]);
    }

    /** @test */
    public function approve_cancel_receive_item()
    {
        $this->delete_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [], $this->headers);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'cancellation_status' => 1,
        ], 'tenant');

        $transferItem = TransferItem::find($receiveItem->transfer_item_id);
        $this->assertTrue(empty($transferItem->form->done));
    }

    /** @test */
    public function delete_receive_item_increase_stock_warehouse_distribution()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $transferItem = TransferItem::orderBy('id', 'asc')->first();

        $quantity = $receiveItem->items[0]->quantity;
        $item = $transferItem->items[0]->item;
        $options = [
            'expiry_date' => $transferItem->items[0]->expiry_date,
            'production_number' => $transferItem->items[0]->production_number,
        ];

        $warehouse = Warehouse::where('name', 'DISTRIBUTION WAREHOUSE')->first();

        $firstStock = $this->getStock($item, $warehouse, $options);

        $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [], $this->headers);

        $currentStock = $this->getStock($item, $warehouse, $options);

        $this->assertEquals((int) $currentStock, ((int) $firstStock + $quantity));
    }

    /** @test */
    public function delete_receive_item_decrease_stock_warehouse_receive()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $quantity = $receiveItem->items[0]->quantity;
        $item = $receiveItem->items[0]->item;
        $options = [
            'expiry_date' => $receiveItem->items[0]->expiry_date,
            'production_number' => $receiveItem->items[0]->production_number,
        ];

        $firstStock = $this->getStock($item, $this->warehouse, $options);

        $this->json('DELETE', self::$path.'/'.$receiveItem->id, [], [$this->headers]);

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [], $this->headers);

        $currentStock = $this->getStock($item, $this->warehouse, $options);

        $this->assertEquals((int) $currentStock, ((int) $firstStock - $quantity));
    }

    /** @test */
    public function approve_cancel_receive_item_journal_deleted()
    {
        $this->delete_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-approve', [], $this->headers);
        
        $this->assertTrue(empty(Journal::where('form_id', '=', $receiveItem->form->id)->delete()));
    }

    /** @test */
    public function reject_cancel_receive_item_no_permission()
    {
        $this->delete_receive_item();
        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-reject', [
            'id' => $receiveItem->id,
            'reason' => 'some reason'
        ], $this->headers);
        
        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function reject_cancel_receive_item_no_reason()
    {
        $this->delete_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-reject', [
            'id' => $receiveItem->id
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'reason' => ['The reason field is required.'],
                ]
            ]);
    }

    /** @test */
    public function reject_cancel_receive_item_reason_long_text()
    {
        $this->delete_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/reject', [
            'id' => $receiveItem->id,
            'reason' => $this->faker->words(256)
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'reason' => [
                        'The reason may not be greater than 255 characters.',
                    ],
                ]
            ]);
    }

    /** @test */
    public function reject_cancel_receive_item()
    {
        $this->delete_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/cancellation-reject', [
            'id' => $receiveItem->id,
            'reason' => 'some reason'
        ], $this->headers);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'cancellation_status' => -1,
        ], 'tenant');
    }
}