<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MassNotifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any active admin can broadcast (matches Filament page access).
        return $this->user('admin') !== null && $this->user('admin')->is_active;
    }

    public function rules(): array
    {
        // Mirrors MassNotify Filament page form fields exactly.
        return [
            'audience' => ['required', 'in:all,premium,basic,expiring'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'title'    => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string', 'max:2000'],
        ];
    }
}
