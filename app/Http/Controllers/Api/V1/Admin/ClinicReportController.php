<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateClinicReportRequest;
use App\Http\Resources\Api\V1\ClinicReportResource;
use App\Models\ClinicReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin review queue for clinic-side platform reports. Kept separate from
 * the customer complaints queue because the workflow and concerns differ
 * (a report about a fraudulent customer doesn't belong in the same view
 * as a customer complaint about a clinic's pricing).
 */
class ClinicReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ClinicReport::query()->with('clinic:id,name')->latest();

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }
        if ($type = $request->string('filter.type')->toString()) {
            $query->where('type', $type);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return ClinicReportResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(ClinicReport $clinicReport): ClinicReportResource
    {
        return new ClinicReportResource($clinicReport->load('clinic:id,name'));
    }

    public function update(UpdateClinicReportRequest $request, ClinicReport $clinicReport): ClinicReportResource
    {
        $data = $request->validated();

        // Resolved-at timestamp auto-flips when transitioning into a
        // terminal state and clears when re-opened.
        if (isset($data['status'])) {
            if (in_array($data['status'], ['resolved', 'rejected'], true) && ! $clinicReport->resolved_at) {
                $data['resolved_at']       = now();
                $data['assigned_admin_id'] = (int) auth('admin')->id();
            }
            if (! in_array($data['status'], ['resolved', 'rejected'], true)) {
                $data['resolved_at'] = null;
            }
        }

        $clinicReport->update($data);

        return new ClinicReportResource($clinicReport->fresh()->load('clinic:id,name'));
    }
}
