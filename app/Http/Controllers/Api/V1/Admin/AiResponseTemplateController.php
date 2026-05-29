<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAiResponseTemplateRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAiResponseTemplateRequest;
use App\Http\Resources\Api\V1\AiResponseTemplateResource;
use App\Models\AiResponseTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AiResponseTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AiResponseTemplateResource::collection(
            AiResponseTemplate::orderBy('sort_order')->orderByDesc('id')->get()
        );
    }

    public function store(StoreAiResponseTemplateRequest $request): AiResponseTemplateResource
    {
        $data = $request->validated() + ['created_by_admin_id' => $request->user('admin')?->id];
        return new AiResponseTemplateResource(AiResponseTemplate::create($data));
    }

    public function update(UpdateAiResponseTemplateRequest $request, AiResponseTemplate $aiResponseTemplate): AiResponseTemplateResource
    {
        $aiResponseTemplate->update($request->validated());
        return new AiResponseTemplateResource($aiResponseTemplate->fresh());
    }

    public function destroy(AiResponseTemplate $aiResponseTemplate): JsonResponse
    {
        $aiResponseTemplate->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
