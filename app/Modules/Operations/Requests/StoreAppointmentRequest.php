<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id'      => 'required|uuid|exists:client_requests,id',
            'technician_id'   => 'required|uuid|exists:users,id',
            'scheduled_start' => 'required|date|after:now',
            'scheduled_end'   => 'nullable|date|after:scheduled_start',
            'location_id'     => 'nullable|uuid|exists:locations,id',
            'notes'           => 'nullable|string|max:1000',
        ];
    }
}
