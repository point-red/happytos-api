<?php 

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Model\Master\Item;
use App\Model\Master\User as TenantUser;
use App\Model\Master\Warehouse;
use App\Model\Master\Customer;
use App\Model\Master\Expedition;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Template\ChartOfAccountImport;
use App\Model\Form;
use App\Model\Inventory\TransferItem\TransferItemCustomer;
use App\Helpers\Inventory\InventoryHelper;
use App\Model\Accounting\ChartOfAccount;

trait TransferItemCustomerSetup {
  private $tenantUser;
  private $branchDefault;
  private $warehouseSelected;
  private $unit;
  private $item;
  private $customer;
  private $approver;
  private $coa;

  public function setUp(): void
  {
    parent::setUp();

    $this->signIn();
    $this->setProject();
    $this->importChartOfAccount();

    $this->tenantUser = TenantUser::find($this->user->id);
    $this->branchDefault = $this->tenantUser->branches()
        ->where('is_default', true)
        ->first();

    $this->setUpTransferItemPermission();
    $_SERVER['HTTP_REFERER'] = 'http://www.example.com/';
  }

  protected function setUpTransferItemPermission()
  {
    \App\Model\Auth\Permission::createIfNotExists('create transfer item');
    \App\Model\Auth\Permission::createIfNotExists('update transfer item');
    \App\Model\Auth\Permission::createIfNotExists('delete transfer item');
    \App\Model\Auth\Permission::createIfNotExists('read transfer item');
    \App\Model\Auth\Permission::createIfNotExists('approve transfer item');
  }

  protected function setCreatePermission()
  {
    $permission = \App\Model\Auth\Permission::where('name', 'create transfer item')->first();
    $hasPermission = new \App\Model\Auth\ModelHasPermission();
    $hasPermission->permission_id = $permission->id;
    $hasPermission->model_type = 'App\Model\Master\User';
    $hasPermission->model_id = $this->user->id;
    $hasPermission->save();
  }

  protected function setUpdatePermission()
  {
    $permission = \App\Model\Auth\Permission::where('name', 'update transfer item')->first();
    $hasPermission = new \App\Model\Auth\ModelHasPermission();
    $hasPermission->permission_id = $permission->id;
    $hasPermission->model_type = 'App\Model\Master\User';
    $hasPermission->model_id = $this->user->id;
    $hasPermission->save();
  }

  protected function setDeletePermission()
  {
    $permission = \App\Model\Auth\Permission::where('name', 'delete transfer item')->first();
    $hasPermission = new \App\Model\Auth\ModelHasPermission();
    $hasPermission->permission_id = $permission->id;
    $hasPermission->model_type = 'App\Model\Master\User';
    $hasPermission->model_id = $this->user->id;
    $hasPermission->save();
  }

  protected function setReadPermission()
  {
    $permission = \App\Model\Auth\Permission::where('name', 'read transfer item')->first();
    $hasPermission = new \App\Model\Auth\ModelHasPermission();
    $hasPermission->permission_id = $permission->id;
    $hasPermission->model_type = 'App\Model\Master\User';
    $hasPermission->model_id = $this->user->id;
    $hasPermission->save();
  }

  protected function setApprovePermission()
  {
    $permission = \App\Model\Auth\Permission::where('name', 'approve transfer item')->first();
    $hasPermission = new \App\Model\Auth\ModelHasPermission();
    $hasPermission->permission_id = $permission->id;
    $hasPermission->model_type = 'App\Model\Master\User';
    $hasPermission->model_id = $this->user->id;
    $hasPermission->save();
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

  protected function unsetDefaultBranch()
  {
      $this->branchDefault->pivot->is_default = false;
      $this->branchDefault->save();

      $this->tenantUser->branches()->detach($this->branchDefault->pivot->branch_id);
  }

  public function dummyData($item = null)
  {
      if (!$item) {
        $item = factory(Item::class)->create();
      }
      
      $warehouse = factory(Warehouse::class)->create();

      $customer = factory(Customer::class)->create();
      $expedition = factory(Expedition::class)->create();

      $distribution_warehouse = new Warehouse();
      $distribution_warehouse->name = 'DISTRIBUTION WAREHOUSE';
      $distribution_warehouse->save();

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
          "request_approval_to" => $this->user->id,
          "items" => [
              [
                  "item_id" => $item->id,
                  "item_name" => $item->name,
                  "unit" => "PCS",
                  "converter" => 1,
                  "quantity" => 10,
                  "stock" => 100,
                  "balance" => 90,
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

  public function createTransferItemCustomer()
  {
      $this->setCreatePermission();

      $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
      $item = new Item;
      $item->name = $this->faker->name;
      $item->chart_of_account_id = $coa->id;
      $item->save();

      $data = $this->dummyData($item);

      $response = $this->json('POST', '/api/v1/inventory/transfer-item-customers', $data, $this->headers);

      $TransferItemCustomer = TransferItemCustomer::where('id', $response->json('data')["id"])->first();
      
      return $TransferItemCustomer;
  }
}