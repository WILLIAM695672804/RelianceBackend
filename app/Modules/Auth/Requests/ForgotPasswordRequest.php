<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Pas de règle « exists » : elle révélerait quelles adresses possèdent un compte.
            'email' => 'required|email',
        ];
    }
}
