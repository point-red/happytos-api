<?php

namespace App\Http\Controllers\Api\Inventory\TransferItem;

use App\Http\Controllers\Controller;
use App\Model\Inventory\TransferItem\TransferItemCustomer;
use App\Model\UserActivity;
use App\Model\Form;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;

class TransferItemCustomerHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return ApiCollection
     */
    public function index(Request $request, $id)
    {
        $transferItemCustomer = TransferItemCustomer::findOrFail($id);
        $form_number = $transferItemCustomer->form->number;
        
        $histories = UserActivity::from(UserActivity::getTableName().' as '.UserActivity::$alias)->eloquentFilter($request);
        
        $histories = $histories->where(UserActivity::$alias.'.number', $form_number);

        $histories = $histories->join(Form::getTableName().' as '.Form::$alias, function ($q) {
            $q->on(Form::$alias.'.id', '=', UserActivity::$alias.'.table_id');
        });

        $histories = $histories->select(UserActivity::$alias.'.*', Form::$alias.'.formable_id');

        $histories = pagination($histories, $request->limit ?: 10);

        return new ApiCollection($histories);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return ApiResource
     */
    public function store(Request $request)
    {
        if ($request->id) {
            $transferItemCustomer = TransferItemCustomer::findOrFail($request->id);

            if ($request->activity == 'Update') {
                $userActivity = UserActivity::where('number', $transferItemCustomer->form->edited_number);
                $userActivity = $userActivity->where('activity', 'like', '%' . 'Update' . '%');
                $updateNumber = $userActivity->count() + 1;
                $activity = $request->activity . ' - ' . $updateNumber;
                $number = $transferItemCustomer->form->edited_number;
            } else {
                $activity = $request->activity;
                $number = $transferItemCustomer->form->number;
            }

            // Insert User Activity
            $userActivity = new UserActivity;
            $userActivity->table_type = 'forms';
            $userActivity->table_id = $transferItemCustomer->form->id;
            $userActivity->number = $number;
            $userActivity->date = now();
            $userActivity->user_id = auth()->user()->id;
            $userActivity->activity = $activity;
            $userActivity->save();
        };

        if ($request->ids) {
            $transferItemCustomers = TransferItemCustomer::whereIn('id', $request->ids)->get();

            // Insert User Activity
            foreach ($transferItemCustomers as $transferItemCustomer) {
                $userActivity = new UserActivity;
                $userActivity->table_type = 'forms';
                $userActivity->table_id = $transferItemCustomer->form->id;
                $userActivity->number = $request->activity == 'Update' ? $transferItemCustomer->form->edited_number : $transferItemCustomer->form->number;
                $userActivity->date = now();
                $userActivity->user_id = auth()->user()->id;
                $userActivity->activity = $request->activity;
                $userActivity->save();
            }
        };

        return new ApiResource($userActivity);
    }
}
