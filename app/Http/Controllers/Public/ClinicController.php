<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function show(string $slug)
    {
        $clinic = Clinic::where('slug', $slug)
            ->where('status', 'active')
            ->with([
                'city',
                'categories',
                'services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'articles' => fn($q) => $q->where('is_published', true)->latest()->limit(3),
            ])
            ->firstOrFail();

        return view('public.clinic', compact('clinic'));
    }

    public function book(Request $request, string $slug)
    {
        $clinic = Clinic::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'service_id' => 'nullable|exists:services,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        Booking::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth('web')->id(),
            'service_id' => $validated['service_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'new',
            'source' => 'website',
        ]);

        return back()->with('success', __('site.booking_success'));
    }
}
