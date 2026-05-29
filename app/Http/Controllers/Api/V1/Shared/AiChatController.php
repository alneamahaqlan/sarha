<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AiAssistantInterceptor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin wrapper over AiAssistantInterceptor — which in turn wraps the
 * unchanged AiAssistantService::ask call with the admin-managed safety
 * net (restriction preflight, blocklist filtering, audit log, stat bumps).
 *
 * The `query` length cap is read from `ai_max_query_length` (default 500)
 * so the super-admin can grow/shrink it without redeploying.
 */
class AiChatController extends Controller
{
    public function __construct(private readonly AiAssistantInterceptor $assistant) {}

    public function ask(Request $request): JsonResponse
    {
        $maxLen = max(20, (int) SystemSetting::get('ai_max_query_length', 500));

        $data = $request->validate([
            'query'   => ['required', 'string', "max:{$maxLen}"],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        $result = $this->assistant->handle($data['query'], $data['city_id'] ?? null, $request);

        return response()->json(['data' => $result]);
    }
}
