<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComplaintResource;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only view for the complex of customer complaints raised AGAINST
 * it. The complex used to be able to FILE complaints from here too, but
 * that surface mixed two unrelated concerns and was redesigned into a
 * proper /clinic/reports endpoint with platform-report types
 * (technical issue, abusive customer, etc.). See ClinicReportController.
 */
class ComplaintController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clinicId = (int) auth('clinic')->id();

        $query = Complaint::query()
            ->where('clinic_id', $clinicId)
            // Hide the (now-deprecated) 'admin'-source rows from the
            // complex's view so it only sees what customers raised.
            ->where('source', 'customer');

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ComplaintResource::collection($query->paginate($perPage)->withQueryString());
    }
}
