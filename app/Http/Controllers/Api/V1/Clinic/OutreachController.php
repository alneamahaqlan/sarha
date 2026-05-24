<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\ClinicStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records when a complex reaches out to a customer (WhatsApp / call) from a
 * booking or quote. Counts as an interaction AND as a "visibility" (the
 * customer discovered the complex through our platform).
 */
class OutreachController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'    => 'required|in:whatsapp,call',
            'context' => 'nullable|in:booking,quote',
            'ref_id'  => 'nullable|integer',
        ]);

        $clinicId = (int) auth('clinic')->id();
        $field = $data['type'] === 'whatsapp' ? 'whatsapp_clicks' : 'call_clicks';

        ClinicStat::bump($clinicId, $field);
        // The customer reached this complex via our system → counts as a visibility.
        ClinicStat::bump($clinicId, 'search_appearances');

        return response()->json(['ok' => true]);
    }
}
