<?php

namespace App\Modules\Governance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlaRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'event_key'     => ['required', 'string'],
            'penalty_type'  => ['required', 'in:fixed,percentage,reputation'],
            'penalty_value' => ['required', 'numeric', 'min:0'],
            'description'   => ['nullable', 'string'],
            'meta'          => ['nullable', 'array'],
        ];
    }
}
