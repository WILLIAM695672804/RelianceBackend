<?php

namespace App\Modules\Schedule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleExceptionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'start_ts' => 'required|date',
            'end_ts' => 'required|date|after:start_ts',
            'reason' => 'nullable|string|max:255'
        ];
    }
}
