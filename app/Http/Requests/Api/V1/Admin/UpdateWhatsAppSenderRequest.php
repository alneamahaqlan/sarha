<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\WhatsAppSender;
use App\Support\SaudiPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsAppSenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sender = $this->route('whatsappSender');

        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $sender);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => SaudiPhone::toInternational($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('whatsappSender')?->id;

        return [
            'label'      => ['nullable', 'string', 'max:255'],
            'phone'      => ['sometimes', 'required', 'string', 'regex:/^9665\d{8}$/', Rule::unique('whatsapp_senders', 'phone')->ignore($id)],
            'provider'   => ['nullable', 'string', 'max:50'],
            'profile_id' => ['nullable', 'string', 'max:255'],
            // Empty / mask token means "keep current" — handled in the controller.
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
}
