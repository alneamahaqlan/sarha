<?php

namespace App\Http\Middleware;

use App\Support\ActingClinicUser;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates a clinic-side route by ability pattern. Registered as
 * `clinic.role:ability1,ability2,...` — the request passes if the
 * acting user has ANY of the listed abilities (OR semantics).
 *
 * Owner always passes (their abilities pattern is `*`). Team members
 * pass only when their role's grant list matches one of the patterns.
 *
 * 403 + a friendly message is returned on denial; the React layer
 * intercepts the 403 and redirects to the clinic dashboard with a
 * toast.
 */
class EnsureClinicRole
{
    public function handle(Request $request, Closure $next, string ...$abilities)
    {
        // Guard: middleware should only run after auth, but be safe.
        if (! ActingClinicUser::clinicId()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // No abilities specified → middleware is a no-op (treated as
        // "any authenticated clinic user").
        if (empty($abilities)) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if (ActingClinicUser::can($ability)) {
                return $next($request);
            }
        }

        return response()->json([
            // Inlined because there's no global `auth.php` lang file
            // in this project — the framework's default is used for
            // auth.failed but nothing else.
            'message' => __('admin.team.forbidden_role'),
            'required' => $abilities,
            'role' => ActingClinicUser::role()->value,
        ], 403);
    }
}
