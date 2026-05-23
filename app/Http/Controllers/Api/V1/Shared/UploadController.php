<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadFileRequest;
use Illuminate\Http\JsonResponse;

/**
 * Single image upload endpoint. Stores into storage/app/public/{directory}
 * and returns the relative path. Mirrors Filament FileUpload::directory(...)
 * targets exactly so the persisted strings line up with what the Filament
 * panels already produce.
 */
class UploadController extends Controller
{
    public function store(UploadFileRequest $request): JsonResponse
    {
        $path = $request->file('file')->store(
            $request->validated('directory'),
            'public',
        );

        return response()->json([
            'data' => [
                'path' => $path,
                'url'  => '/storage/' . $path,
            ],
        ], 201);
    }
}
