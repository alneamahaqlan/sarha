<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\IdentifiesCustomer;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Clinic;
use App\Models\Offer;
use App\Models\OtpCode;
use App\Models\Package;
use App\Models\Service;
use App\Services\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Public shopping cart. A customer adds a Service / Offer / Package; guests
 * must OTP-verify on add (which registers + logs them in), so the cart is
 * always user-bound. Inside the cart each item can be removed or booked
 * (per item, reusing the existing per-type booking CTA). The whole feature is
 * gated per clinic by Clinic::cartActive().
 */
class CartController extends Controller
{
    use IdentifiesCustomer;

    /** Public add-to-cart type → model class. */
    private const TYPES = [
        'service' => Service::class,
        'offer'   => Offer::class,
        'package' => Package::class,
    ];

    public function index(Request $request)
    {
        $items = $request->user('web')->cartItems()
            ->with(['cartable.clinic', 'clinic'])
            ->latest()
            ->get();

        // Group by clinic for a clear, per-clinic layout. Each group carries
        // the clinic + whether its cart is still active (gate may have flipped).
        $groups = $items->groupBy('clinic_id')->map(function ($groupItems) {
            $clinic = $groupItems->first()->clinic;

            return [
                'clinic' => $clinic,
                'active' => $clinic && $clinic->cartActive(),
                'rows'   => $groupItems->map(fn (CartItem $item) => $this->row($item))->all(),
            ];
        })->values();

        return view('public.cart', compact('groups'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:service,offer,package',
            'id'   => 'required|integer',
        ]);

        [$model, $clinic] = $this->resolveTarget($data['type'], (int) $data['id']);

        // Per-clinic gate.
        abort_unless($clinic && $clinic->cartActive(), 403);

        // Logged-in (or a returning verified device) → add immediately.
        if ($request->user('web')) {
            if (! $request->user('web')->is_active) {
                return back()->withErrors(['account' => __('site.account_blocked')]);
            }
            $this->store($request->user('web')->id, $model, $clinic);

            return back()->with('success', __('site.cart_added'));
        }

        // Guest → name + phone, then OTP (mirrors ClinicController::book).
        $validated = $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20|regex:/^05\d{8}$/',
        ], ['customer_phone.regex' => __('site.phone_invalid')]);

        if ($this->customerVerified($request, $validated['customer_phone'])) {
            $user = $this->resolveCustomerUser($validated['customer_name'], $validated['customer_phone']);
            if (! $user->is_active) {
                return back()->withErrors(['account' => __('site.account_blocked')]);
            }
            auth('web')->login($user, true);
            $this->store($user->id, $model, $clinic);

            return redirect()->route('cart.index')
                ->with('success', __('site.cart_added'))
                ->withCookie($this->identityCookie($validated['customer_name'], $validated['customer_phone']));
        }

        $otp = OtpCode::generate($validated['customer_phone'], 'cart');
        app(SmsService::class)->send($validated['customer_phone'], __('site.otp_sms', ['code' => $otp->code]));
        session()->put('pending_cart', [
            'type'  => $data['type'],
            'id'    => (int) $data['id'],
            'name'  => $validated['customer_name'],
            'phone' => $validated['customer_phone'],
        ]);

        // Cart-specific flash keys (NOT the booking flow's `otp_required`) so the
        // global cart modal never collides with the booking page's OTP step.
        $redirect = back()
            ->with('cart_otp_required', true)
            ->with('cart_otp_phone', $validated['customer_phone'])
            ->withInput();

        if (app()->environment('local')) {
            $redirect->with('cart_dev_code', $otp->code);
        }

        return $redirect;
    }

    public function addVerify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $pending = session('pending_cart');
        if (! $pending) {
            return redirect()->route('cart.index')->withErrors(['code' => __('site.booking_session_expired')]);
        }

        $otp = OtpCode::where('phone', $pending['phone'])
            ->where('code', $request->string('code'))
            ->where('purpose', 'cart')
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return back()
                ->with('cart_otp_required', true)
                ->with('cart_otp_phone', $pending['phone'])
                ->withErrors(['code' => __('site.otp_invalid')]);
        }

        $otp->update(['is_used' => true]);

        $user = $this->resolveCustomerUser($pending['name'], $pending['phone']);
        if (! $user->is_active) {
            session()->forget('pending_cart');
            return redirect()->route('cart.index')->withErrors(['account' => __('site.account_blocked')]);
        }

        auth('web')->login($user, true);

        // Re-resolve + re-check the gate (it may have flipped during the OTP step).
        [$model, $clinic] = $this->resolveTarget($pending['type'], (int) $pending['id']);
        session()->forget('pending_cart');

        if (! $clinic || ! $clinic->cartActive()) {
            return redirect()->route('cart.index')
                ->withCookie($this->identityCookie($pending['name'], $pending['phone']))
                ->withErrors(['cart' => __('site.cart_unavailable')]);
        }

        $this->store($user->id, $model, $clinic);

        return redirect()->route('cart.index')
            ->with('success', __('site.cart_added'))
            ->withCookie($this->identityCookie($pending['name'], $pending['phone']));
    }

    public function remove(Request $request, CartItem $cartItem)
    {
        // Id-guess guard — a user may only remove their own rows.
        abort_unless($cartItem->user_id === $request->user('web')->id, 404);
        $cartItem->delete();

        return back()->with('success', __('site.cart_removed'));
    }

    /** Resolve a (model, clinic) pair from the public type + id. Aborts 404 if missing. */
    private function resolveTarget(string $type, int $id): array
    {
        $class = self::TYPES[$type] ?? abort(404);
        $model = $class::query()->find($id);
        abort_unless($model, 404);

        return [$model, $model->clinic];
    }

    private function store(int $userId, Model $model, Clinic $clinic): void
    {
        CartItem::firstOrCreate(
            [
                'user_id'       => $userId,
                'cartable_type' => $model->getMorphClass(),
                'cartable_id'   => $model->getKey(),
            ],
            ['clinic_id' => $clinic->id],
        );
    }

    /**
     * Build the per-row view data for the cart page: display name, price, and
     * the per-type "book now" target (mirrors the existing CTA logic in
     * offer.blade.php / service-row / package.blade.php).
     */
    private function row(CartItem $item): array
    {
        $cartable = $item->cartable;
        $clinic   = $item->clinic;

        if (! $cartable) {
            return ['item' => $item, 'deleted' => true, 'name' => '—', 'price' => null, 'book' => null];
        }

        $deleted = method_exists($cartable, 'trashed') && $cartable->trashed();

        $book = null;
        if (! $deleted && $clinic) {
            $book = $this->bookTarget($item->cartable_type, $cartable, $clinic);
        }

        return [
            'item'     => $item,
            'deleted'  => $deleted,
            'name'     => $cartable->name ?? $cartable->title ?? '—',
            'price'    => $cartable->price ?? null,
            'priceFrom' => (bool) ($cartable->price_from ?? false),
            'book'     => $book,
        ];
    }

    private function bookTarget(string $type, Model $cartable, Clinic $clinic): array
    {
        if ($type === Service::class) {
            return ['mode' => 'booking', 'newTab' => false,
                'href' => route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $cartable->id])];
        }

        if ($type === Package::class) {
            return ['mode' => 'booking', 'newTab' => false,
                'href' => route('clinic.book.form', $clinic->slug)];
        }

        // Offer: service-linked → booking; general → contact (whatsapp/tel).
        if ($cartable->type === Offer::TYPE_SERVICE && $cartable->service) {
            return ['mode' => 'booking', 'newTab' => false,
                'href' => route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $cartable->service_id])];
        }

        return ['mode' => 'contact', 'newTab' => true,
            'href' => $clinic->whatsappLink() ?: ($clinic->phone ? 'tel:' . $clinic->phone : '#')];
    }
}
