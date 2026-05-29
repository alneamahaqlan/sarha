<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fire-and-forget click tracker for the public clinic page action buttons
 * (WhatsApp / call / book). Called via navigator.sendBeacon, so it is
 * CSRF-excluded (see bootstrap/app.php) and always returns 204.
 */
class TrackingController extends Controller
{
    private const MAP = [
        'whatsapp'   => 'whatsapp_clicks',
        'call'       => 'call_clicks',
        'booking'    => 'booking_clicks',
        'directions' => 'directions_clicks',
    ];

    public function click(Request $request): Response
    {
        $type = (string) $request->input('type');
        $clinicId = (int) $request->input('clinic');

        if (isset(self::MAP[$type]) && $clinicId > 0) {
            try {
                if (Clinic::whereKey($clinicId)->exists()) {
                    ClinicStat::bump($clinicId, self::MAP[$type]);

                    // Profile timeline: only when a known user clicked.
                    // sendBeacon often fires from anonymous tabs too,
                    // and we don't want to invent a "ghost user".
                    if (auth('web')->check()) {
                        app(UserActivityLogger::class)->logActionClick(
                            $request, auth('web')->id(), $clinicId, $type,
                        );
                    }
                }
            } catch (\Throwable $e) {
                // ignore — tracking must never surface an error
            }
        }

        return response()->noContent();
    }
}
