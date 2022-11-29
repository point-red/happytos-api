<?php

namespace App\Http\Controllers\Api\Inventory\TransferItem;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\TransferItem\ApproveTransferItemRequest;
use App\Http\Resources\ApiResource;
use App\Model\Inventory\TransferItem\TransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferItemCloseApprovalController extends Controller
{
    /**
     * @param ApproveTransferItemRequest $request
     * @param Request $request
     * @param $id
     * @return ApiResource
     */
    public function approve(ApproveTransferItemRequest $request, $id)
    {
        try {
            $transferItem = TransferItem::findOrFail($id);
            if ($transferItem->form->close_status === 0) {
                TransferItem::closeForm($transferItem, $request->items);
            
                $transferItem->form->close_approval_by = auth()->user()->id;
                $transferItem->form->close_approval_at = now();
                $transferItem->form->close_status = 1;
                $transferItem->form->done = 1;
                $transferItem->form->save();
            } elseif ($transferItem->form->close_status === 1) {
                abort(422, 'This form has been approved');
            }

            return new ApiResource($transferItem);

        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return response()->json([
                'code' => 422,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
