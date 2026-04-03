<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:50',
            'password' => 'required|min:8|confirmed',
            'role'     => 'in:client,technicien,agence',
        ];

        // Champs spécifiques au technicien
        if ($this->input('role') === 'technicien') {
            $rules['bio'] = 'nullable|string';
            $rules['competences'] = 'nullable|array';
            $rules['competences.*'] = 'string';
        }

        // Champs spécifiques à l'agence
        if ($this->input('role') === 'agence') {
            $rules['agency_name'] = 'required|string|max:255';
            $rules['address'] = 'nullable|string|max:500';
            $rules['description'] = 'nullable|string';
        }

        return $rules;
    }
}