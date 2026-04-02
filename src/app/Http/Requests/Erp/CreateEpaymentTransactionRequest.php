<?php

namespace App\Http\Requests\Erp;

use Illuminate\Foundation\Http\FormRequest;

class CreateEpaymentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relatedEntityId' => ['nullable', 'integer'],
            'entityType' => ['required', 'string'],
            'identifier' => ['nullable', 'string'],
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string'],
            'customParams' => ['nullable', 'array'],
            'customParams.*' => ['nullable', 'string'],
            'customRedirectUrl' => ['nullable', 'url'],
            'customFailRedirectUrl' => ['nullable', 'url'],
            'referer' => ['nullable', 'string'],
        ];
    }
}

