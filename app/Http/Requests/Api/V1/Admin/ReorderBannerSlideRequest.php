<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\HomepageSection;
use Illuminate\Foundation\Http\FormRequest;

class ReorderBannerSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', HomepageSection::class);
    }

    public function rules(): array
    {
        return [
            'order'              => ['required', 'array', 'min:1'],
            'order.*.id'         => ['required', 'integer', 'exists:homepage_banner_slides,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
