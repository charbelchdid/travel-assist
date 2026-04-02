<?php

namespace App\Http\Requests\Erp;

use Illuminate\Foundation\Http\FormRequest;

class SendTemplateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'templateName' => ['nullable', 'string'],
            'template' => ['nullable', 'array'],
            'lang' => ['nullable', 'string'],
            'target' => ['required', 'array', 'min:1'],
            'target.*.id' => ['nullable'],
            'target.*.entityType' => ['required', 'string'],
            'target.*.receiverName' => ['required', 'string'],
            'target.*.mobileNumber' => ['nullable', 'string'],
            'target.*.whatsappNumber' => ['nullable', 'string'],
            'target.*.smsReceiverType' => ['nullable'],
            'context' => ['nullable'],
            'cta' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'channelType' => ['nullable'],
            'notificationRelativeExpirationDate' => ['nullable', 'array'],
        ];
    }
}

