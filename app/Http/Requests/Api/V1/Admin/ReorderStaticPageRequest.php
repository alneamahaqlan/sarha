<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\StaticPage;
use Illuminate\Foundation\Http\FormRequest;

class ReorderStaticPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('reorder', StaticPage::class);
    }

    public function rules(): array
    {
        return [
            'order'              => ['required', 'array', 'min:1'],
            'order.*.id'         => ['required', 'integer', 'exists:static_pages,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
