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
        $date_form = convert_to_server_timezone($request->date_form, 'Asia/Jakarta');
        if ($request->expiry_date or $request->production_number) {
            $options = [
                'expiry_date' => $request->expiry_date,
                'production_number' => $request->production_number,
            ];
            $item = Item::where('id', $request->item_id)->first();
            $warehouse = Warehouse::where('id', $request->warehouse_id)->first();
            $stock = InventoryHelper::getCurrentStock($item, $date_form, $warehouse, $options);
            return response()->json($stock, 200);
        } else {
            $inventories = Inventory::join('forms', 'forms.id', '=', 'inventories.form_id')
            ->selectRaw('inventories.*, sum(quantity) as remaining')
            ->where('item_id', $request->item_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->where('forms.date', '<', $date_form)
            ->having('remaining', '>', 0);
            $stock = $inventories->first() != null ? $inventories->first()->remaining : 0;
            
            return response()->json($stock, 200);
        }
    }
}
