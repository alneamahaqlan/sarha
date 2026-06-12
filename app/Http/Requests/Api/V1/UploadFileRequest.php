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
        'landing-pages', // landing page cover + social + block images
        'category-icons',// specialization (category) custom icons
        'stories',       // Instagram-style clinic story images
    ];

    public function authorize(): bool
    {
        // Any authenticated admin or clinic can upload.
        return $this->user('admin') !== null || $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        // Category icons are small vectors/glyphs: allow SVG (Laravel's `image`
        // rule drops SVG by default) alongside raster formats, capped at 1 MB.
        // Every other upload kind stays on the stricter raster-image rule.
        $fileRule = $this->input('directory') === 'category-icons'
            ? ['required', 'file', 'mimes:svg,png,webp,jpg,jpeg', 'max:1024']
            : ['required', 'file', 'image', 'max:4096'];

        return [
            'file'      => $fileRule,
            'directory' => ['required', 'in:' . implode(',', self::ALLOWED_DIRECTORIES)],
        ];
    }
}
