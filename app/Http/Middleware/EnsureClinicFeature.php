<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use App\Services\FeatureGate;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates a clinic-side route by a SUBSCRIPTION feature, not a role.
 * Registered as `clinic.feature:crm` — the request passes only when
 * the clinic's package has the named feature enabled (via FeatureGate).
 *
 * Distinct from EnsureClinicRole, which answers "is the acting user
 * ALLOWED to do this?". This answers "does the clinic's PLAN include
 * this?". Both can stack on one route (feature first, then role).
 *
 * Features map to FeatureGate methods below. Default-ON flags (like
 * `crm`) mean nothing changes until an admin opts a package out.
 */
class EnsureClinicFeature
{
    /** feature name → FeatureGate predicate. */
    private const FEATURES = [
        'crm' => 'hasCrmAccess',
    ];

    public function handle(Request $request, Closure $next, string $feature)
    {
        $clinic = auth('clinic')->user();

        if (! $clinic instanceof Clinic) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $method = self::FEATURES[$feature] ?? null;
        if ($method === null) {
            // Unknown feature key is a wiring bug — fail closed loudly in
            // dev, but never silently grant access.
            abort(500, "Unknown clinic feature gate: {$feature}");
        }

        if (! app(FeatureGate::class)->{$method}($clinic)) {
            return response()->json([
                'message' => __('admin.team.forbidden_role'),
                'required_feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
