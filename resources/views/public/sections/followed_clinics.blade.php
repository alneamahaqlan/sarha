{{-- CMS section type `followed_clinics` — the complexes the signed-in customer
     follows. Data resolved per-request in HomepageRenderService::followedClinics();
     the partial self-hides when empty. --}}
@include('public.partials.followed-clinics', ['followedClinics' => $data['clinics'] ?? collect()])
