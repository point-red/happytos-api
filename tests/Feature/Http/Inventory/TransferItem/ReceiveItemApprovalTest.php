<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Inventory\TransferItem\ReceiveItem;
use Tests\TestCase;

class ReceiveItemApprovalTest extends TestCase
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
    public function approve_receive_item_no_permission()
    {
        $this->update_receive_item();
        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
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
    public function approve_receive_item()
    {
        $this->update_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'approval_status' => 1,
        ], 'tenant');
    }

    /** @test */
    public function reject_receive_item_no_permission()
    {
        $this->update_receive_item();
        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/reject', [
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
    public function reject_receive_item_no_reason()
    {
        $this->update_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/reject', [
            'id' => $receiveItem->id,
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
    public function reject_receive_item_reason_long_text()
    {
        $this->update_receive_item();

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
    public function reject_receive_item()
    {
        $this->update_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$receiveItem->id.'/reject', [
            'id' => $receiveItem->id,
            'reason' => 'some reason'
        ], $this->headers);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('forms', [
            'id' => $response->json('data.form.id'),
            'number' => $response->json('data.form.number'),
            'approval_status' => -1,
        ], 'tenant');
    }
}
