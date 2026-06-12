<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerInterestedService;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages a customer's interested-services list — the services the
 * clinic notes a customer wants but has not purchased. Feeds the
 * interest reports.
 */
class CustomerInterestedServiceController extends Controller
{
    public function store(Request $request, Customer $customer): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        abort_unless($customer->clinic_id === $clinicId, 404);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
        ]);

        $service = Service::where('clinic_id', $clinicId)
            ->findOrFail($validated['service_id']);

        // Idempotent — the (customer_id, service_id) unique constraint
        // guards duplicates; firstOrCreate avoids a 500 on re-add.
        CustomerInterestedService::firstOrCreate(
            ['customer_id' => $customer->id, 'service_id' => $service->id],
            ['clinic_id' => $clinicId],
        );

        return response()->json([
            'data' => ['id' => $service->id, 'name' => $service->name],
        ], 201);
    }

    public function destroy(Customer $customer, Service $service): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        abort_unless($customer->clinic_id === $clinicId, 404);

        CustomerInterestedService::where('customer_id', $customer->id)
            ->where('service_id', $service->id)
            ->delete();

        return response()->json(['data' => ['removed' => true]]);
    }
}
