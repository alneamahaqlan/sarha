<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\HomepageSection;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', HomepageSection::class);
    }

    public function rules(): array
    {
        return [
            'image'      => ['sometimes', 'required', 'string', 'max:500'],
            'link_url'   => ['nullable', 'string', 'max:500', 'url'],
            'is_active'  => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
