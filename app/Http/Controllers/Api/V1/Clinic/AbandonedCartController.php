<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Concerns\PresentsCartItems;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CartContact;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Service;
use App\Models\User;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clinic-side abandoned-cart follow-up. Lists this clinic's unbooked cart
 * items grouped by customer, lets the team log an outreach (which feeds the
 * "contacted recently" flag) and convert a cart into a Kanban booking draft.
 * Always scoped to the authenticated clinic. Gated by `cart_leads.*`.
 */
class AbandonedCartController extends Controller
{
    use PresentsCartItems;

    private const RECENT_DAYS = 3;

    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    /** This clinic's abandoned carts grouped by customer. */
    public function index(): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $items = CartItem::abandoned()
            ->where('clinic_id', $clinicId)
            ->with(['cartable', 'user:id,name,phone'])
            ->latest()
            ->get();

        $lastContacts = CartContact::where('clinic_id', $clinicId)
            ->selectRaw('user_id, MAX(created_at) as last_contacted_at')
            ->groupBy('user_id')
            ->pluck('last_contacted_at', 'user_id');

        $recentCutoff = now()->subDays(self::RECENT_DAYS);

        $carts = $items->groupBy('user_id')->map(function ($userItems) use ($lastContacts, $recentCutoff) {
            $user = $userItems->first()->user;
            $last = $lastContacts->get($userItems->first()->user_id);
            $lastAt = $last ? \Illuminate\Support\Carbon::parse($last) : null;

            return [
                'user'               => $user ? ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone] : null,
                'items'              => $userItems->map(fn (CartItem $i) => $this->cartItemRow($i))->values(),
                'last_contacted_at'  => $lastAt?->toIso8601String(),
                'contacted_recently' => $lastAt ? $lastAt->gte($recentCutoff) : false,
            ];
        })->filter(fn ($c) => $c['user'] !== null)->values();

        return response()->json(['data' => $carts]);
    }

    /** Log an outreach to a customer about their abandoned cart. */
    public function contact(Request $request, User $user): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $this->assertHasAbandonedItem($clinicId, $user->id);

        $data = $request->validate([
            'channel' => ['nullable', 'string', 'in:' . implode(',', CartContact::CHANNELS)],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        CartContact::create([
            'clinic_id'       => $clinicId,
            'user_id'         => $user->id,
            'channel'         => $data['channel'] ?? 'manual',
            'note'            => $data['note'] ?? null,
            'created_by_type' => ActingClinicUser::actorType(),
            'created_by_id'   => ActingClinicUser::actorId(),
            'created_by_name' => ActingClinicUser::actorName(),
        ]);

        $this->activity->log('cart.contacted', $user, [
            'customer'      => $user->name,
            'customer_phone'=> $user->phone,
            'channel'       => $data['channel'] ?? 'manual',
        ]);

        return response()->json([
            'data' => [
                'whatsapp_link' => $this->whatsappLink($user->phone),
                'tel_link'      => $user->phone ? 'tel:' . $user->phone : null,
                'contacted_at'  => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Convert a customer's abandoned cart into one Kanban booking draft and
     * stamp the converted items as booked. Service id is taken from the first
     * item that resolves to a service; all item names go into the notes.
     */
    public function convert(User $user): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $items = CartItem::abandoned()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $user->id)
            ->with('cartable')
            ->get();

        abort_if($items->isEmpty(), 404, 'لا توجد عناصر متروكة لهذا العميل.');

        $serviceId = null;
        $names = [];
        foreach ($items as $item) {
            $names[] = $item->cartable->name ?? $item->cartable->title ?? '—';
            $serviceId ??= $this->resolveServiceId($item);
        }

        $booking = Booking::create([
            'clinic_id'          => $clinicId,
            'user_id'            => $user->id,
            'customer_name'      => $user->name,
            'customer_phone'     => $user->phone,
            'service_id'         => $serviceId,
            'status'             => 'new',
            'source'             => 'clinic',
            'acquisition_source' => 'cart',
            'notes'              => __('site.cart_title') . ': ' . implode('، ', $names),
        ]);

        // Lift the converted items out of "abandoned".
        CartItem::whereIn('id', $items->pluck('id'))->update(['booked_at' => now()]);

        $this->activity->log('cart.converted', $booking, [
            'reference' => $booking->reference_code,
            'customer'  => $user->name,
            'items'     => count($names),
        ]);

        return response()->json([
            'data' => [
                'booking_id'     => $booking->id,
                'reference_code' => $booking->reference_code,
            ],
        ], 201);
    }

    // ---------- helpers ----------

    private function assertHasAbandonedItem(int $clinicId, int $userId): void
    {
        $exists = CartItem::abandoned()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($exists, 404, 'لا توجد سلة متروكة لهذا العميل.');
    }

    /** Service id for a cart item — direct service, or a service-linked offer. */
    private function resolveServiceId(CartItem $item): ?int
    {
        $cartable = $item->cartable;
        if (! $cartable) {
            return null;
        }
        if ($item->cartable_type === Service::class) {
            return (int) $cartable->getKey();
        }
        if ($item->cartable_type === Offer::class && $cartable->type === Offer::TYPE_SERVICE && $cartable->service_id) {
            return (int) $cartable->service_id;
        }
        return null;
    }

    private function whatsappLink(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '05')) {
            $digits = '966' . substr($digits, 1);
        }
        return 'https://wa.me/' . $digits;
    }
}
