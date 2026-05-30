<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Support\PhoneNormalizer;
use App\Models\BookingTag;
use App\Models\Clinic;
use App\Models\ClinicActivityLog;
use App\Models\ClinicTeamMember;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Adds the Kanban-CRM demo signals on top of whatever YearOfUsageSeeder
 * (or DemoSeeder) already created:
 *   - assignees: distributes existing bookings across team members so
 *     the "بمتابعة" badge isn't empty everywhere.
 *   - VIP customers: a couple of repeat customers per clinic with
 *     5+ completed bookings to light up the gold badge.
 *   - cancel-risk customers: 2+ cancellations from the same phone.
 *   - tags: a handful of customer-level + booking-level tags to
 *     populate the Tags Manager + card chips.
 *   - activity log entries (call_attempted, whatsapped, etc.) so the
 *     Timeline + suggestion logic ("retry_call", "reminder_soon")
 *     have data to surface.
 *
 * Lightweight & idempotent — skips rows that already exist.
 */
class BookingsCrmSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::query()
            ->where('status', 'active')
            ->take(6)
            ->get();

        foreach ($clinics as $clinic) {
            $this->assignBookings($clinic);
            $this->seedVipCustomers($clinic);
            $this->seedCancelRiskCustomers($clinic);
            $this->seedTags($clinic);
            $this->seedActivities($clinic);
        }
    }

    /**
     * Distribute the clinic's existing bookings across its team members
     * + the owner so the Kanban "بمتابعة" badge isn't empty.
     */
    private function assignBookings(Clinic $clinic): void
    {
        $members = ClinicTeamMember::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->get();
        if ($members->isEmpty()) return;

        $bookings = Booking::query()
            ->where('clinic_id', $clinic->id)
            ->whereNull('assignee_id')
            ->limit(30)
            ->get();

        $pool = $members->all();
        $ownerSlot = ['type' => Clinic::class, 'id' => $clinic->id];

        foreach ($bookings as $i => $booking) {
            // 1 in 4 stays with the owner, the rest go to members.
            $assignee = ($i % 4 === 0)
                ? $ownerSlot
                : ['type' => ClinicTeamMember::class, 'id' => $pool[$i % count($pool)]->id];

            $booking->update([
                'assignee_type' => $assignee['type'],
                'assignee_id'   => $assignee['id'],
            ]);
        }
    }

    /**
     * Make 2 customers (per clinic) into VIPs by creating 5+ completed
     * bookings under the same phone.
     */
    private function seedVipCustomers(Clinic $clinic): void
    {
        $service = Service::where('clinic_id', $clinic->id)->first();
        if (! $service) return;

        $vips = [
            ['name' => 'سلطان العنزي',  'phone' => '0500001011'],
            ['name' => 'دانة الخالدي', 'phone' => '0500001012'],
        ];

        foreach ($vips as $vip) {
            $existing = Booking::where('clinic_id', $clinic->id)
                ->where('customer_phone', $vip['phone'])
                ->count();
            if ($existing >= 5) continue;

            $needed = 5 - $existing;
            for ($i = 0; $i < $needed; $i++) {
                Booking::create([
                    'clinic_id'      => $clinic->id,
                    'service_id'     => $service->id,
                    'customer_name'  => $vip['name'],
                    'customer_phone' => $vip['phone'],
                    'status'         => 'completed',
                    'appointment_at' => now()->subDays(random_int(20, 200)),
                    'source'         => 'demo',
                ]);
            }
        }
    }

    /**
     * Customers with 2+ cancellations/no_shows — to surface the
     * "cancel_risk" auto-tag + suggestion on their next booking.
     */
    private function seedCancelRiskCustomers(Clinic $clinic): void
    {
        $service = Service::where('clinic_id', $clinic->id)->first();
        if (! $service) return;

        $risky = [
            ['name' => 'يوسف القحطاني', 'phone' => '0500001021'],
        ];

        foreach ($risky as $person) {
            $cancels = Booking::where('clinic_id', $clinic->id)
                ->where('customer_phone', $person['phone'])
                ->whereIn('status', ['cancelled', 'no_show'])
                ->count();
            if ($cancels >= 2) continue;

            for ($i = 0; $i < 3; $i++) {
                Booking::create([
                    'clinic_id'      => $clinic->id,
                    'service_id'     => $service->id,
                    'customer_name'  => $person['name'],
                    'customer_phone' => $person['phone'],
                    'status'         => $i === 0 ? 'no_show' : 'cancelled',
                    'appointment_at' => now()->subDays(random_int(5, 60)),
                    'source'         => 'demo',
                ]);
            }

            // One pending booking so the suggestion fires "now".
            Booking::create([
                'clinic_id'      => $clinic->id,
                'service_id'     => $service->id,
                'customer_name'  => $person['name'],
                'customer_phone' => $person['phone'],
                'status'         => 'new',
                'appointment_at' => now()->addHours(20),
                'source'         => 'demo',
            ]);
        }
    }

    private function seedTags(Clinic $clinic): void
    {
        // Customer-level tags on the VIP phones we just seeded.
        $customerTags = [
            ['phone' => '0500001011', 'label' => 'يَفضّل العربية', 'color' => 'sky'],
            ['phone' => '0500001011', 'label' => 'تابع مع د. أحمد', 'color' => 'emerald'],
            ['phone' => '0500001012', 'label' => 'حساسية من بنج',  'color' => 'rose'],
        ];

        foreach ($customerTags as $tag) {
            // Phase 2: tags belong to Customer (FK), not phone strings.
            // The booking observer from phase 1 has already created
            // (or upserted) Customer rows for these phones via
            // seedVipCustomers() above.
            $normalized = PhoneNormalizer::normalizeOrSelf($tag['phone']);
            $customer = Customer::where('clinic_id', $clinic->id)
                ->where('phone', $normalized)
                ->first();
            if (! $customer) continue;
            CustomerTag::firstOrCreate(
                ['customer_id' => $customer->id, 'label' => $tag['label']],
                ['color' => $tag['color']]
            );
        }

        // Booking-level tags on a couple of fresh bookings.
        $sampleBookings = Booking::where('clinic_id', $clinic->id)
            ->whereIn('status', ['new', 'appointment_set'])
            ->limit(2)
            ->get();
        foreach ($sampleBookings as $b) {
            BookingTag::firstOrCreate(
                ['booking_id' => $b->id, 'label' => 'يَحتاج تَخدير'],
                ['color' => 'amber']
            );
        }
    }

    /**
     * Sprinkle a few "call_attempted / whatsapped / reminder_sent"
     * activity rows across pending bookings so suggestions and the
     * Timeline have content. Owner is the actor (works without a
     * session because we synthesize the snapshot fields manually).
     */
    private function seedActivities(Clinic $clinic): void
    {
        $rows = Booking::where('clinic_id', $clinic->id)
            ->whereIn('status', ['new', 'contacted', 'appointment_set'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $members = ClinicTeamMember::where('clinic_id', $clinic->id)->where('is_active', true)->get();

        foreach ($rows as $idx => $booking) {
            $actor = $members->isNotEmpty() && $idx % 2 === 0
                ? ['type' => ClinicTeamMember::class, 'id' => $members[0]->id, 'name' => $members[0]->name, 'role' => $members[0]->role]
                : ['type' => Clinic::class, 'id' => $clinic->id, 'name' => $clinic->name, 'role' => 'owner'];

            // First action: a call (some succeeded, some not).
            $callOutcome = $idx % 3 === 0 ? 'no_answer' : 'answered';
            ClinicActivityLog::create([
                'clinic_id'  => $clinic->id,
                'actor_type' => $actor['type'],
                'actor_id'   => $actor['id'],
                'actor_name' => $actor['name'],
                'actor_role' => $actor['role'],
                'action'     => 'booking.call_attempted',
                'model_type' => Booking::class,
                'model_id'   => $booking->id,
                'summary'    => [
                    'reference' => $booking->reference_code,
                    'customer'  => $booking->customer_name,
                    'outcome'   => $callOutcome,
                ],
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subHours(random_int(3, 36)),
            ]);

            // Sometimes a WhatsApp follow-up.
            if ($idx % 2 === 0) {
                ClinicActivityLog::create([
                    'clinic_id'  => $clinic->id,
                    'actor_type' => $actor['type'],
                    'actor_id'   => $actor['id'],
                    'actor_name' => $actor['name'],
                    'actor_role' => $actor['role'],
                    'action'     => 'booking.whatsapped',
                    'model_type' => Booking::class,
                    'model_id'   => $booking->id,
                    'summary'    => [
                        'reference' => $booking->reference_code,
                        'customer'  => $booking->customer_name,
                    ],
                    'ip_address' => '127.0.0.1',
                    'created_at' => now()->subHours(random_int(1, 6)),
                ]);
            }
        }
    }
}
