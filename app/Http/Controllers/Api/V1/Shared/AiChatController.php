<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin wrapper over AiAssistantService::ask — the same code the Filament
 * AiChat Livewire widget calls. No business logic here.
 */
class AiChatController extends Controller
{
    public function __construct(private readonly AiAssistantService $ai) {}

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query'   => ['required', 'string', 'max:500'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        $result = $this->ai->ask($data['query'], $data['city_id'] ?? null);

        return response()->json(['data' => $result]);
    }
}
