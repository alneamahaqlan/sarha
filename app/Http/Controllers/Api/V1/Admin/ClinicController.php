<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BulkClinicRequest;
use App\Http\Requests\Api\V1\Admin\ExtendClinicRequest;
use App\Http\Requests\Api\V1\Admin\RejectClinicRequest;
use App\Http\Requests\Api\V1\Admin\StoreClinicRequest;
use App\Http\Requests\Api\V1\Admin\UpdateClinicRequest;
use App\Http\Resources\Api\V1\ClinicResource as ClinicApiResource;
use App\Models\Clinic;
use App\Services\ClinicService;
use App\Services\GoogleSheetsImportService;
use App\Services\ImpersonationService;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class ClinicController extends Controller
{
    public function __construct(
        private readonly ClinicService $clinics,
        private readonly ImpersonationService $impersonation,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Clinic::class);

        // Mirror Filament's withoutGlobalScopes(SoftDeletingScope) for the admin panel.
        $query = Clinic::query()->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['city:id,name'])
            ->withCount('bookings')
            ->withSum(['stats as visits_30d_sum' => fn ($q) => $q->where('date', '>=', now()->subDays(30))], 'page_views');

        $trashed = $request->string('filter.trashed')->toString();
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            // no extra clause — both trashed and non-trashed.
        } else {
            $query->whereNull('deleted_at');
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'subscription_type', 'city_id'] as $f) {
            if ($val = $request->input("filter.{$f}")) {
                $query->where($f, $val);
            }
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['name', 'created_at', 'subscription_ends_at', 'status'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ClinicApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Clinic $clinic): ClinicApiResource
    {
        $this->authorize('view', $clinic);

        return new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name']));
    }

    /**
     * Return the complex → clinic (sub-clinic) → services tree for the admin
     * view. Sub-clinics here include both active and inactive ones (admins need
     * the full picture); same for services. The clinic-side `subClinics`
     * relation is filtered to active-only, so we go through `subClinicsAll`.
     */
    public function structure(Clinic $clinic): JsonResponse
    {
        $this->authorize('view', $clinic);

        $clinic->load([
            'subClinicsAll.category:id,name,emoji',
            'subClinicsAll.allServices' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
            // Services without a sub-clinic ("general" bucket) — still need them.
            'services' => fn ($q) => $q->whereNull('sub_clinic_id')->orderBy('sort_order')->orderBy('name'),
        ]);

        $serviceShape = fn ($s) => [
            'id'         => $s->id,
            'name'       => $s->name,
            'price'      => $s->price,
            'is_active'  => (bool) $s->is_active,
        ];

        $subClinics = $clinic->subClinicsAll->map(fn ($sub) => [
            'id'             => $sub->id,
            'name'           => $sub->name,
            'name_en'        => $sub->name_en,
            'description'    => $sub->description,
            'is_active'      => (bool) $sub->is_active,
            'sort_order'     => (int) $sub->sort_order,
            'category'       => $sub->category ? [
                'id'    => $sub->category->id,
                'name'  => $sub->category->name,
                'emoji' => $sub->category->emoji,
            ] : null,
            'services_count' => $sub->allServices->count(),
            'services'       => $sub->allServices->map($serviceShape)->values(),
        ])->values();

        $generalServices = $clinic->services->map($serviceShape)->values();

        return response()->json([
            'data' => [
                'clinic' => [
                    'id'   => $clinic->id,
                    'name' => $clinic->name,
                ],
                'sub_clinics'      => $subClinics,
                'general_services' => $generalServices,
                'totals' => [
                    'sub_clinics' => $subClinics->count(),
                    'services'    => $subClinics->sum('services_count') + $generalServices->count(),
                ],
            ],
        ]);
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $data = $request->validated();
        $categories = $data['categories'] ?? null;
        unset($data['categories']);

        // Keep a reversible copy so the super-admin can reveal it later.
        $data['password_plaintext'] = $data['password'];
        $data['password'] = Hash::make($data['password']);

        $clinic = Clinic::create($data);
        if ($categories !== null) {
            $clinic->categories()->sync($categories);
        }

        return (new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): ClinicApiResource
    {
        $data = $request->validated();
        $categories = $data['categories'] ?? null;
        unset($data['categories']);

        // Mirror Filament: empty password means 'keep existing'.
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password_plaintext'] = $data['password'];
            $data['password'] = Hash::make($data['password']);
        }

        $clinic->update($data);
        if ($categories !== null) {
            $clinic->categories()->sync($categories);
        }

        return new ClinicApiResource($clinic->fresh()->load(['city:id,name', 'categories:id,name']));
    }

    public function destroy(Clinic $clinic): JsonResponse
    {
        $this->authorize('delete', $clinic);

        $clinic->delete();

        return response()->json(null, 204);
    }

    /**
     * Reveal the stored login password to a super-admin. Returns null when no
     * reversible copy exists (clinic predates the feature or was never issued
     * one) — the UI then offers "regenerate". Every reveal is audit-logged.
     */
    public function password(Clinic $clinic): JsonResponse
    {
        $this->authorize('viewPassword', $clinic);

        \App\Services\AuditLogService::log('clinic.password_revealed', $clinic);

        return response()->json(['data' => ['password' => $clinic->password_plaintext]]);
    }

    /**
     * Issue a fresh password for the clinic, store hash + reversible copy, and
     * return the plaintext once for the super-admin to hand over.
     */
    public function regeneratePassword(Clinic $clinic): JsonResponse
    {
        $this->authorize('viewPassword', $clinic);

        $password = $clinic->issuePassword();
        $clinic->save();

        \App\Services\AuditLogService::log('clinic.password_regenerated', $clinic);

        return response()->json(['data' => ['password' => $password]]);
    }

    // ---- State-transition actions — all delegate to ClinicService. ----

    public function approve(Clinic $clinic): ClinicApiResource
    {
        $this->authorize('approve', $clinic);

        $clinic = $this->clinics->approve($clinic);

        return new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name']));
    }

    public function reject(RejectClinicRequest $request, Clinic $clinic): ClinicApiResource
    {
        $clinic = $this->clinics->reject($clinic, $request->validated('rejection_reason'));

        return new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name']));
    }

    public function activate(Clinic $clinic): ClinicApiResource
    {
        $this->authorize('activate', $clinic);

        $clinic = $this->clinics->activate($clinic);

        return new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name']));
    }

    public function suspend(Clinic $clinic): ClinicApiResource
    {
        $this->authorize('suspend', $clinic);

        $clinic = $this->clinics->suspend($clinic);

        return new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name']));
    }

    public function extend(ExtendClinicRequest $request, Clinic $clinic): ClinicApiResource
    {
        $clinic = $this->clinics->extend($clinic, (int) $request->validated('days'));

        return new ClinicApiResource($clinic->load(['city:id,name', 'categories:id,name']));
    }

    /**
     * Start an impersonation session for a clinic. Session cookie is set by
     * ImpersonationService::start() so subsequent /api/v1/auth/me calls reflect
     * the impersonated clinic guard.
     */
    public function impersonate(Clinic $clinic): JsonResponse
    {
        $this->authorize('impersonate', $clinic);

        $admin = auth('admin')->user();

        $this->impersonation->start($admin, $clinic);

        return response()->json([
            'data' => [
                'redirect' => '/app',
                'clinic'   => (new ClinicApiResource($clinic->load('city:id,name')))->toArray(request()),
            ],
        ]);
    }

    /**
     * Bulk delete / restore / forceDelete on selected clinics.
     * Mirrors Filament's BulkActionGroup actions, with each row checked via
     * the corresponding Policy ability so any row that fails authorization
     * is silently skipped from the count instead of aborting the whole batch.
     */
    /**
     * Restore a soft-deleted clinic. Single-row counterpart to the bulk action.
     */
    public function restore(Clinic $clinic_trashed): ClinicApiResource
    {
        $this->authorize('restore', $clinic_trashed);

        $clinic_trashed->restore();

        return new ClinicApiResource($clinic_trashed->fresh()->load(['city:id,name', 'categories:id,name']));
    }

    /**
     * Import clinics from a PUBLIC Google Sheet (CSV-export trick — no API key).
     * Returns a {created, updated, skipped} summary. Invalid sheet URL → 422.
     */
    public function importSheet(Request $request, GoogleSheetsImportService $sheets): JsonResponse
    {
        $this->authorize('create', Clinic::class);

        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        if ($sheets->toCsvUrl($data['url']) === null) {
            return response()->json([
                'message' => 'The provided URL is not a valid Google Sheets link.',
                'errors'  => ['url' => ['The provided URL is not a valid Google Sheets link.']],
            ], 422);
        }

        $summary = $sheets->import($data['url']);

        return response()->json(['data' => $summary]);
    }

    public function bulk(BulkClinicRequest $request): JsonResponse
    {
        $data = $request->validated();
        $action = $data['action'];
        $ids = $data['ids'];

        // Force/Restore need access to soft-deleted rows.
        $query = Clinic::query()->withoutGlobalScopes([SoftDeletingScope::class])->whereIn('id', $ids);

        $affected = 0;

        foreach ($query->get() as $clinic) {
            $ability = match ($action) {
                'delete'        => 'delete',
                'restore'       => 'restore',
                'force_delete'  => 'forceDelete',
            };

            if (! auth('admin')->user()->can($ability, $clinic)) continue;

            match ($action) {
                'delete'        => $clinic->delete(),
                'restore'       => $clinic->restore(),
                'force_delete'  => $clinic->forceDelete(),
            };

            $affected++;
        }

        return response()->json(['data' => ['affected' => $affected]]);
    }
}
