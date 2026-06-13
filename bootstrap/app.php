<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('saerha:notify-subscription-expiry')->dailyAt('06:00');
        $schedule->command('saerha:sync-google-reviews')->daily();
        $schedule->command('saerha:sync-clinics-sheet')->everySixHours();
        // Contact reminders fire close to their scheduled minute (±10m).
        $schedule->command('saerha:dispatch-contact-reminders')->everyTenMinutes();
        // Overdue sales-lead follow-ups — hourly is plenty for date-based cues.
        $schedule->command('saerha:notify-sales-followups')->hourly();
        // Abandoned-cart nudges — hourly; the command only picks up items
        // aged past the configured window (default 24h) and never re-nudges.
        $schedule->command('saerha:dispatch-cart-reminders')->hourly();
        // Self-heal yesterday's landing-page analytics rollup overnight.
        $schedule->command('saerha:rollup-landing-stats')->dailyAt('02:30');
        // Recompute automatic badges (most booked / fastest growing …) daily.
        $schedule->command('saerha:badges-recompute')->dailyAt('03:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            // Heartbeat for the super-admin "comprehensive user
            // profile" timeline — extends or opens the visit_session
            // row on every authenticated web request. Failures are
            // swallowed inside the middleware.
            \App\Http\Middleware\TrackUserVisit::class,
            // Resolves the marketing-pixel TrackingContext for public
            // clinic pages (inert unless the feature flag is on and the
            // clinic's tracking is approved). Failures are swallowed.
            \App\Http\Middleware\ResolveTrackingContext::class,
        ]);

        $middleware->statefulApi();

        // sendBeacon trackers can't carry a CSRF token.
        $middleware->validateCsrfTokens(except: ['track/click', 'track/story', 'l/track/*']);

        $middleware->alias([
            'api.guard'   => \App\Http\Middleware\EnsureApiGuard::class,
            'api.locale'  => \App\Http\Middleware\SetLocale::class,
            'clinic.role' => \App\Http\Middleware\EnsureClinicRole::class,
            'clinic.feature' => \App\Http\Middleware\EnsureClinicFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'This action is unauthorized.'], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'HTTP error.',
                ], $e->getStatusCode());
            }

            // A 419 on a web POST is almost always a stale form or a double
            // submit (e.g. the OTP "verify" tapped twice: the first request
            // logged the user in and migrated the session, so the second
            // arrives with a now-deleted session cookie → token mismatch).
            // Laravel has already mapped the TokenMismatchException to a 419
            // HttpException by the time render callbacks run, so we key off
            // the status here. Never show the scary "Page Expired" page: if
            // the user is in fact already authenticated, send them on their
            // way; otherwise bounce back to the form with a notice.
            if ($e->getStatusCode() === 419) {
                if (auth('web')->check()) {
                    return redirect()->intended(route('home'));
                }

                return redirect()->back()
                    ->withInput($request->except('_token', 'code', 'password'))
                    ->with('error', __('site.session_expired_retry'));
            }
        });
    })->create();
