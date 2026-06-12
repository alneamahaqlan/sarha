<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\NavigationLink;
use Illuminate\Foundation\Http\FormRequest;

class StoreNavigationLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', NavigationLink::class);
    }

    public function rules(): array
    {
        return [
            'location'       => ['required', 'in:header,footer'],
            'footer_column'  => ['nullable', 'integer', 'min:1', 'max:3', 'required_if:location,footer'],
            'label_ar'       => ['required', 'string', 'max:255'],
            'label_en'       => ['nullable', 'string', 'max:255'],
            'url'            => ['nullable', 'string', 'max:2048'],
            'static_page_id' => ['nullable', 'integer', 'exists:static_pages,id'],
            'route_name'     => ['nullable', 'string', 'max:255'],
            'open_new_tab'   => ['nullable', 'boolean'],
            'is_active'      => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // A link must point somewhere: a page, a named route, or a URL.
            if (! $this->filled('static_page_id') && ! $this->filled('route_name') && ! $this->filled('url')) {
                $validator->errors()->add('url', __('validation.required', ['attribute' => 'url']));
            }
        });
    }
}
