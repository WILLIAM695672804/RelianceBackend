<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RechargeWalletRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:100',
            'method' => 'required|in:card,bank_transfer,momo,om',
            'phone_number' => 'required_if:method,momo,om|string',
        ];
    }
}
