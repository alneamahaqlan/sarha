<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\NavigationLink;
use Illuminate\Foundation\Http\FormRequest;

class ReorderNavigationLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('reorder', NavigationLink::class);
    }

    public function rules(): array
    {
        return [
            'order'              => ['required', 'array', 'min:1'],
            'order.*.id'         => ['required', 'integer', 'exists:navigation_links,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
