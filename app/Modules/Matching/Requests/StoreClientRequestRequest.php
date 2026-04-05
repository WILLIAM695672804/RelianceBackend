<?php

namespace App\Modules\Matching\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'         => 'required|string|max:255',
            'description'   => 'required|string|max:5000',
            'category_slug' => 'nullable|string|exists:skills,slug',
            'address_id'    => 'nullable|uuid|exists:locations,id',
            'latitude'      => 'required_without:address_id|nullable|numeric|between:-90,90',
            'longitude'     => 'required_without:address_id|nullable|numeric|between:-180,180',
            'address_label' => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'photos'        => 'nullable|array|max:5',
            'photos.*'      => 'file|mimes:jpg,jpeg,png|max:5120',
        ];
    }
}
