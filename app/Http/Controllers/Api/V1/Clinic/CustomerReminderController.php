<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreCustomerReminderRequest;
use App\Http\Resources\Api\V1\CustomerReminderResource;
use App\Models\Booking;
use App\Models\ClinicTeamMember;
use App\Models\Customer;
use App\Models\CustomerReminder;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contact reminders the clinic sets for itself ("call this patient at X").
 *
 * Auth model (all gated at the route via clinic.role):
 *   - index:   reminders.view
 *   - store:   reminders.create
 *   - complete/cancel: reminders.manage
 *
 * Audience is the whole clinic — every role with reminders.* sees the
 * same list, so there's no per-author ownership check here.
 */
class CustomerReminderController extends Controller
{
    public function __construct(private readonly ClinicActivityLogger $activity) {}

    /**
     * List reminders. ?status=upcoming|overdue|done|all (default: open =
     * pending of both kinds). Cancelled rows are hidden unless asked for.
     */
    public function index(Request $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $status   = (string) $request->query('status', 'open');

        $query = CustomerReminder::forClinic($clinicId)
            ->with(['customer:id,name,phone', 'assigneeMember:id,name,role']);

        // "Assigned to me" — only meaningful for a team-member session
        // (an owner session has no member id, so it matches nothing).
        if ($request->boolean('mine')) {
            $actor = ActingClinicUser::actor();
            $memberId = $actor instanceof ClinicTeamMember ? $actor->id : 0;
            $query->where('assignee_member_id', $memberId);
        }

        match ($status) {
            'upcoming' => $query->pending()->where('remind_at', '>', now())->orderBy('remind_at'),
            'overdue'  => $query->pending()->where('remind_at', '<=', now())->orderBy('remind_at'),
            'done'     => $query->where('status', CustomerReminder::STATUS_DONE)->latest('completed_at'),
            'all'      => $query->latest('remind_at'),
            default    => $query->pending()->orderBy('remind_at'), // "open"
        };

        $reminders = $query->limit(200)->get();

        return response()->json([
            'data' => CustomerReminderResource::collection($reminders)->resolve(),
        ]);
    }

    public function store(StoreCustomerReminderRequest $request): JsonResponse
    {
        $clinicId  = (int) auth('clinic')->id();
        $validated = $request->validated();

        $customer = Customer::findOrFail($validated['customer_id']);
        abort_unless($customer->clinic_id === $clinicId, 404);

        // A booking, if linked, must belong to the same clinic + customer.
        if (! empty($validated['booking_id'])) {
            $booking = Booking::findOrFail($validated['booking_id']);
            abort_unless(
                $booking->clinic_id === $clinicId && $booking->customer_id === $customer->id,
                422,
            );
        }

        // An assignee, if given, must be a team member of this clinic.
        if (! empty($validated['assignee_member_id'])) {
            $member = ClinicTeamMember::findOrFail($validated['assignee_member_id']);
            abort_unless($member->clinic_id === $clinicId, 422);
        }

        $reminder = CustomerReminder::create([
            'clinic_id'          => $clinicId,
            'customer_id'        => $customer->id,
            'booking_id'         => $validated['booking_id'] ?? null,
            'assignee_member_id' => $validated['assignee_member_id'] ?? null,
            'remind_at'          => $validated['remind_at'],
            'note'            => isset($validated['note']) ? trim($validated['note']) : null,
            'status'          => CustomerReminder::STATUS_PENDING,
            'created_by_type' => ActingClinicUser::actorType(),
            'created_by_id'   => ActingClinicUser::actorId(),
            'created_by_name' => ActingClinicUser::actorName() ?? '—',
        ]);

        $this->activity->log('reminder.created', $reminder, [
            'customer'  => $customer->name,
            'remind_at' => $reminder->remind_at?->toIso8601String(),
        ]);

        $reminder->load(['customer:id,name,phone', 'assigneeMember:id,name,role']);

        return response()->json([
            'data' => (new CustomerReminderResource($reminder))->resolve(),
        ], 201);
    }

    public function complete(CustomerReminder $reminder): JsonResponse
    {
        $this->ensureClinicOwns($reminder);

        $reminder->update([
            'status'       => CustomerReminder::STATUS_DONE,
            'completed_at' => now(),
        ]);

        $this->activity->log('reminder.completed', $reminder);

        $reminder->load(['customer:id,name,phone', 'assigneeMember:id,name,role']);

        return response()->json([
            'data' => (new CustomerReminderResource($reminder))->resolve(),
        ]);
    }

    public function cancel(CustomerReminder $reminder): JsonResponse
    {
        $this->ensureClinicOwns($reminder);

        $reminder->update(['status' => CustomerReminder::STATUS_CANCELLED]);

        $this->activity->log('reminder.cancelled', $reminder);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    // ---------- internal ----------

    private function ensureClinicOwns(CustomerReminder $reminder): void
    {
        abort_unless($reminder->clinic_id === (int) auth('clinic')->id(), 404);
    }
}
