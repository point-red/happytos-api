<?php

namespace App\Http\Requests\Inventory\TransferItem;

use App\Http\Requests\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseTransferItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (tenant(auth()->user()->id)->hasPermissionTo('create transfer item') || tenant(auth()->user()->id)->hasPermissionTo('update transfer item')) {
            return true;
        }
        
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
