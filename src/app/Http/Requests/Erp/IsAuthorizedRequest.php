<?php

namespace App\Http\Requests\Erp;

use Illuminate\Foundation\Http\FormRequest;

class IsAuthorizedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pageCode' => ['required', 'string'],
            'apiUrl' => ['required', 'string'],
        ];
    }
}

