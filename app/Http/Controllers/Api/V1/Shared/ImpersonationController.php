<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;

/**
 * Stop the active impersonation session. Mirrors the existing
 * /impersonate/stop web route — delegates to the SAME ImpersonationService.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function stop(): JsonResponse
    {
        if (! $this->impersonation->isImpersonating()) {
            return response()->json(['message' => 'Not impersonating.'], 422);
        }

        $this->impersonation->stop();

        return response()->json([
            'data'    => ['redirect' => '/app'],
            'message' => 'Impersonation stopped.',
        ]);
    }
}
