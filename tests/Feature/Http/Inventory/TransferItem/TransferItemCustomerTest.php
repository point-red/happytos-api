<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Imports\Template\ChartOfAccountImport;
use App\Model\Master\Item;
use App\Model\Master\User as TenantUser;
use App\Model\Master\Warehouse;
use App\Model\Master\Customer;
use App\Model\Master\Expedition;
use App\Model\Accounting\ChartOfAccount;
use App\Model\Form;
use App\Model\Inventory\TransferItem\TransferItemCustomer;
use App\Helpers\Inventory\InventoryHelper;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TransferItemCustomerTest extends TestCase
{
    public static $path = '/api/v1/inventory/transfer-item-customers';

    public function setUp(): void
    {
        parent::setUp();

        $this->signIn();
        $this->setProject();
        $this->importChartOfAccount();
    }

    private function importChartOfAccount()
    {
        Excel::import(new ChartOfAccountImport(), storage_path('template/chart_of_accounts_manufacture.xlsx'));


        $this->artisan('db:seed', [
            '--database' => 'tenant',
            '--class' => 'SettingJournalSeeder',
            '--force' => true,
        ]);
    }

    public function dummyData($item = null)
    {
        if (!$item) {
            $item = factory(Item::class)->create();
        }

        $warehouse = factory(Warehouse::class)->create();

        $customer = factory(Customer::class)->create();
        $expedition = factory(Expedition::class)->create();

        $user = new TenantUser;
        $user->name = $this->faker->name;
        $user->address = $this->faker->address;
        $user->phone = $this->faker->phoneNumber;
        $user->email = $this->faker->email;
        $user->save();

        $form = new Form;
        $form->date = now()->toDateTimeString();
        $form->created_by = $this->user->id;
        $form->updated_by = $this->user->id;
        $form->save();

        $options = [];
        if ($item->require_expiry_date) {
            $options['expiry_date'] = $item->expiry_date;
        }
        if ($item->require_production_number) {
            $options['production_number'] = $item->production_number;
        }

        $options['quantity_reference'] = $item->quantity;
        $options['unit_reference'] = $item->unit;
        $options['converter_reference'] = $item->converter;

        InventoryHelper::increase($form, $warehouse, $item, 100, "PCS", 1, $options);
        
        $data = [
            "date" => now()->timezone('Asia/Jakarta')->toDateTimeString(),
            "increment_group" => date("Ym"),
            "notes" => "Some notes",
            "warehouse_id" => $warehouse->id,
            "customer_id" => $customer->id,
            "expedition_id" => $expedition->id,
            "plat" => "AB 123 H",
            "stnk" => "83723",
            "phone" => "085847837473",
            "request_approval_to" => $user->id,
            "items" => [
                [
                    "item_id" => $item->id,
                    "item_name" => $item->name,
                    "unit" => "PCS",
                    "converter" => 1,
                    "quantity" => 10,
                    "stock" => 100,
                    "balance" => 80,
                    "warehouse_id" => $warehouse->id,
                    'dna' => [
                        [
                            "quantity" => 10,
                            "item_id" => $item->id,
                            "expiry_date" => date('Y-m-d', strtotime('1 year')),
                            "production_number" => "sample",
                            "remaining" => 100,
                        ]
                    ]
                ]
            ]
        ];

        return $data;
    }

    /** @test */
    public function create_transfer_item_customer()
    {
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(201);
    }

    /**
     * @test 
     */
    public function read_all_transfer_item_customer()
    {
        $response = $this->json('GET', self::$path, [
            'join' => 'form,items,item',
            'fields' => 'transfer_sent_customer.*',
            'group_by' => 'form.id',
            'sort_by' => '-form.number',
        ], $this->headers);

        $response->assertStatus(200);
    }

    /**
     * @test 
     */
    public function read_single_transfer_item_customer()
    {
        $this->create_transfer_item_customer();

        $transferItemCustomer = TransferItemCustomer::orderBy('id', 'asc')->first();

        $response = $this->json('GET', self::$path.'/'.$transferItemCustomer->id, [
            'includes' => 'warehouse;customer;expedition;items.item;form.createdBy;form.requestApprovalTo;form.branch'
        ], $this->headers);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function update_transfer_item_customer()
    {
        $this->create_transfer_item_customer();

        $transferItemCustomer = TransferItemCustomer::orderBy('id', 'asc')->first();

        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $data["id"] = $transferItemCustomer->id;

        $response = $this->json('PATCH', self::$path.'/'.$transferItemCustomer->id, $data, [$this->headers]);

        $response->assertStatus(201);
    }

    /** @test */
    public function delete_transfer_item_customer()
    {
        $this->create_transfer_item_customer();

        $transferItemCustomer = TransferItemCustomer::orderBy('id', 'asc')->first();

        $response = $this->json('DELETE', self::$path.'/'.$transferItemCustomer->id, [], [$this->headers]);

        $response->assertStatus(204);
    }

    /**
     * @test 
     */
    public function approve_transfer_item_customer()
    {
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $this->json('POST', self::$path, $data, $this->headers);

        $transferItemCustomer = TransferItemCustomer::orderBy('id', 'asc')->first();

        $response = $this->json('POST', self::$path.'/'.$transferItemCustomer->id.'/approve', [
            'id' => $transferItemCustomer->id
        ], $this->headers);
        
        $response->assertStatus(200);
    }

    /**
     * @test 
     */
    public function cancellation_transfer_item_customer()
    {
        $coa = ChartOfAccount::orderBy('id', 'asc')->first();

        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $data = $this->dummyData($item);

        $this->json('POST', self::$path, $data, $this->headers);

        $transferItemCustomer = TransferItemCustomer::orderBy('id', 'desc')->first();

        $response = $this->json('POST', self::$path.'/'.$transferItemCustomer->id.'/cancellation-approve', [], $this->headers);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function export_transfer_item_customer()
    {
        $this->create_transfer_item_customer();

        $transferItemCustomer = TransferItemCustomer::orderBy('id', 'asc')->first();

        $data = [
            "data" => [
                "ids" => [$transferItemCustomer->id],
                "date_start" => date("Y-m-d", strtotime("-1 days")),
                "date_end" => date("Y-m-d", strtotime("+1 days")),
                "tenant_name" => "development"
            ]
        ];

        $response = $this->json('POST', self::$path.'/export', $data, $this->headers);
        
        $response->assertStatus(200);
    }
}
