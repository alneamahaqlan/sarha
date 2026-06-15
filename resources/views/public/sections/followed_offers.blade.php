{{-- CMS section type `followed_offers` — live offers from the complexes the
     signed-in customer follows. Data resolved per-request in
     HomepageRenderService::followedOffers(); the partial self-hides when empty. --}}
@include('public.partials.followed-offers', ['followedOffers' => $data['offers'] ?? collect()])
