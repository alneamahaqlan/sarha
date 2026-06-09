<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    // Flat, entity-based collections. Each uploadable kind in the system maps
    // to exactly one top-level prefix in the bucket so files are easy to find,
    // audit and clean up per type.
    private const ALLOWED_DIRECTORIES = [
        'logos',         // clinic logos
        'gallery',       // clinic gallery images
        'doctors',       // doctor photos
        'services',      // service images
        'offers',        // offer images
        'before-after',  // before/after photos
        'articles',      // article cover images
        'banners',       // homepage banner slides
        'campaigns',     // managed-campaign creative images
    ];

    public function authorize(): bool
    {
        // Any authenticated admin or clinic can upload.
        return $this->user('admin') !== null || $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'file'      => ['required', 'file', 'image', 'max:4096'],
            'directory' => ['required', 'in:' . implode(',', self::ALLOWED_DIRECTORIES)],
        ];
    }
}
