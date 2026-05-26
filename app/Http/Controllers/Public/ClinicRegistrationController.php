<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\SalesLead;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Public "List your complex" form. Submissions create a SalesLead (status=new)
 * that lands in the admin sales pipeline — the admin then converts it into an
 * active clinic via the existing SalesLeadService::convertLead flow.
 */
class ClinicRegistrationController extends Controller
{
    public function show()
    {
        $cities = City::where('is_active', true)->orderBy('sort_order')->get();

        return view('public.register-clinic', compact('cities'));
    }

    public function store(Request $request, NotificationService $notifications)
    {
        $validated = $request->validate([
            'clinic_name'    => ['required', 'string', 'max:255'],
            'contact_name'   => ['required', 'string', 'max:255'],
            'phone'          => ['required', 'string', 'regex:/^05\d{8}$/'],
            'email'          => ['nullable', 'email', 'max:255'],
            'city_id'        => ['required', 'integer', 'exists:cities,id'],
            'district'       => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => __('site.phone_invalid'),
        ]);

        $lead = SalesLead::create(array_merge($validated, ['status' => 'new']));

        $notifications->newSalesLead($lead);

        return redirect()
            ->route('clinic.register')
            ->with('success', __('site.register_clinic_success'));
    }
}
