<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAiRestrictionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAiRestrictionRequest;
use App\Http\Resources\Api\V1\AiRestrictionResource;
use App\Models\AiRestriction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Super-admin CRUD over AI restrictions (banned topics, emergency
 * keywords, clinic/category blocklists). Consumed by the "Restrictions &
 * Instructions" tab of the AI Center.
 */
class AiRestrictionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = AiRestriction::query();
        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        return AiRestrictionResource::collection($q->orderBy('type')->orderByDesc('id')->get());
    }

    public function store(StoreAiRestrictionRequest $request): AiRestrictionResource
    {
        $data = $request->validated() + [
            'is_active' => true,
            'created_by_admin_id' => $request->user('admin')?->id,
        ];

        return new AiRestrictionResource(AiRestriction::create($data));
    }

    public function update(UpdateAiRestrictionRequest $request, AiRestriction $aiRestriction): AiRestrictionResource
    {
        $aiRestriction->update($request->validated());
        return new AiRestrictionResource($aiRestriction->fresh());
    }

    public function destroy(AiRestriction $aiRestriction): JsonResponse
    {
        $aiRestriction->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
