<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogService;
use App\Models\CategoryRequest;
use App\Models\Clinic;
use App\Models\ClinicCampaign;
use App\Models\LandingPage;
use App\Models\PlatformNotification;
use App\Models\SubscriptionPackage;
use App\Support\ClinicGateRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified "Requests & Permissions Center" for the super-admin.
 *
 * Aggregates every per-clinic gate (driven by ClinicGateRegistry) plus the
 * external request queues (category / catalog / managed-campaign) into one
 * surface: a pending inbox, a per-clinic control view, and a read-only view of
 * package-level entitlements. New registry gates appear here automatically.
 */
class AccessCenterController extends Controller
{
    /** Package-level entitlement columns (read-only here; edited on packages). */
    private const PACKAGE_FLAGS = [
        'crm_enabled', 'featured_in_search', 'verified_badge',
        'allow_offers_packages', 'allow_doctors_before_after',
        'ai_article_generation', 'google_reviews_sync',
        'services_limit', 'articles_monthly_limit', 'quote_replies_monthly_limit',
        'banner_slots', 'analytics_level', 'ai_assistant_priority',
    ];

    private function authorizeAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    /** Registry metadata so the UI can render gates it has never heard of. */
    public function meta(): JsonResponse
    {
        $this->authorizeAdmin();

        $gates = collect(ClinicGateRegistry::gates())->map(fn ($g, $key) => [
            'key'      => $key,
            'label_ar' => $g['label_ar'],
            'label_en' => $g['label_en'],
            'default'  => $g['default'],
        ])->values();

        return response()->json(['data' => ['gates' => $gates]]);
    }

    /** Unified inbox: every clinic with a pending gate + external queue counts. */
    public function pending(): JsonResponse
    {
        $this->authorizeAdmin();

        $items = [];
        foreach (ClinicGateRegistry::gates() as $key => $g) {
            $clinics = Clinic::query()
                ->where($g['status'], 'pending')
                ->orderByDesc($g['requested'])
                ->get(['id', 'name', $g['requested']]);

            foreach ($clinics as $c) {
                // Real column name (not an alias) so the model's datetime cast applies.
                $requestedAt = $c->{$g['requested']};
                $items[] = [
                    'gate'          => $key,
                    'gate_label_ar' => $g['label_ar'],
                    'gate_label_en' => $g['label_en'],
                    'clinic_id'     => $c->id,
                    'clinic_name'   => $c->name,
                    'requested_at'  => $requestedAt ? $requestedAt->toIso8601String() : null,
                ];
            }
        }

        // External request queues (different data shapes — surfaced as a count
        // + a deep link to their dedicated moderation page).
        $queues = [
            [
                'key'   => 'category_requests',
                'count' => CategoryRequest::where('status', 'pending')->count(),
                'route' => '/admin/category-requests',
            ],
            [
                'key'   => 'catalog_services',
                'count' => CatalogService::where('status', 'pending')->count(),
                'route' => '/admin/catalog-services',
            ],
            [
                'key'   => 'campaign_requests',
                'count' => ClinicCampaign::where('type', 'managed')->where('managed_status', 'submitted')->count(),
                'route' => '/admin/campaign-requests',
            ],
        ];

        // Clinic-built landing pages awaiting their one-time approval. Unlike
        // the gates (a per-clinic status column) these are individual entities,
        // so they're surfaced as their own inline-actionable list with a live
        // preview link rather than a per-clinic toggle.
        $landingRequests = LandingPage::query()
            ->where('approval_status', 'pending')
            ->whereNotNull('owner_clinic_id')
            ->with('ownerClinic:id,name')
            ->orderByDesc('submitted_at')
            ->get(['id', 'owner_clinic_id', 'title_ar', 'slug', 'submitted_at'])
            ->map(fn (LandingPage $p) => [
                'landing_page_id' => $p->id,
                'clinic_id'       => $p->owner_clinic_id,
                'clinic_name'     => $p->ownerClinic?->name,
                'title'           => $p->title_ar,
                'slug'            => $p->slug,
                'preview_url'     => "/l/{$p->slug}",
                'submitted_at'    => $p->submitted_at?->toIso8601String(),
            ])->values();

        return response()->json(['data' => [
            'pending'          => $items,
            'queues'           => $queues,
            'landing_requests' => $landingRequests,
        ]]);
    }

