<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use Tests\TestCase;

class TransferItemCustomerHistoryTest extends TestCase
{
    use TransferItemCustomerSetup;
    
    public static $path = '/api/v1/inventory/transfer-item-customers';

    /** @test */
    public function read_transfer_item_customer_histories()
    {
        $transferItem = $this->createTransferItemCustomer();

        $data_history = [
            "id" => $transferItem->id,
            "activity" => "Created"
        ];

        $response = $this->json('POST', self::$path . '/histories', $data_history, $this->headers);

        $data = [
            'sort_by' => '-user_activity.date',
            'includes' => 'user',
            'limit' => 10,
            'page' => 1
        ];

        $response = $this->json('GET', self::$path . '/' . $transferItem->id . '/histories', $data, $this->headers);
        
        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    [
                        "table_type" => "forms",
                        "table_id" => $transferItem->form->id,
                        "number" => $transferItem->form->number,
                        "user_id" => $this->user->id,
                        "activity" => "Created",
                    ]
                ]
            ]);
    }

    /** @test */
    public function success_create_transfer_item_customer_history()
    {
        $transferItem = $this->createTransferItemCustomer();

        $data = [
            "id" => $transferItem->id,
            "activity" => "Printed"
        ];

        $response = $this->json('POST', self::$path . '/histories', $data, $this->headers);
        
        $response->assertStatus(201)
        ->assertJson([
            "data" => [
                "table_type" => "forms",
                "table_id" => $transferItem->form->id,
                "number" => $transferItem->form->number,
                "user_id" => $this->user->id,
                "activity" => "Printed",
            ]
            
        ]);

        $this->assertDatabaseHas('user_activities', [
            'number' => $transferItem->form->number,
            'table_id' => $transferItem->form->id,
            'table_type' => "forms",
            "user_id" => $this->user->id,
            'activity' => 'Printed'
        ], 'tenant');
    }
}
