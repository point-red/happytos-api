<?php

namespace Tests\Feature\Http\Inventory\TransferItem;

use App\Helpers\Inventory\InventoryHelper;
use App\Imports\Template\ChartOfAccountImport;
use App\Model\Accounting\ChartOfAccount;
use App\Model\Auth\ModelHasRole;
use App\Model\Auth\Permission;
use App\Model\Auth\Role;
use App\Model\Auth\RoleHasPermission;
use App\Model\Form;
use App\Model\Inventory\TransferItem\TransferItem;
use App\Model\Master\Item;
use App\Model\Master\ItemUnit;
use App\Model\Master\User as TenantUser;
use App\Model\Master\Warehouse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

trait ReceiveItemSetup
{
    private $tenantUser;
    private $branchDefault;
    private $warehouse;
    private $unit;
    private $item;

    public static $path = '/api/v1/inventory/receive-items';

    public function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', -1);

        $this->signIn();
        $this->setProject();
        $this->importChartOfAccount();
        $this->insertPermissions();

        $this->tenantUser = TenantUser::find($this->user->id);
        $this->branchDefault = $this->tenantUser->branches()
            ->where('is_default', true)
            ->first();

        $this->setUserWarehouse($this->branchDefault);
        $this->createDistributionWareHouse();

        $_SERVER['HTTP_REFERER'] = 'http://www.example.com/';
    }

    public function tearDown(): void
    {
        parent::tearDown();
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

    private function createDistributionWareHouse()
    {
        Warehouse::firstOrCreate(['name' => 'DISTRIBUTION WAREHOUSE']);
    }

    private function insertPermissions()
    {
        $permissions = ['create', 'read', 'update', 'delete', 'approve'];
        $role = Role::createIfNotExists('super admin');

        foreach ($permissions as $permission) {
            $permission = Permission::createIfNotExists($permission.' transfer item');

            try {
                RoleHasPermission::forceCreate(['permission_id' => $permission->id, 'role_id' => $role->id]);
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
            }
        }
    }

    private function setUserWarehouse($branch = null)
    {
        $warehouse = $this->createWarehouse($branch);
        $this->tenantUser->warehouses()->syncWithoutDetaching($warehouse->id);
        foreach ($this->tenantUser->warehouses as $warehouse) {
            $warehouse->pivot->is_default = true;
            $warehouse->pivot->save();
    
            $this->warehouse = $warehouse;
        }
    }

    private function createWarehouse($branch = null)
    {
        $warehouse = new Warehouse();
        $warehouse->name = $this->faker->name;
        if ($branch) {
            $warehouse->branch_id = $branch->id;
        }
        $warehouse->save();

        return $warehouse;
    }

    protected function unsetUserRole()
    {
        $role = Role::createIfNotExists('super admin');

        ModelHasRole::where('role_id', $role->id)
            ->where('model_type', 'App\Model\Master\User')
            ->where('model_id', $this->user->id)
            ->delete();
    }

    protected function unsetDefaultBranch()
    {
        $this->branchDefault->pivot->is_default = false;
        $this->branchDefault->save();

        $this->tenantUser->branches()->detach($this->branchDefault->pivot->branch_id);
    }

    protected function unsetDefaultWarehouse()
    {
        $this->warehouse->pivot->is_default = false;
        $this->warehouse->pivot->save();
    }

    protected function increaseStock($warehouse, $item, $quantity, $unit)
    {
        $form = new Form();
        $form->date = date('Y-m-d H:i:s', time() - 3600);
        $form->created_by = $this->user->id;
        $form->updated_by = $this->user->id;
        $form->save();

        InventoryHelper::increase($form, $warehouse, $item, $quantity, $unit, 1);
    }

    protected function decreaseStock($form, $warehouse, $item, $quantity, $unit, $options)
    {
        InventoryHelper::decrease($form, $warehouse, $item, $quantity, $unit, 1, $options);
    }

    private function dummyDataTransferItem()
    {
        $coa = ChartOfAccount::orderBy('id', 'desc')->first();
        
        $item = new Item;
        $item->name = $this->faker->name;
        $item->chart_of_account_id = $coa->id;
        $item->save();

        $unit = factory(ItemUnit::class)->make();
        $item->units()->save($unit);

        $warehouse = factory(Warehouse::class)->create();

        $this->increaseStock($warehouse, $item, 10000, $unit);

        $data = [
            'date' => now()->timezone('Asia/Jakarta')->toDateTimeString(),
            'increment_group' => date('Ym'),
            'notes' => 'Some notes',
            'warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $this->warehouse->id,
            'driver' => 'Some one',
            'request_approval_to' => $this->user->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit' => 'PCS',
                    'converter' => 1,
                    'quantity' => 10,
                    'stock' => 30,
                    'balance' => 20,
                    'warehouse_id' => $warehouse->id,
                    'dna' => [
                        [
                            'quantity' => 10,
                            'item_id' => $item->id,
                            'expiry_date' => date('Y-m-d', strtotime('1 year')),
                            'production_number' => 'sample',
                            'remaining' => 30,
                        ],
                    ],
                ],
            ],
        ];

        return $data;
    }

    private function create_transfer_item()
    {
        $data = $this->dummyDataTransferItem();
        
        $this->json('POST', '/api/v1/inventory/transfer-items', $data, $this->headers);
    }

    private function approve_tranfer_item()
    {
        $transferItem = TransferItem::orderBy('id', 'asc')->first();

        $this->json('POST', '/api/v1/inventory/transfer-items/'.$transferItem->id.'/approve', [
            'id' => $transferItem->id,
            'form_send_done' => 1
        ], $this->headers);
    }

    private function dummyDataReceiveItem($createTransferItem = true)
    {
        if ($createTransferItem) {
            $this->create_transfer_item();
            $this->approve_tranfer_item();
        }

        $transferItem = TransferItem::orderBy('id', 'asc')->first();
        $from_warehouse = Warehouse::findOrFail($transferItem->warehouse_id);

        $data = [
            'date' => now()->timezone('Asia/Jakarta')->toDateTimeString(),
            'increment_group' => date('Ym'),
            'notes' => $transferItem->form->notes,
            'warehouse_id' => $this->warehouse->id,
            'from_warehouse_id' => $from_warehouse->id,
            'request_approval_to' => $transferItem->form->request_approval_to,
            'transfer_item_id' => $transferItem->id,
            'items' => [
                [
                    'item_id' => $transferItem->items[0]->item_id,
                    'item_name' => $transferItem->items[0]->item_name,
                    'unit' => $transferItem->items[0]->unit,
                    'converter' => $transferItem->items[0]->converter,
                    'quantity' => $transferItem->items[0]->quantity,
                    'stock' => 50,
                    'balance' => 60,
                    'warehouse_id' => $this->warehouse->id,
                    'expiry_date' => $transferItem->items[0]->expiry_date,
                    'production_number' => $transferItem->items[0]->production_number,
                ],
            ],
        ];

        return $data;
    }
}
