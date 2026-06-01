<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadFileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Single image upload endpoint. Stores into {directory} on the default
 * filesystem disk (S3 in this project) and returns both the relative path
 * and the fully-qualified public URL. The relative path is what gets
 * persisted on the model; the URL is derived from it via Storage::url().
 */
class UploadController extends Controller
{
    public function store(UploadFileRequest $request): JsonResponse
    {
        $path = $request->file('file')->store(
            $request->validated('directory'),
        );

        return response()->json([
            'data' => [
                'path' => $path,
                'url'  => Storage::url($path),
            ],
        ], 201);
    }
}
