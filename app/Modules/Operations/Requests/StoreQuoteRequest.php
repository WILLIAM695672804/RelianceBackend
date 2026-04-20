<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id'   => 'required|uuid|exists:client_requests,id',
            'total_amount' => 'required|numeric|min:0',
            'breakdown'    => 'nullable|array',
            'breakdown.*.label'  => 'required_with:breakdown|string',
            'breakdown.*.amount' => 'required_with:breakdown|numeric|min:0',
            'valid_until'  => 'nullable|date|after:today',
        ];
    }
}
