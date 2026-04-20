<?php

namespace App\Modules\Social\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject'      => 'nullable|string|max:255',
            'members'      => 'required|array|min:1',
            'members.*'    => 'uuid|exists:users,id',
            'initial_message' => 'nullable|string|max:5000',
        ];
    }
}
