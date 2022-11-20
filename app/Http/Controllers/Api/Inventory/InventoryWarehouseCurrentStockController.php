<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Model\Inventory\Inventory;
use App\Helpers\Inventory\InventoryHelper;
use App\Model\Master\Item;
use App\Model\Master\Warehouse;
use Illuminate\Http\Request;

class InventoryWarehouseCurrentStockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return ApiCollection
     */
    public function index(Request $request)
    {
        $warehouseId = $request->get('warehouse_id');
        $itemId = $request->get('item_id');

        if ($request->get('formable')){
            $form = json_decode($request->get('formable'), true);
                
            $inventoriesForm = Inventory::whereHas('form', function($q) use($form){
                $q->where('formable_type', $form['formable_type'])
                    ->where('formable_id', $form['formable_id']);
                })->first();

                
            if ($inventoriesForm) {
                $inventoriesFeature = Inventory::selectRaw('item_id, abs(sum(inventories.quantity)) as quantity')
                    ->where('form_id', $inventoriesForm->form_id)
                    ->where('warehouse_id', $warehouseId)
                    ->groupBy('item_id');
                    
                $inventories = Inventory::selectRaw('inventories.*, sum(inventories.quantity) + COALESCE(abs(inventories_feature.quantity), 0) as remaining')
                    ->leftjoinSub($inventoriesFeature, 'inventories_feature', function ($q) {
                        $q->on('inventories.item_id', '=', 'inventories_feature.item_id');
                    })
                    ->where('inventories.item_id', $itemId)
                    ->where('inventories.warehouse_id', $warehouseId)
                    ->having('remaining', '>', 0);
            } else {
                $inventories = Inventory::selectRaw('*, sum(quantity) as remaining')
                ->where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->having('remaining', '>', 0);
            }
    
            $stock = $inventories->first() != null ? $inventories->first()->remaining : 0;
            return response()->json($stock, 200);
        } else {
            if ($request->expiry_date or $request->production_number) {
                $options = [
                    'expiry_date' => $request->expiry_date,
                    'production_number' => $request->production_number,
                ];
                $item = Item::where('id', $request->item_id)->first();
                $warehouse = Warehouse::where('id', $request->warehouse_id)->first();
                $stock = InventoryHelper::getCurrentStock($item, convert_to_server_timezone(now()), $warehouse, $options);

                return response()->json($stock, 200);
            } else {
                $inventories = Inventory::join('forms', 'forms.id', '=', 'inventories.form_id')
                ->selectRaw('inventories.*, sum(quantity) as remaining')
                ->where('item_id', $request->item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->having('remaining', '>', 0);
                $stock = $inventories->first() != null ? $inventories->first()->remaining : 0;
                
                return response()->json($stock, 200);
            }
        }
    }
}
