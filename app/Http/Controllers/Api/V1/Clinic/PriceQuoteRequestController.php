<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreQuoteReplyRequest;
use App\Http\Resources\Api\V1\QuoteRequestResource;
use App\Models\ClinicTeamMember;
use App\Models\PriceQuoteReply;
use App\Models\PriceQuoteRequest;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Broadcast quote requests as seen by the authenticated complex: every request
 * targeting the complex's city, with the ability to post one reply (public or
 * private) per request.
 */
class PriceQuoteRequestController extends Controller
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    private function clinic()
    {
        return auth('clinic')->user();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $clinic = $this->clinic();

        $query = PriceQuoteRequest::query()
            ->whereHas('cities', fn ($q) => $q->where('cities.id', $clinic->city_id))
            ->with([
                'cities:id,name,name_en',
                'replies' => fn ($q) => $q->where('clinic_id', $clinic->id),
            ])
            ->withCount('replies');

        if ($search = $request->string('search')->toString()) {
            // Phone is hidden from clinics, so it is not a searchable field here.
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('service_name', 'like', "%{$search}%");
            });
        }

        // filter: replied = this clinic already replied; pending = not yet.
        $filter = $request->string('filter.status')->toString();
        if ($filter === 'replied') {
            $query->whereHas('replies', fn ($q) => $q->where('clinic_id', $clinic->id));
        } elseif ($filter === 'pending') {
            $query->whereDoesntHave('replies', fn ($q) => $q->where('clinic_id', $clinic->id));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return QuoteRequestResource::collection($query->paginate($perPage)->withQueryString());
    }

    /** This clinic's price-quote access state (for the reply gate UI). */
    public function access(): JsonResponse
    {
        $clinic = $this->clinic();

        return response()->json([
            'data' => [
                'price_quote_status'           => $clinic->price_quote_status,
                'price_quote_rejection_reason' => $clinic->price_quote_rejection_reason,
                'price_quote_requested_at'     => $clinic->price_quote_requested_at,
            ],
        ]);
    }

    /** Ask the super-admin to (re)enable replies after a disable/rejection. */
    public function requestAccess(): JsonResponse
    {
        $clinic = $this->clinic();

        // Only meaningful from a blocked state; 'active'/'pending' are no-ops.
        if (in_array($clinic->price_quote_status, ['disabled', 'rejected'], true)) {
            $clinic->update([
                'price_quote_status'           => 'pending',
                'price_quote_rejection_reason' => null,
                'price_quote_requested_at'     => now(),
            ]);
        }

        return $this->access();
    }

    /** Create or update this clinic's reply to a broadcast request. */
    public function reply(StoreQuoteReplyRequest $request, PriceQuoteRequest $priceQuote): JsonResponse
    {
        $clinic = $this->clinic();

        // Reply gate: the clinic can always SEE requests, but may only reply
        // while the super-admin keeps its price-quote access 'active'.
        abort_unless($clinic->priceQuoteReplyActive(), 403, __('site.price_quote_reply_locked'));

        // The complex may only reply to requests targeting its city.
        abort_unless(
            $priceQuote->cities()->where('cities.id', $clinic->city_id)->exists(),
            403,
        );

        $data = $request->validated();
        $actor = ActingClinicUser::actor();
        $role  = ActingClinicUser::role();

        // Snapshot the member identity onto the reply so the customer-
        // facing view can attribute the answer even after the member
        // is soft-deleted. Owner replies leave the FK null and only
        // carry the name snapshot.
        $reply = PriceQuoteReply::updateOrCreate(
            ['price_quote_request_id' => $priceQuote->id, 'clinic_id' => $clinic->id],
            [
                'body'                       => $data['body'],
                'price'                      => $data['price'] ?? null,
                'is_public'                  => (bool) ($data['is_public'] ?? false),
                'replied_by_member_id'       => $actor instanceof ClinicTeamMember ? $actor->id : null,
                'replied_by_name_snapshot'   => ActingClinicUser::actorName(),
                'replied_by_role_snapshot'   => $role->value,
            ],
        );

        if ($priceQuote->status === 'new') {
            $priceQuote->update(['status' => 'replied']);
        }

        $this->activity->log('price_quote.replied', $priceQuote, [
            'customer'   => $priceQuote->customer_name,
            'service'    => $priceQuote->service_name,
            'reply_id'   => $reply->id,
        ]);

        $priceQuote->load([
            'cities:id,name,name_en',
            'replies' => fn ($q) => $q->where('clinic_id', $clinic->id),
        ])->loadCount('replies');

        return (new QuoteRequestResource($priceQuote))->response();
    }
}
