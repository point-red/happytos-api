<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Inventory\TransferItem\ReceiveItem;
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