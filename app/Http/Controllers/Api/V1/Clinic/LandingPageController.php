<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreLandingPageRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateLandingPageRequest;
use App\Http\Resources\Api\V1\LandingPageResource;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\LandingPage;
use App\Models\PlatformNotification;
use App\Services\LandingPageBuilderService;
use App\Services\LandingPageStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * A complex manages landing pages for ITSELF. Every page is type `clinic`,
 * linked + owned by the authenticated complex, and must be approved once by the
 * platform admin (via the Access Center) before its first public appearance.
 * Mirrors the admin LandingPageController but is hard-scoped to the owner.
 */
class LandingPageController extends Controller
{
    public function __construct(private readonly LandingPageBuilderService $builder)
    {
    }

    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    /** Bind + assert the page belongs to the authenticated complex. */
    private function ownedOrFail(LandingPage $page): void
    {
        abort_unless($page->owner_clinic_id === $this->clinicId(), 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LandingPage::query()->ownedBy($this->clinicId());

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title_ar', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('internal_name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($approval = $request->string('filter.approval_status')->toString()) {
            $query->where('approval_status', $approval);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'updated_at', 'title_ar', 'slug', 'approval_status', 'total_views', 'total_conversions'];
        $query->orderBy(in_array($column, $allowed, true) ? $column : 'created_at', $direction);

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        return LandingPageResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(LandingPage $landingPage): LandingPageResource
    {
        $this->ownedOrFail($landingPage);

        $landingPage->load(['blocks']);

        return new LandingPageResource($landingPage);
    }

    public function store(StoreLandingPageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $clinic = auth('clinic')->user();

        // Server-controlled: the page is the complex's own profile-style page,
        // hidden until the platform admin approves it once.
        $data['type']            = 'clinic';
        $data['clinic_id']       = $clinic->id;
        $data['owner_clinic_id'] = $clinic->id;
        $data['status']          = 'draft';
        $data['approval_status'] = 'draft';
        $data['title_ar']        = $data['title_ar'] ?? $clinic->name;
        $data['internal_name']   = $data['internal_name'] ?? $clinic->name;
        $data['slug']            = $this->uniqueSlug($data['slug'] ?? $clinic->slug ?? Str::slug($clinic->name) ?: 'clinic');

        $page = LandingPage::create($data);

        // Seed the default `clinic` block roster so the builder opens populated.
        $this->builder->seedDefaults($page);

        $page->load(['blocks']);

        return (new LandingPageResource($page))->response()->setStatusCode(201);
    }

    public function update(UpdateLandingPageRequest $request, LandingPage $landingPage): LandingPageResource
    {
        $this->ownedOrFail($landingPage);

        // Content edits are live for an already-approved page (no re-approval),
        // per spec. Approval/ownership columns are never taken from input.
        $landingPage->update($request->validated());

        $landingPage->load(['blocks']);

        return new LandingPageResource($landingPage);
    }

    public function destroy(LandingPage $landingPage): JsonResponse
    {
        $this->ownedOrFail($landingPage);

        $landingPage->delete();

        return response()->json(null, 204);
    }

    /**
     * Submit a draft/rejected page for the platform admin's one-time review.
     * Flips it to `pending` and notifies every active admin (same mechanism as
     * the specialty-request queue).
     */
    public function submit(LandingPage $landingPage): JsonResponse
    {
        $this->ownedOrFail($landingPage);

        abort_unless(in_array($landingPage->approval_status, ['draft', 'rejected'], true), 422, __('admin.landing_pages.submit_invalid_state'));

        $landingPage->update([
            'approval_status' => 'pending',
            'approval_reason' => null,
            'submitted_at'    => now(),
        ]);

        $clinicName = optional(auth('clinic')->user())->name ?? '—';
        foreach (Admin::where('is_active', true)->get(['id']) as $admin) {
            PlatformNotification::create([
                'notifiable_type' => Admin::class,
                'notifiable_id'   => $admin->id,
                'type'            => 'landing_page.submitted',
                'icon'            => 'heroicon-o-rectangle-group',
                'url'             => '/app/admin/access-center',
                'priority'        => 'normal',
                'title'           => 'طلب صفحة هبوط جديد',
                'body'            => "{$clinicName} أرسل صفحة هبوط للمراجعة: {$landingPage->title_ar}",
                'data'            => ['landing_page_id' => $landingPage->id],
            ]);
        }

        return response()->json(['data' => new LandingPageResource($landingPage->fresh())]);
    }

    public function stats(Request $request, LandingPage $landingPage, LandingPageStatsService $stats): JsonResponse
    {
        $this->ownedOrFail($landingPage);

        return response()->json([
            'data' => $stats->compute(
                $landingPage,
                $request->string('from')->toString() ?: null,
                $request->string('to')->toString() ?: null,
            ),
        ]);
    }

    /** Leads/customers this page produced — bookings attributed to it. */
    public function customers(Request $request, LandingPage $landingPage): JsonResponse
    {
        $this->ownedOrFail($landingPage);

        $query = Booking::query()
            ->where('landing_page_id', $landingPage->id)
            ->with(['service:id,name', 'customer:id,name', 'user:id,name'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);
        $page = $query->paginate($perPage)->withQueryString();

        $base = Booking::where('landing_page_id', $landingPage->id);
        $totals = [
            'total'      => (clone $base)->count(),
            'registered' => (clone $base)->whereNotNull('user_id')->count(),
            'completed'  => (clone $base)->where('status', Booking::STATUS_COMPLETED)->count(),
        ];

        return response()->json([
            'data' => collect($page->items())->map(fn (Booking $b) => [
                'id'             => $b->id,
                'reference_code' => $b->reference_code,
                'customer_name'  => $b->customer_name,
                'customer_phone' => $b->customer_phone,
                'service'        => $b->service?->name,
                'status'         => $b->status,
                'acquisition'    => $b->acquisition_source,
                'utm_source'     => $b->utm_source,
                'utm_campaign'   => $b->utm_campaign,
                'is_registered'  => $b->user_id !== null,
                'customer_id'    => $b->customer_id,
                'created_at'     => $b->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
            'totals' => $totals,
        ]);
    }

    /** Derive a unique landing-page slug from a base candidate. */
    private function uniqueSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'clinic';
        $slug = $base;
        $i = 2;
        while (LandingPage::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
