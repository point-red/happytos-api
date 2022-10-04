<?php

namespace App\Http\Controllers\Api\Inventory\TransferItem;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\TransferItem\StoreTransferItemCustomerRequest;
use App\Http\Requests\Inventory\TransferItem\UpdateTransferItemCustomerRequest;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ApiResource;
use App\Model\Inventory\TransferItem\TransferItemCustomer;
use App\Helpers\Inventory\InventoryHelper;
use App\Helpers\Journal\JournalHelper;
use App\Exports\TransferItem\TransferItemCustomerSendExport;
use App\Model\CloudStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransferItemCustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return ApiCollection
     */
    public function index(Request $request)
    {
        $transferItemCustomers = TransferItemCustomer::from(TransferItemCustomer::getTableName().' as '.TransferItemCustomer::$alias)->eloquentFilter($request);
        
        $transferItemCustomers = TransferItemCustomer::joins($transferItemCustomers, $request->get('join'));
        
        $transferItemCustomers = pagination($transferItemCustomers, $request->get('limit'));
        
        return new ApiCollection($transferItemCustomers);
    }

    /**
     * Store a newly created resource in storage.
     * Request :
     *  - number (String)
     *  - date (String YYYY-MM-DD hh:mm:ss)
     *  - warehouse_id (Int)
     *  - customer_id (Int)
     *  - expedition_id (Int)
     *  - plat (String)
     *  - stnk (String)
     *  - phone (String)
     *  -
     *  - items (Array) :
     *      - item_id (Int)
     *      - item_name (String)
     *      - quantity (Decimal)
     *      - unit (String)
     *      - converter (Decimal)
     *
     * @param StoreTransferItemCustomerRequest $request
     * @return ApiResource
     * @throws Throwable
     */
    public function store(StoreTransferItemCustomerRequest $request)
    {
        $result = DB::connection('tenant')->transaction(function () use ($request) {
            $transferItemCustomer = TransferItemCustomer::create($request->all());
            $transferItemCustomer
                ->load('form')
                ->load('warehouse')
                ->load('items.item');

            return new ApiResource($transferItemCustomer);
        });

        return $result;
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param $id
     * @return ApiResource
     */
    public function show(Request $request, $id)
    {
        $transferItemCustomer = TransferItemCustomer::eloquentFilter($request)->findOrFail($id);

        return new ApiResource($transferItemCustomer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTransferItemCustomerRequest $request
     * @param int $id
     * @return ApiResource
     * @throws Throwable
     */
    public function update(UpdateTransferItemCustomerRequest $request, $id)
    {
        $transferItemCustomer = TransferItemCustomer::findOrFail($id);

        $result = DB::connection('tenant')->transaction(function () use ($request, $transferItemCustomer) {
            $transferItemCustomer->form->archive();
            $request['number'] = $transferItemCustomer->form->edited_number;
            $request['old_increment'] = $transferItemCustomer->form->increment;

            $transferItemCustomer = TransferItemCustomer::create($request->all());
            $transferItemCustomer
                ->load('form')
                ->load('warehouse')
                ->load('items.item');

            return new ApiResource($transferItemCustomer);
        });

        return $result;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        DB::connection('tenant')->beginTransaction();

        $transferItemCustomer = TransferItemCustomer::findOrFail($id);
        
        $transferItemCustomer->requestCancel($request);

        DB::connection('tenant')->commit();

        return response()->json([], 204);
    }

    /**
     * @param Request $request
     * @param $id
     * @return ApiResource
     */
    public function approve(Request $request, $id)
    {
        try {
            DB::connection('tenant')->beginTransaction();
    
            $transferItemCustomer = TransferItemCustomer::findOrFail($id);
            if ($transferItemCustomer->form->approval_status === 0) {
                $transferItemCustomer->form->approval_by = auth()->user()->id;
                $transferItemCustomer->form->approval_at = now();
                $transferItemCustomer->form->approval_status = 1;
                $transferItemCustomer->form->save();
                TransferItemCustomer::updateInventory($transferItemCustomer->form, $transferItemCustomer);
                TransferItemCustomer::updateJournal($transferItemCustomer);
            }

            DB::connection('tenant')->commit();

            return new ApiResource($transferItemCustomer);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return response()->json([
                'code' => 422,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @return ApiResource
     */
    public function cancellationApprove(Request $request, $id)
    {
        $transferItemCustomer = TransferItemCustomer::findOrFail($id);
        $transferItemCustomer->form->cancellation_approval_by = auth()->user()->id;
        $transferItemCustomer->form->cancellation_approval_at = now();
        $transferItemCustomer->form->cancellation_status = 1;
        $transferItemCustomer->form->save();

        JournalHelper::delete($transferItemCustomer->form->id);
        InventoryHelper::delete($transferItemCustomer->form->id);

        return new ApiResource($transferItemCustomer);
    }

    public function export(Request $request)
    {
        $request->validate([
            'data' => 'required',
        ]);
        
        $tenant = strtolower($request->header('Tenant'));

        $dateForm = date('d F Y', strtotime($request->data['date_start']));
        $dateTo = date('d F Y', strtotime($request->data['date_end']));
        
        $key = Str::random(16);
        $fileName = 'Transfer Item Customer_'.$dateForm.'-'.$dateTo;
        $fileExt = 'xlsx';
        $path = 'tmp/'.$tenant.'/'.$key.'.'.$fileExt;

        Excel::store(new TransferItemCustomerSendExport($request->data['date_start'], $request->data['date_end'], $request->data['ids'], $request->data['tenant_name']), $path, env('STORAGE_DISK'));

        $cloudStorage = new CloudStorage();
        $cloudStorage->file_name = $fileName;
        $cloudStorage->file_ext = $fileExt;
        $cloudStorage->feature = 'transfer item send';
        $cloudStorage->key = $key;
        $cloudStorage->path = $path;
        $cloudStorage->disk = env('STORAGE_DISK');
        $cloudStorage->owner_id = auth()->user()->id;
        $cloudStorage->download_url = env('API_URL').'/download?key='.$key;
        $cloudStorage->save();

        return response()->json([
            'data' => [
                'url' => $cloudStorage->download_url,
            ],
        ], 200);
    }
}
