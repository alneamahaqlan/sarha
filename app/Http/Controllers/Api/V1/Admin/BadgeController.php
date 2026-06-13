<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Clinic;
use App\Services\ClinicBadgeService;
use App\Support\BadgeRuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Badges Center — super-admin CRUD over display badges, manual clinic
 * assignment, and on-demand recompute of the automatic ones.
 */
class BadgeController extends Controller
{
    /** Curated icon keys — must exist in both the Blade x-icon set and the React map. */
    public const ICONS = [
        'star-solid', 'check-circle', 'bell', 'calendar', 'users', 'user-plus',
        'trophy', 'fire', 'trending-up', 'sparkles', 'bolt', 'heart-solid',
        'eye', 'clock', 'building', 'light-bulb',
    ];

    /** Palette keys — mapped to classes in the Blade badge-chip + React preview. */
    public const COLORS = ['gold', 'sage', 'emerald', 'red', 'blue', 'amber', 'purple', 'sky', 'gray'];

    private function authorizeSuperAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    public function index(): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $badges = Badge::query()
            ->withCount('clinics')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Badge $b) => $this->transform($b));

        return response()->json(['data' => $badges]);
    }

    public function show(Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $badge->loadCount('clinics');
        $data = $this->transform($badge);
        // Manual assignments (for the edit dialog's clinic picker).
        $data['manual_clinics'] = $badge->clinics()
            ->wherePivot('source', 'manual')
            ->get(['clinics.id', 'clinics.name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $this->validatePayload($request, null);
        $badge = Badge::create($data);

        return response()->json(['data' => $this->transform($badge->loadCount('clinics'))], 201);
    }

    public function update(Request $request, Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $this->validatePayload($request, $badge);
        $badge->update($data);

        return response()->json(['data' => $this->transform($badge->loadCount('clinics'))]);
    }

    public function destroy(Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $badge->delete(); // cascades pivot rows
        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Automatic rule types available to bind a badge to. */
    public function rules(): JsonResponse
    {
        $this->authorizeSuperAdmin();

        return response()->json(['data' => [
            'rules'      => BadgeRuleRegistry::meta(),
            'icons'      => self::ICONS,
            'colors'     => self::COLORS,
            'placements' => Badge::PLACEMENTS,
        ]]);
    }

    /** Run the automatic-badge recompute now and return the per-badge counts. */
    public function recompute(ClinicBadgeService $service): JsonResponse
    {
        $this->authorizeSuperAdmin();

        return response()->json(['data' => ['summary' => $service->recompute()]]);
    }

    /** Replace a (manual) badge's clinic assignments with the given list. */
    public function syncClinics(Request $request, Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'clinic_ids'   => ['array'],
            'clinic_ids.*' => ['integer', 'exists:clinics,id'],
        ]);

        $ids = collect($data['clinic_ids'] ?? [])->unique()->values();

        // Replace only the MANUAL rows; leave auto winners intact.
        $badge->clinics()->wherePivot('source', 'manual')->detach();
        foreach ($ids as $cid) {
            $badge->clinics()->syncWithoutDetaching([
                $cid => ['source' => 'manual', 'expires_at' => null],
            ]);
        }

        return response()->json(['data' => ['assigned' => $ids->count()]]);
    }

    /** Lightweight clinic search for the manual-assignment picker. */
    public function searchClinics(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $search = $request->string('search')->toString();

        $clinics = Clinic::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        return response()->json(['data' => $clinics]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?Badge $badge): array
    {
        $ruleKeys = array_keys(BadgeRuleRegistry::rules());

        return $request->validate([
            'key'         => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('badges', 'key')->ignore($badge?->id)],
            'label_ar'    => ['required', 'string', 'max:60'],
            'label_en'    => ['required', 'string', 'max:60'],
            'icon'        => ['required', Rule::in(self::ICONS)],
            'color'       => ['required', Rule::in(self::COLORS)],
            'placement'   => ['required', Rule::in(Badge::PLACEMENTS)],
            'mode'        => ['required', Rule::in([Badge::MODE_MANUAL, Badge::MODE_AUTO])],
            'rule_key'    => ['nullable', 'required_if:mode,auto', Rule::in($ruleKeys)],
            'rule_params' => ['nullable', 'array'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function transform(Badge $b): array
    {
        return [
            'id'             => $b->id,
            'key'            => $b->key,
            'label_ar'       => $b->label_ar,
            'label_en'       => $b->label_en,
            'icon'           => $b->icon,
            'color'          => $b->color,
            'placement'      => $b->placement,
            'mode'           => $b->mode,
            'rule_key'       => $b->rule_key,
            'rule_params'    => $b->rule_params ?? [],
            'is_active'      => $b->is_active,
            'sort_order'     => $b->sort_order,
            'assigned_count' => $b->clinics_count ?? 0,
        ];
    }
}
