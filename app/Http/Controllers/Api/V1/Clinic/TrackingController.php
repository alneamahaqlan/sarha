<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Enums\PixelProvider;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Rules\ValidTrackingPixels;
use App\Services\Tracking\TrackingContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clinic-side marketing pixel management. The clinic edits its pixels as a
 * DRAFT and requests activation; a super-admin approves before anything
 * loads on the public site. Auto-scoped to auth('clinic').
 */
class TrackingController extends Controller
{
    public function show(): JsonResponse
    {
        $clinic = auth('clinic')->user();

        return response()->json([
            'data' => [
                'tracking_status'         => $clinic->tracking_status,
                'tracking_pixels'         => $clinic->tracking_pixels ?? [],
                'advanced_matching_optin' => (bool) $clinic->advanced_matching_optin,
                'tracking_rejection_reason' => $clinic->tracking_rejection_reason,
                'tracking_requested_at'   => $clinic->tracking_requested_at,
                // Is the platform-wide feature on at all? Drives the UI banner.
                'feature_enabled'         => (bool) SystemSetting::get('tracking.feature_enabled', false),
                'providers'               => PixelProvider::catalog(),
            ],
        ]);
    }

    /** Save the pixel draft. Does NOT change moderation status by itself. */
    public function update(Request $request): JsonResponse
    {
        $clinic = auth('clinic')->user();

        $data = $request->validate([
            'tracking_pixels'           => ['nullable', 'array', new ValidTrackingPixels()],
            'advanced_matching_optin'   => ['boolean'],
        ]);

        $clinic->update([
            'tracking_pixels'         => $this->sanitizePixels($data['tracking_pixels'] ?? []),
            'advanced_matching_optin' => $data['advanced_matching_optin'] ?? false,
        ]);

        TrackingContextResolver::forget($clinic->slug);

        return $this->show();
    }

    /** Submit the draft for super-admin approval. */
    public function requestActivation(Request $request): JsonResponse
    {
        $clinic = auth('clinic')->user();

        if (empty($clinic->tracking_pixels)) {
            return response()->json([
                'message' => 'أضف بكسلاً واحداً على الأقل قبل طلب التفعيل.',
            ], 422);
        }

        // Only meaningful from a non-active state.
        if (in_array($clinic->tracking_status, ['disabled', 'rejected'], true)) {
            $clinic->update([
                'tracking_status'           => 'pending',
                'tracking_rejection_reason' => null,
                'tracking_requested_at'     => now(),
            ]);
            TrackingContextResolver::forget($clinic->slug);
        }

        return $this->show();
    }

    /** Keep only validated provider+id+enabled triples. */
    private function sanitizePixels(array $pixels): array
    {
        $out = [];
        foreach ($pixels as $row) {
            $provider = PixelProvider::tryFrom((string) ($row['provider'] ?? ''));
            $id = trim((string) ($row['id'] ?? ''));
            if ($provider === null || ! $provider->isValidId($id)) {
                continue;
            }
            $out[] = [
                'provider' => $provider->value,
                'id'       => $id,
                'enabled'  => (bool) ($row['enabled'] ?? true),
            ];
        }
        return $out;
    }
}
