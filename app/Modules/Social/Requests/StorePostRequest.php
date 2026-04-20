<?php

namespace App\Modules\Social\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'       => 'required_without:attachments|nullable|string|max:5000',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,gif,mp4,webm,pdf|max:10240',
        ];
    }
}
