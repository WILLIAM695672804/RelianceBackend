<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['en_route', 'arrived', 'in_progress', 'completed', 'cancelled'])],
            'completion_notes' => 'nullable|string|max:2000',
            'phases' => 'nullable|array',
            'phases.*.title'  => 'required_with:phases|string',
            'phases.*.status' => 'required_with:phases|string',
        ];
    }
}
