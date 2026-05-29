<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateCustomerReportRequest;
use App\Http\Resources\Api\V1\CustomerReportResource;
use App\Models\CustomerReport;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

/**
 * Admin review queue for customer-filed platform reports. Mirrors the
 * clinic-reports queue so the admin UI stays consistent across the two
 * sources.
 */
class CustomerReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CustomerReport::query()->with(['user:id,name,phone', 'clinic:id,name'])->latest();

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }
        if ($type = $request->string('filter.type')->toString()) {
            $query->where('type', $type);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return CustomerReportResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(CustomerReport $customerReport): CustomerReportResource
    {
        return new CustomerReportResource($customerReport->load(['user:id,name,phone', 'clinic:id,name']));
    }

    public function update(UpdateCustomerReportRequest $request, CustomerReport $customerReport): CustomerReportResource
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            if (in_array($data['status'], ['resolved', 'rejected'], true) && ! $customerReport->resolved_at) {
                $data['resolved_at']       = now();
                $data['assigned_admin_id'] = (int) auth('admin')->id();
            }
            if (! in_array($data['status'], ['resolved', 'rejected'], true)) {
                $data['resolved_at'] = null;
            }
        }

        $customerReport->update($data);

        return new CustomerReportResource($customerReport->fresh()->load(['user:id,name,phone', 'clinic:id,name']));
    }
}
