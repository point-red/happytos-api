<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\UserActivity;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReceiveItemHistoryTest extends TestCase
{
    use ReceiveItemSetup;

    /** @test */
    public function create_receive_item()
    {
        $this->setRole();

        $data = $this->dummyDataReceiveItem();

        $this->json('POST', self::$path, $data, $this->headers);

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $data = [
            "id" => $receiveItem->id,
            "activity" => "Created"
        ];

        $response = $this->json('POST', self::$path . '/histories', $data, $this->headers);

        $response->assertStatus(201)
        ->assertJson([
            "data" => [
                "table_type" => "forms",
                "table_id" => $receiveItem->form->id,
                "number" => $receiveItem->form->number,
                "user_id" => $this->user->id,
                "activity" => "Created",
            ]

        ]);

        $this->assertDatabaseHas('user_activities', [
            'number' => $receiveItem->form->number,
            'table_id' => $receiveItem->form->id,
            'table_type' => "forms",
            "user_id" => $this->user->id,
            'activity' => 'Created'
        ], 'tenant');
    }

    /** @test */
    public function approve_receive_item()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $this->json('POST', self::$path.'/'.$receiveItem->id.'/approve', [
            'id' => $receiveItem->id,
            'form_send_done' => 1
        ], $this->headers);


        $data = $this->dummyDataReceiveItem();

        $this->json('POST', self::$path, $data, $this->headers);

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();
        $data = [
            "id" => $receiveItem->id,
            "activity" => "Approve"
        ];

        $response = $this->json('POST', self::$path . '/histories', $data, $this->headers);

        $response->assertStatus(201)
        ->assertJson([
            "data" => [
                "table_type" => "forms",
                "table_id" => $receiveItem->form->id,
                "number" => $receiveItem->form->number,
                "user_id" => $this->user->id,
                "activity" => "Approve",
            ]

        ]);

        $this->assertDatabaseHas('user_activities', [
            'number' => $receiveItem->form->number,
            'table_id' => $receiveItem->form->id,
            'table_type' => "forms",
            "user_id" => $this->user->id,
            'activity' => 'Approve'
        ], 'tenant');
    }

    /** @test */
    public function approve_receive_item_fullname()
    {
        $this->approve_receive_item();

        $history = UserActivity::orderBy('id', 'desc')->first();
        $historyUserName = $history->user->first_name.' '.$history->user->last_name;
        
        $fullName = $this->user->first_name.' '.$this->user->last_name;

        $this->assertEquals($fullName, $historyUserName);
    }

    /** @test */
    public function approve_receive_item_date_format()
    {
        $this->approve_receive_item();
        
        $history = UserActivity::orderBy('id', 'desc')->first();
        $historyDate = $this->getDate($history->date);
        
        $date = $this->getDate();

        $this->assertEquals($date, $historyDate);
    }

    /** @test */
    public function read_receive_item_histories_no_permission()
    {
        $this->create_receive_item();
        $this->unsetUserRole();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = [
            'sort_by' => '-user_activity.date',
            'includes' => 'user',
            'limit' => 10,
            'page' => 1
        ];

        $response = $this->json('GET', self::$path . '/' . $receiveItem->id . '/histories', $data, $this->headers);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'message' => 'Unauthorized',
            ]);
    }

    /** @test */
    public function read_receive_item_histories()
    {
        $this->create_receive_item();

        $receiveItem = ReceiveItem::orderBy('id', 'asc')->first();

        $data = [
            'sort_by' => '-user_activity.date',
            'includes' => 'user',
            'limit' => 10,
            'page' => 1
        ];

        $response = $this->json('GET', self::$path . '/' . $receiveItem->id . '/histories', $data, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                "data" => [
                    [
                        "table_type" => "forms",
                        "table_id" => $receiveItem->form->id,
                        "number" => $receiveItem->form->number,
                        "user_id" => $this->user->id,
                        "activity" => "Created",
                    ]
                ]
            ]);
    }
}