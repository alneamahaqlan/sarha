<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\LandingPage;
use Illuminate\Foundation\Http\FormRequest;

class GenerateLandingContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', LandingPage::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'   => ['nullable', 'exists:clinics,id'],
            'service'     => ['nullable', 'string', 'max:255'],
            'city_id'     => ['nullable', 'exists:cities,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ];
    }
}
