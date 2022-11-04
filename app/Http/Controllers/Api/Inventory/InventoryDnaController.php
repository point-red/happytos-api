<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Model\Inventory\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryDnaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @param $itemId
     * @return ApiCollection
     */
    public function index(Request $request, $itemId)
    {
        $warehouseId = $request->get('warehouse_id');
        $inventories = Inventory::selectRaw('*, sum(quantity) as remaining')
            ->groupBy(['item_id', 'production_number', 'expiry_date'])
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->having('remaining', '>', 0)
            ->get();

        return new ApiCollection($inventories);
    }

    public function allDna(Request $request, $itemId)
    {
        $warehouseId = $request->get('warehouse_id');
        $inventories = Inventory::selectRaw('*, sum(quantity) as remaining')
            ->groupBy(['item_id', 'production_number', 'expiry_date'])
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->get();

        return new ApiCollection($inventories);
    }

    public function editDna(Request $request, $itemId)
    {
        $warehouseId = $request->get('warehouse_id');
        $form = json_decode($request->get('formable'), true);
            
        $inventories_feature = Inventory::whereHas('form', function($q) use($form){
            $q->where('formable_type', $form['formable_type'])
                ->where('formable_id', $form['formable_id']);
            })->first();
        
        if ($inventories_feature) {
            $inventories = Inventory::selectRaw('inventories.*, sum(inventories.quantity) + COALESCE(abs(inventories_feature.quantity), 0) as remaining')
                ->leftjoin(Inventory::getTableName().' as inventories_feature', function ($q) use($inventories_feature) {
                    $q->on('inventories_feature.item_id', '=', 'inventories.item_id')
                    ->where('inventories_feature.production_number', DB::raw('inventories.production_number'))
                    ->where('inventories_feature.expiry_date', DB::raw('inventories.expiry_date'))
                    ->where('inventories_feature.form_id', $inventories_feature->form_id);
                })
                ->groupBy(['inventories.item_id', 'inventories.production_number', 'inventories.expiry_date'])
                ->where('inventories.item_id', $itemId)
                ->where('inventories.warehouse_id', $warehouseId)
                ->having('remaining', '>', 0)
                ->get();
        } else {
            $inventories = Inventory::selectRaw('*, sum(quantity) as remaining')
            ->groupBy(['item_id', 'production_number', 'expiry_date'])
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->having('remaining', '>', 0)
            ->get();
        }


        return new ApiCollection($inventories);
    }
}
