<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNavigationLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('navigation_link'));
    }

    public function rules(): array
    {
        return [
            'location'       => ['sometimes', 'required', 'in:header,footer'],
            'footer_column'  => ['nullable', 'integer', 'min:1', 'max:3', 'required_if:location,footer'],
            'label_ar'       => ['sometimes', 'required', 'string', 'max:255'],
            'label_en'       => ['nullable', 'string', 'max:255'],
            'url'            => ['nullable', 'string', 'max:2048'],
            'static_page_id' => ['nullable', 'integer', 'exists:static_pages,id'],
            'route_name'     => ['nullable', 'string', 'max:255'],
            'open_new_tab'   => ['nullable', 'boolean'],
            'is_active'      => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
        ];
    }
}
