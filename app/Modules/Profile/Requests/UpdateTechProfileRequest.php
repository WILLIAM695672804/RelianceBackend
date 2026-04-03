<?php

namespace App\Modules\Profile\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTechProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Autorisée car géré par le middleware auth:sanctum
    }

    public function rules()
    {
        return [
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:50',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ];
    }
}
