<?php

namespace App\Modules\Governance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlaEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid', 'exists:client_requests,id'],
            'event_key'  => ['required', 'string'],
            'payload'    => ['nullable', 'array'],
        ];
    }
}
