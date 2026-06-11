<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\BookingService;
use App\Models\CustomerInterestedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-clinic service insight reports:
 *   - best-selling services (count + net value of purchases)
 *   - most-interested services (interest list counts)
 *   - the customers interested in a given service
 *   - the buyers of a given service
 *
 * All scoped to the acting clinic; purchase reports accept an optional
 * date range over the line-item creation date.
 */
class ServiceReportsController extends Controller
{
    public function bestSelling(Request $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $rows = BookingService::query()
            ->where('booking_services.clinic_id', $clinicId)
            ->join('services', 'services.id', '=', 'booking_services.service_id')
            ->when($request->input('date_from'), fn($q, $d) => $q->whereDate('booking_services.created_at', '>=', $d))
            ->when($request->input('date_to'), fn($q, $d) => $q->whereDate('booking_services.created_at', '<=', $d))
            ->groupBy('booking_services.service_id', 'services.name')
            ->selectRaw('booking_services.service_id as service_id, services.name as service_name, COUNT(*) as purchases, SUM(booking_services.net_price) as value')
            ->orderByDesc('value')
            ->orderByDesc('purchases')
            ->limit(100)
            ->get()
            ->map(fn($r) => [
                'service_id'   => (int) $r->service_id,
                'service_name' => $r->service_name,
                'purchases'    => (int) $r->purchases,
                'value'        => (float) $r->value,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function mostInterested(Request $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $rows = CustomerInterestedService::query()
            ->where('customer_interested_services.clinic_id', $clinicId)
            ->join('services', 'services.id', '=', 'customer_interested_services.service_id')
            ->when($request->input('date_from'), fn($q, $d) => $q->whereDate('customer_interested_services.created_at', '>=', $d))
            ->when($request->input('date_to'), fn($q, $d) => $q->whereDate('customer_interested_services.created_at', '<=', $d))
            ->groupBy('customer_interested_services.service_id', 'services.name')
            ->selectRaw('customer_interested_services.service_id as service_id, services.name as service_name, COUNT(*) as interested')
            ->orderByDesc('interested')
            ->limit(100)
            ->get()
            ->map(fn($r) => [
                'service_id'   => (int) $r->service_id,
                'service_name' => $r->service_name,
                'interested'   => (int) $r->interested,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function interestedCustomers(Request $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $serviceId = (int) $request->input('service_id');

        $rows = CustomerInterestedService::query()
            ->where('clinic_id', $clinicId)
            ->where('service_id', $serviceId)
            ->with('customer:id,name,phone')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn($r) => [
                'customer_id' => $r->customer_id,
                'name'        => $r->customer?->name,
                'phone'       => $r->customer?->phone,
                'created_at'  => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function serviceBuyers(Request $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $serviceId = (int) $request->input('service_id');

        // One row per customer who took this service, with how many times
        // and the total net value they paid for it.
        $rows = BookingService::query()
            ->where('booking_services.clinic_id', $clinicId)
            ->where('booking_services.service_id', $serviceId)
            ->join('bookings', 'bookings.id', '=', 'booking_services.booking_id')
            ->join('customers', 'customers.id', '=', 'bookings.customer_id')
            ->when($request->input('date_from'), fn($q, $d) => $q->whereDate('booking_services.created_at', '>=', $d))
            ->when($request->input('date_to'), fn($q, $d) => $q->whereDate('booking_services.created_at', '<=', $d))
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->selectRaw('customers.id as customer_id, customers.name as name, customers.phone as phone, COUNT(*) as purchases, SUM(booking_services.net_price) as value')
            ->orderByDesc('value')
            ->limit(500)
            ->get()
            ->map(fn($r) => [
                'customer_id' => (int) $r->customer_id,
                'name'        => $r->name,
                'phone'       => $r->phone,
                'purchases'   => (int) $r->purchases,
                'value'       => (float) $r->value,
            ]);

        return response()->json(['data' => $rows]);
    }
}
