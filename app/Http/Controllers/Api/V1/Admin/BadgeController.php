<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Services\ClinicBadgeService;
use App\Support\BadgeIcons;
use App\Support\BadgeRuleRegistry;
use App\Support\BadgeTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Badges Center — super-admin CRUD over display badges, manual assignment to
 * any badgeable entity (clinic/offer/service/doctor), and on-demand recompute
 * of the automatic ones.
 */
class BadgeController extends Controller
{
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
            ->withCount(['clinics', 'offers', 'services', 'doctors'])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Badge $b) => $this->transform($b));

        return response()->json(['data' => $badges]);
    }

    public function show(Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $badge->loadCount(['clinics', 'offers', 'services', 'doctors']);
        $data = $this->transform($badge);
        $data['manual_targets'] = $this->manualTargets($badge);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $this->validatePayload($request, null);
        $badge = Badge::create($data);

        return response()->json(['data' => $this->transform($badge->loadCount(['clinics', 'offers', 'services', 'doctors']))], 201);
    }

    public function update(Request $request, Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $this->validatePayload($request, $badge);
        $badge->update($data);

        return response()->json(['data' => $this->transform($badge->loadCount(['clinics', 'offers', 'services', 'doctors']))]);
    }

    public function destroy(Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $badge->delete(); // cascades pivot rows
        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Automatic rule types + the icon/color/target/placement vocabularies. */
    public function rules(): JsonResponse
    {
        $this->authorizeSuperAdmin();

        return response()->json(['data' => [
            'rules'      => BadgeRuleRegistry::meta(),
            'icons'      => BadgeIcons::meta(),
            'colors'     => self::COLORS,
            'placements' => Badge::PLACEMENTS,
            'targets'    => BadgeTargets::meta(),
        ]]);
    }

    /** Run the automatic-badge recompute now and return the per-badge counts. */
    public function recompute(ClinicBadgeService $service): JsonResponse
    {
        $this->authorizeSuperAdmin();

        return response()->json(['data' => ['summary' => $service->recompute()]]);
    }

    /** Replace a badge's MANUAL assignments of one target type with the given list. */
    public function syncTargets(Request $request, Badge $badge): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'type'  => ['required', Rule::in(BadgeTargets::aliases())],
            'ids'   => ['array'],
            'ids.*' => ['integer'],
        ]);

        $relation = $this->relationFor($badge, $data['type']);
        $ids = collect($data['ids'] ?? [])->map(fn ($v) => (int) $v)->unique()->values();

        // Replace only the MANUAL rows for this type; leave auto winners intact.
        $relation->wherePivot('source', 'manual')->detach();
        foreach ($ids as $id) {
            $relation->syncWithoutDetaching([$id => ['source' => 'manual', 'expires_at' => null]]);
        }

        return response()->json(['data' => ['assigned' => $ids->count()]]);
    }

    /** Lightweight entity search for the manual-assignment picker (?type=&search=). */
    public function searchTargets(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $type = $request->string('type')->toString();
        abort_unless(in_array($type, BadgeTargets::aliases(), true), 422, 'نوع غير صالح.');

        $results = BadgeTargets::search($type, $request->string('search')->toString());

        return response()->json(['data' => $results->values()]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** The morphedByMany relation on the badge for a given target alias. */
    private function relationFor(Badge $badge, string $alias)
    {
        return match ($alias) {
            'clinic'  => $badge->clinics(),
            'offer'   => $badge->offers(),
            'service' => $badge->services(),
            'doctor'  => $badge->doctors(),
        };
    }

    /** Manual assignments grouped by target alias, for the edit dialog's pickers. */
    private function manualTargets(Badge $badge): array
    {
        $out = [];
        foreach (BadgeTargets::aliases() as $alias) {
            $rel = $this->relationFor($badge, $alias);
            $rows = $rel->wherePivot('source', 'manual')->get();
            $out[$alias] = $rows->map(fn ($m) => [
                'id'   => $m->id,
                'name' => $m->name ?? $m->title ?? ('#'.$m->id),
            ])->values();
        }
        return $out;
    }

    private function validatePayload(Request $request, ?Badge $badge): array
    {
        $ruleKeys = array_keys(BadgeRuleRegistry::rules());

        return $request->validate([
            'key'            => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('badges', 'key')->ignore($badge?->id)],
            'target_types'   => ['required', 'array', 'min:1'],
            'target_types.*' => [Rule::in(BadgeTargets::aliases())],
            'label_ar'       => ['required', 'string', 'max:60'],
            'label_en'       => ['required', 'string', 'max:60'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:255'],
            'icon'           => ['required', Rule::in(BadgeIcons::keys())],
            'color'          => ['required', Rule::in(self::COLORS)],
            'placement'      => ['required', Rule::in(Badge::PLACEMENTS)],
            'mode'           => ['required', Rule::in([Badge::MODE_MANUAL, Badge::MODE_AUTO])],
            'rule_key'       => ['nullable', 'required_if:mode,auto', Rule::in($ruleKeys)],
            'rule_params'    => ['nullable', 'array'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function transform(Badge $b): array
    {
        return [
            'id'             => $b->id,
            'key'            => $b->key,
            'target_types'   => $b->target_types ?? [],
            'label_ar'       => $b->label_ar,
            'label_en'       => $b->label_en,
            'description_ar' => $b->description_ar,
            'description_en' => $b->description_en,
            'icon'           => $b->icon,
            'color'          => $b->color,
            'placement'      => $b->placement,
            'mode'           => $b->mode,
            'rule_key'       => $b->rule_key,
            'rule_params'    => $b->rule_params ?? [],
            'is_active'      => $b->is_active,
            'sort_order'     => $b->sort_order,
            'assigned_count' => ($b->clinics_count ?? 0) + ($b->offers_count ?? 0)
                + ($b->services_count ?? 0) + ($b->doctors_count ?? 0),
        ];
    }
}
