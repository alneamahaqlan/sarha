<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clinic;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public "List your complex" form. Submissions create a Clinic with
 * status=pending so the complex lands directly in the admin "Awaiting
 * approval" (بانتظار الاعتماد) list. The admin then approves it via
 * ClinicService::approve, which assigns the subscription package and
 * flips the status to active.
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
            'tax_number'     => ['nullable', 'string', 'max:255'],
            'commercial_registration' => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => __('site.phone_invalid'),
        ]);

        $clinic = Clinic::create([
            'name'           => $validated['clinic_name'],
            'slug'           => Str::slug($validated['clinic_name']) . '-' . Str::lower(Str::random(4)),
            'phone'          => $validated['phone'],
            'email'          => $validated['email'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'tax_number'     => $validated['tax_number'] ?? null,
            'commercial_registration' => $validated['commercial_registration'] ?? null,
            // Random password: the complex cannot sign in until the admin
            // approves it and issues real credentials (ClinicService::approve).
            'password'       => bcrypt(Str::random(16)),
            'city_id'        => $validated['city_id'],
            'district'       => $validated['district'] ?? null,
            // The clinics table has no contact_name/notes columns, so the
            // person + free-text note from the form are stashed in the
            // (admin-only while pending) description and overwritten with
            // real marketing copy on approval.
            'description'    => $this->registrationNote($validated),
            'status'         => 'pending',
        ]);

        $notifications->newClinicRegistration($clinic);

        return redirect()
            ->route('clinic.register')
            ->with('success', __('site.register_clinic_success'));
    }

    /**
     * Build a short admin-facing note from the submitter's name and any
     * free-text they left, so neither is lost on a status=pending clinic
     * that has no dedicated columns for them.
     */
    private function registrationNote(array $data): string
    {
        return trim(implode("\n", array_filter([
            __('site.register_clinic_contact', ['name' => $data['contact_name']]),
            ! empty($data['notes']) ? $data['notes'] : null,
        ])));
    }
}
