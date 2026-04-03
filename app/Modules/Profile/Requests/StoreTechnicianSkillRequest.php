<?php

namespace App\Modules\Profile\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTechnicianSkillRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'skill_id' => 'required|uuid|exists:skills,id',
            'proficiency' => 'required|integer|min:1|max:5', // L1 à L5
            'experience_years' => 'required|integer|min:0|max:50',
            'hourly_rate' => 'nullable|numeric|min:0',
            'travel_fee' => 'nullable|numeric|min:0'
        ];
    }
}
