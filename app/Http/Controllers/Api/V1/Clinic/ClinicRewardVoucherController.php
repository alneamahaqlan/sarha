<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\GrantRewardRequest;
use App\Http\Requests\Api\V1\Clinic\RedeemRewardRequest;
use App\Http\Resources\Api\V1\RewardVoucherResource;
use App\Models\Booking;
use App\Models\RewardVoucher;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The clinic's issued vouchers: list, manual grant, and reception
 * redemption. Every path is isolated to the acting clinic (query filter
 * + RewardVoucherPolicy). Redemption always flows through
 * RewardService::redeemChecked so the gate (active, not expired, this
 * clinic, type matches the booking) is never bypassed.
 */
class ClinicRewardVoucherController extends Controller
{
    public function __construct(private readonly RewardService $rewards) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RewardVoucher::class);

        $query = RewardVoucher::query()
            ->where('clinic_id', auth('clinic')->id())
            ->with(['offer:id,title', 'service:id,name', 'customer:id,name'])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return RewardVoucherResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(GrantRewardRequest $request): JsonResponse
    {
        $this->authorize('create', RewardVoucher::class);

        $clinic = auth('clinic')->user();
        $data = $request->validated();

        $voucher = $this->rewards->grantManual(
            $clinic,
            $data['phone'],
            [
                'type'           => $data['type'],
                'offer_id'       => $data['offer_id'] ?? null,
                'service_id'     => $data['service_id'] ?? null,
                'discount_type'  => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
            ],
            isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
        );

        return response()->json([
            'data' => new RewardVoucherResource($voucher->load(['offer:id,title', 'service:id,name'])),
        ], 201);
    }

    public function redeem(RedeemRewardRequest $request, RewardVoucher $voucher): JsonResponse
    {
        $this->authorize('update', $voucher);

        $clinic = auth('clinic')->user();
        $booking = null;
        if ($bookingId = $request->validated()['booking_id'] ?? null) {
            $booking = Booking::where('clinic_id', $clinic->id)->find($bookingId);
        }

        try {
            $this->rewards->redeemChecked($voucher, $clinic, $booking);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => __('rewards.errors.' . $e->getMessage()),
                'error'   => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new RewardVoucherResource(
                $voucher->fresh()->load(['offer:id,title', 'service:id,name', 'redeemedBooking:id,reference_code'])
            ),
        ]);
    }
}
