<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreClinicReportRequest;
use App\Http\Resources\Api\V1\ClinicReportResource;
use App\Models\Admin;
use App\Models\ClinicReport;
use App\Models\PlatformNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Reports filed BY the authenticated complex to the platform admins.
 * Replaces the previous "clinic complaints" surface which was reusing
 * customer complaint types ("quality", "pricing"…) that made no sense
 * from the complex's perspective.
 */
class ReportController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ClinicReport::query()
            ->where('clinic_id', $this->clinicId());

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }
        $query->orderByDesc('created_at');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ClinicReportResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreClinicReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $report = ClinicReport::create([
            'reference_code' => 'RPT-' . strtoupper(Str::random(8)),
            'clinic_id'      => $this->clinicId(),
            'type'           => $data['type'],
            'priority'       => $data['priority'] ?? 'medium',
            'status'         => 'new',
            'subject'        => $data['subject'],
            'description'    => $data['description'],
        ]);

        // Notify every active admin so the queue isn't missed. Same pattern
        // used for category requests / complaints.
        $clinicName = optional(auth('clinic')->user())->name ?? '—';
        foreach (Admin::where('is_active', true)->get(['id']) as $admin) {
            PlatformNotification::create([
                'notifiable_type' => Admin::class,
                'notifiable_id'   => $admin->id,
                'type'            => 'clinic_report.new',
                'icon'            => 'heroicon-o-exclamation-triangle',
                'url'             => '/app/admin/clinic-reports',
                'priority'        => $report->priority === 'high' ? 'urgent' : 'normal',
                'title'           => 'بلاغ جديد من مجمع',
                'body'            => "{$clinicName}: {$report->subject}",
                'data'            => ['clinic_report_id' => $report->id, 'type' => $report->type],
            ]);
        }

        return (new ClinicReportResource($report))->response()->setStatusCode(201);
    }
}
