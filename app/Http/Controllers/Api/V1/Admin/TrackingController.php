<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PixelProvider;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SystemSetting;
use App\Services\Tracking\TrackingContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin control surface for the tracking feature:
 *   - global on/off + consent settings
 *   - the activation request queue (approve / reject)
 *   - per-clinic kill-switch (disable)
 *
 * The `sales` admin role is excluded — tracking touches the public site +
 * the privacy/PDPL surface, so it's restricted to super_admin / admin.
 */
class TrackingController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    public function settings(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json([
            'data' => [
                'feature_enabled' => (bool) SystemSetting::get('tracking.feature_enabled', false),
                'consent_enabled' => (bool) SystemSetting::get('tracking.consent_enabled', true),
                'consent_text_ar' => (string) (SystemSetting::get('tracking.consent_text_ar') ?? ''),
                'consent_text_en' => (string) (SystemSetting::get('tracking.consent_text_en') ?? ''),
                'privacy_url'     => (string) (SystemSetting::get('tracking.privacy_url') ?? ''),
                // Platform-wide pixels (load on every public page).
                'platform_pixels' => SystemSetting::get('tracking.platform_pixels', []) ?? [],
                'providers'       => PixelProvider::catalog(),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'feature_enabled' => ['required', 'boolean'],
            'consent_enabled' => ['required', 'boolean'],
            'consent_text_ar' => ['nullable', 'string', 'max:1000'],
            'consent_text_en' => ['nullable', 'string', 'max:1000'],
            'privacy_url'     => ['nullable', 'url', 'max:300'],
            'platform_pixels' => ['nullable', 'array', new \App\Rules\ValidTrackingPixels()],
        ]);

        SystemSetting::set('tracking.feature_enabled', $data['feature_enabled'] ? '1' : '0');
        SystemSetting::set('tracking.consent_enabled', $data['consent_enabled'] ? '1' : '0');
        SystemSetting::set('tracking.consent_text_ar', $data['consent_text_ar'] ?? '');
        SystemSetting::set('tracking.consent_text_en', $data['consent_text_en'] ?? '');
        SystemSetting::set('tracking.privacy_url', $data['privacy_url'] ?? '');
        SystemSetting::set('tracking.platform_pixels', json_encode($this->sanitizePixels($data['platform_pixels'] ?? [])));

        // Type metadata so SystemSetting::castValue() returns real booleans.
        SystemSetting::query()->whereIn('key', [
            'tracking.feature_enabled', 'tracking.consent_enabled',
        ])->update(['type' => 'boolean', 'group' => 'tracking']);
        SystemSetting::query()->whereIn('key', [
            'tracking.consent_text_ar', 'tracking.consent_text_en', 'tracking.privacy_url',
        ])->update(['type' => 'string', 'group' => 'tracking']);
        SystemSetting::query()->where('key', 'tracking.platform_pixels')->update(['type' => 'json', 'group' => 'tracking']);

        foreach (['feature_enabled', 'consent_enabled', 'consent_text_ar', 'consent_text_en', 'privacy_url', 'platform_pixels'] as $k) {
            \Illuminate\Support\Facades\Cache::forget('setting:tracking.' . $k);
        }

        return $this->settings();
    }

    /** The moderation queue. ?status=pending (default) | active | all */
    public function requests(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $status = $request->string('status')->toString() ?: 'pending';

        $query = Clinic::query()
            ->select(['id', 'name', 'slug', 'tracking_status', 'tracking_pixels', 'advanced_matching_optin', 'tracking_requested_at'])
            ->when($status !== 'all', fn ($q) => $q->where('tracking_status', $status))
            ->when($status === 'all', fn ($q) => $q->whereIn('tracking_status', ['pending', 'active', 'rejected']))
            ->orderByRaw("FIELD(tracking_status, 'pending', 'active', 'rejected', 'disabled')")
            ->orderByDesc('tracking_requested_at');

        return response()->json(['data' => $query->get()]);
    }

    public function approve(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $clinic->update([
            'tracking_status'           => 'active',
            'tracking_rejection_reason' => null,
            'tracking_reviewed_by'      => auth('admin')->id(),
        ]);
        TrackingContextResolver::forget($clinic->slug);

        return response()->json(['data' => $clinic->only(['id', 'tracking_status'])]);
    }

    public function reject(Request $request, Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $clinic->update([
            'tracking_status'           => 'rejected',
            'tracking_rejection_reason' => $data['reason'] ?? null,
            'tracking_reviewed_by'      => auth('admin')->id(),
        ]);
        TrackingContextResolver::forget($clinic->slug);

        return response()->json(['data' => $clinic->only(['id', 'tracking_status'])]);
    }

    /** Keep only validated provider+id+enabled triples (platform pixels). */
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

    /** Kill-switch — stop tracking immediately. */
    public function disable(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $clinic->update([
            'tracking_status'      => 'disabled',
            'tracking_reviewed_by' => auth('admin')->id(),
        ]);
        TrackingContextResolver::forget($clinic->slug);

        return response()->json(['data' => $clinic->only(['id', 'tracking_status'])]);
    }
}
