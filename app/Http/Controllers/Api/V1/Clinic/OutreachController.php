<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\ClinicStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records when a complex reaches out to a customer (WhatsApp / call) from a
 * booking or quote. Counts the interaction click only. It is deliberately
 * NOT counted as an "impression/visibility": public impressions live solely
 * in clinic_impressions (via ImpressionTrackerService), and a reception-logged
 * outreach is not a public surfacing. See docs/qa/TC-001-impressions-discrepancy.md.
 */
class OutreachController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:whatsapp,call',
            'context' => 'nullable|in:booking,quote',
            'ref_id' => 'nullable|integer',
        ]);

        $clinicId = (int) auth('clinic')->id();
        $field = $data['type'] === 'whatsapp' ? 'whatsapp_clicks' : 'call_clicks';

        ClinicStat::bump($clinicId, $field);
        // NOTE: previously also bumped the legacy clinic_stats.search_appearances
        // column here. Removed — that column is no longer the impressions source
        // of truth (clinic_impressions is), so the dual-write only risked drift.

        return response()->json(['ok' => true]);
    }
}