    /** Per-clinic view: search clinics and return each gate's current status. */
    public function clinics(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $search = $request->string('search')->toString();

        $cols = ['id', 'name'];
        foreach (ClinicGateRegistry::gates() as $g) {
            $cols[] = $g['status'];
        }

        $clinics = Clinic::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(100)
            ->get($cols)
            ->map(function (Clinic $c) {
                $gates = [];
                foreach (ClinicGateRegistry::gates() as $key => $g) {
                    $gates[$key] = $c->{$g['status']};
                }
                return ['id' => $c->id, 'name' => $c->name, 'gates' => $gates];
            });

        return response()->json(['data' => $clinics]);
    }

    /** Read-only package entitlement matrix (edited on the packages screen). */
    public function packages(): JsonResponse
    {
        $this->authorizeAdmin();

        $packages = SubscriptionPackage::query()
            ->orderBy('sort_order')
            ->get(array_merge(['id', 'name_ar', 'name_en'], self::PACKAGE_FLAGS))
            ->map(function (SubscriptionPackage $p) {
                $flags = [];
                foreach (self::PACKAGE_FLAGS as $f) {
                    $flags[$f] = $p->{$f};
                }
                return [
                    'id'      => $p->id,
                    'name_ar' => $p->name_ar,
                    'name_en' => $p->name_en,
                    'flags'   => $flags,
                ];
            });

        return response()->json(['data' => ['flags' => self::PACKAGE_FLAGS, 'packages' => $packages]]);
    }

    /** Generic approve / reject / disable for any registered gate. */
    public function action(Request $request, string $gate, Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $g = ClinicGateRegistry::get($gate);
        abort_unless($g !== null, 404);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,disable'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $adminId = auth('admin')->id();

        $patch = match ($data['action']) {
            'approve' => [$g['status'] => 'active',   $g['reason'] => null,                 $g['reviewed'] => $adminId],
            'reject'  => [$g['status'] => 'rejected', $g['reason'] => $data['reason'] ?? null, $g['reviewed'] => $adminId],
            'disable' => [$g['status'] => 'disabled', $g['reviewed'] => $adminId],
        };

        $clinic->update($patch);

        return response()->json(['data' => [
            'gate'      => $gate,
            'clinic_id' => $clinic->id,
            'status'    => $clinic->{$g['status']},
        ]]);
    }

    /**
     * Approve or reject a clinic-built landing page's one-time review. Approve
     * publishes it (after which the complex's edits are live without further
     * review); reject records the reason and keeps it hidden. Either way the
     * owning complex is notified.
     */
    public function landingAction(Request $request, LandingPage $landingPage): JsonResponse
    {
        $this->authorizeAdmin();

        abort_unless($landingPage->owner_clinic_id !== null, 404);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $adminId = auth('admin')->id();

        if ($data['action'] === 'approve') {
            $landingPage->update([
                'approval_status'      => 'approved',
                'approval_reason'      => null,
                'status'               => 'published',
                'published_at'         => $landingPage->published_at ?? now(),
                'reviewed_at'          => now(),
                'approval_reviewed_by' => $adminId,
            ]);

            $this->notifyClinic($landingPage, 'landing_page.approved', 'تم اعتماد صفحة الهبوط', "تم نشر صفحة الهبوط: {$landingPage->title_ar}");
        } else {
            $landingPage->update([
                'approval_status'      => 'rejected',
                'approval_reason'      => $data['reason'] ?? null,
                'reviewed_at'          => now(),
                'approval_reviewed_by' => $adminId,
            ]);

            $this->notifyClinic($landingPage, 'landing_page.rejected', 'تم رفض صفحة الهبوط', "تم رفض صفحة الهبوط: {$landingPage->title_ar}");
        }

        return response()->json(['data' => [
            'landing_page_id' => $landingPage->id,
            'approval_status' => $landingPage->approval_status,
        ]]);
    }

    /** Send a decision notification to the complex that owns the page. */
    private function notifyClinic(LandingPage $page, string $type, string $title, string $body): void
    {
        PlatformNotification::create([
            'notifiable_type' => Clinic::class,
            'notifiable_id'   => $page->owner_clinic_id,
            'type'            => $type,
            'icon'            => 'heroicon-o-rectangle-group',
            'url'             => '/app/clinic/landing-pages',
            'priority'        => 'normal',
            'title'           => $title,
            'body'            => $body,
            'data'            => ['landing_page_id' => $page->id],
        ]);
    }
}
