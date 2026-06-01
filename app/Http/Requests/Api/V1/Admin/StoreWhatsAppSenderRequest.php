<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\WhatsAppSender;
use App\Support\SaudiPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppSenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', WhatsAppSender::class);
    }

    /** Normalise the phone to international digits before unique/format checks. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => SaudiPhone::toInternational($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'label'      => ['nullable', 'string', 'max:255'],
            'phone'      => ['required', 'string', 'regex:/^9665\d{8}$/', Rule::unique('whatsapp_senders', 'phone')],
            'provider'   => ['nullable', 'string', 'max:50'],
            'profile_id' => ['nullable', 'string', 'max:255'],
            'token'      => ['nullable', 'string', 'max:1000'],
            'is_active'  => ['nullable', 'boolean'],
            'priority'   => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('admin.whatsapp_senders.phone_invalid'),
        ];
    }

    /** Enforce the 5-number catalogue cap. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (WhatsAppSender::query()->count() >= WhatsAppSender::MAX_SENDERS) {
                $validator->errors()->add('phone', __('admin.whatsapp_senders.max_reached', ['max' => WhatsAppSender::MAX_SENDERS]));
            }
        });
    }
}
