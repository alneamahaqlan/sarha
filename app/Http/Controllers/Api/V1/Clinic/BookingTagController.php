<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCustomerTag;
use App\Models\BookingTag;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages booking-scoped tags AND customer-scoped tags (per phone +
 * clinic). Single controller for both, with `scope=booking|customer`
 * on the payload. Both scopes use the same color palette.
 */
class BookingTagController extends Controller
{
    private const COLORS = ['rose', 'amber', 'emerald', 'sky', 'violet', 'slate'];

    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['booking', 'customer'])],
            'label' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::in(self::COLORS)],
        ]);

        if ($validated['scope'] === 'booking') {
            $tag = BookingTag::firstOrCreate(
                ['booking_id' => $booking->id, 'label' => $validated['label']],
                [
                    'color'           => $validated['color'],
                    'created_by_type' => ActingClinicUser::actorType(),
                    'created_by_id'   => ActingClinicUser::actorId(),
                ]
            );

            $this->activity->log('booking.tag_added', $booking, [
                'reference' => $booking->reference_code,
                'label'     => $validated['label'],
                'scope'     => 'booking',
            ]);

            return response()->json([
                'data' => [
                    'id'    => $tag->id,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'scope' => 'booking',
                ],
            ], 201);
        }

        $tag = BookingCustomerTag::firstOrCreate(
            [
                'clinic_id'      => $booking->clinic_id,
                'customer_phone' => $booking->customer_phone,
                'label'          => $validated['label'],
            ],
            [
                'color'           => $validated['color'],
                'created_by_type' => ActingClinicUser::actorType(),
                'created_by_id'   => ActingClinicUser::actorId(),
            ]
        );

        $this->activity->log('booking.tag_added', $booking, [
            'reference' => $booking->reference_code,
            'label'     => $validated['label'],
            'scope'     => 'customer',
        ]);

        return response()->json([
            'data' => [
                'id'    => $tag->id,
                'label' => $tag->label,
                'color' => $tag->color,
                'scope' => 'customer',
            ],
        ], 201);
    }

    public function destroy(Request $request, Booking $booking, string $scope, int $tagId): JsonResponse
    {
        $this->authorize('update', $booking);

        if (! in_array($scope, ['booking', 'customer'], true)) {
            return response()->json(['message' => 'invalid_scope'], 422);
        }

        $label = null;
        if ($scope === 'booking') {
            $tag = BookingTag::where('booking_id', $booking->id)->find($tagId);
            if ($tag) { $label = $tag->label; $tag->delete(); }
        } else {
            $tag = BookingCustomerTag::where('clinic_id', $booking->clinic_id)
                ->where('customer_phone', $booking->customer_phone)
                ->find($tagId);
            if ($tag) { $label = $tag->label; $tag->delete(); }
        }

        if ($label) {
            $this->activity->log('booking.tag_removed', $booking, [
                'reference' => $booking->reference_code,
                'label'     => $label,
                'scope'     => $scope,
            ]);
        }

        return response()->json(['data' => ['removed' => (bool) $label]]);
    }
}
