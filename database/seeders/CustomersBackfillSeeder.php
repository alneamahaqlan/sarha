<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\PriceQuoteRequest;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 backfill: walk every existing booking / complaint /
 * price-quote-request, normalize the phone in place, upsert the
 * matching per-clinic Customer, and stamp customer_id on the row.
 * Then recompute the denormalized counters in one SQL pass per
 * clinic.
 *
 * Idempotent — re-running converges. Skips work for rows already
 * linked.
 *
 * Runs after every other seeder so it sees the full data picture
 * (DemoSeeder, YearOfUsageSeeder, ClinicTeamSeeder, BookingsCrm-
 * Seeder all produce phone-bearing rows the new entity must absorb).
 */
class CustomersBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('  Customers backfill: normalizing phone columns…');
        $this->normalizePhoneColumns();

        $this->command?->info('  Customers backfill: upserting Customer rows + linking…');
        $this->linkBookings();
        $this->linkComplaints();
        $this->linkPriceQuotes();

        $this->command?->info('  Customers backfill: recomputing counters…');
        $this->recomputeCounters();

        $this->command?->info('  Customers backfill: auto-linking user_id…');
        $this->autoLinkUsers();

        $count = Customer::count();
        $this->command?->info("  ✓ Customers backfill complete ({$count} rows)");
    }

    /**
     * Rewrite customer_phone in place across bookings + complaints +
     * price_quote_requests + booking_customer_tags. updateQuietly so
     * we don't trigger our own observers during the rewrite.
     */
    private function normalizePhoneColumns(): void
    {
        foreach ([Booking::class, Complaint::class, PriceQuoteRequest::class] as $model) {
            $model::query()
                ->whereNotNull('customer_phone')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $row->updateQuietly([
                            'customer_phone' => PhoneNormalizer::normalizeOrSelf($row->customer_phone),
                        ]);
                    }
                });
        }
    }

    /**
     * Walk bookings chronologically, upserting Customers and
     * stamping customer_id. Proxy bookings (booker_user_id ≠ null)
     * are attributed to the booker per product decision.
     */
    private function linkBookings(): void
    {
        // lazyById walks every row by primary key ascending, fetching
        // small pages under the hood. Safe to mutate the same column
        // we're filtering on (customer_id) inside the loop — the
        // cursor only advances by PK, never by the filtered column.
        Booking::query()
            ->whereNull('customer_id')
            ->lazyById(500)
            ->each(function (Booking $booking) {
                [$phone, $name, $userId, $email] = $this->resolveBookingIdentity($booking);
                if (! $phone || ! $booking->clinic_id) return;
                $customer = $this->upsert($booking->clinic_id, $phone, $name, $email, $userId, $booking->created_at);
                Booking::where('id', $booking->id)->update(['customer_id' => $customer->id]);
            });
    }

    private function linkComplaints(): void
    {
        // Platform-wide complaints (no clinic_id) aren't per-clinic
        // customers — they belong to the global admin queue. Skip.
        Complaint::query()
            ->whereNull('customer_id')
            ->whereNotNull('clinic_id')
            ->lazyById(500)
            ->each(function (Complaint $c) {
                $phone = PhoneNormalizer::normalizeOrSelf($c->customer_phone);
                if (! $phone) return;
                $customer = $this->upsert(
                    $c->clinic_id, $phone, $c->customer_name ?? '—',
                    $c->customer_email, $c->user_id, $c->created_at,
                );
                Complaint::where('id', $c->id)->update(['customer_id' => $customer->id]);
            });
    }

    private function linkPriceQuotes(): void
    {
        // Broadcast price quotes (no clinic_id) target every clinic in
        // the request's cities — they don't belong to one customer
        // record. They'll get a customer link only when a specific
        // clinic replies (future phase, when replies carry clinic_id).
        PriceQuoteRequest::query()
            ->whereNull('customer_id')
            ->whereNotNull('clinic_id')
            ->lazyById(500)
            ->each(function (PriceQuoteRequest $q) {
                $phone = PhoneNormalizer::normalizeOrSelf($q->customer_phone);
                if (! $phone) return;
                $customer = $this->upsert(
                    $q->clinic_id, $phone, $q->customer_name ?? '—',
                    null, $q->user_id, $q->created_at,
                );
                PriceQuoteRequest::where('id', $q->id)->update(['customer_id' => $customer->id]);
            });
    }

    /**
     * @return array{0:?string,1:string,2:?int,3:?string}
     */
    private function resolveBookingIdentity(Booking $booking): array
    {
        if ($booking->booker_user_id) {
            $booker = User::find($booking->booker_user_id);
            if ($booker) {
                return [
                    PhoneNormalizer::normalizeOrSelf($booker->phone),
                    $booker->name ?? '—',
                    $booker->id,
                    $booker->email,
                ];
            }
        }
        return [
            PhoneNormalizer::normalizeOrSelf($booking->customer_phone),
            $booking->customer_name ?? '—',
            $booking->user_id,
            null,
        ];
    }

    /**
     * Insert a Customer if (clinic, phone) doesn't exist yet, else
     * top up missing name/email/user_id and roll first_seen_at back
     * to the oldest interaction we've seen for this customer.
     */
    private function upsert(int $clinicId, string $phone, string $name, ?string $email, ?int $userId, $createdAt): Customer
    {
        $customer = Customer::firstOrCreate(
            ['clinic_id' => $clinicId, 'phone' => $phone],
            [
                'name'          => $name,
                'email'         => $email,
                'user_id'       => $userId,
                'first_seen_at' => $createdAt,
                'last_seen_at'  => $createdAt,
            ]
        );

        $dirty = [];
        if ($customer->first_seen_at === null || $createdAt < $customer->first_seen_at) {
            $dirty['first_seen_at'] = $createdAt;
        }
        if ($customer->last_seen_at === null || $createdAt > $customer->last_seen_at) {
            $dirty['last_seen_at'] = $createdAt;
        }
        if (! $customer->email && $email)   $dirty['email']   = $email;
        if (! $customer->user_id && $userId) $dirty['user_id'] = $userId;

        if ($dirty) $customer->forceFill($dirty)->save();
        return $customer;
    }

    /**
     * One UPDATE-per-clinic recomputes the four counters + the
     * last_interaction columns from scratch. Faster + simpler than
     * incrementing per-row during the link pass.
     */
    private function recomputeCounters(): void
    {
        $driver = DB::connection()->getDriverName();

        // Plain SQL — works on MySQL/MariaDB. Subqueries instead of
        // joins to keep it driver-agnostic.
        DB::statement(<<<SQL
            UPDATE customers SET
              total_bookings = COALESCE((
                SELECT COUNT(*) FROM bookings
                WHERE bookings.customer_id = customers.id
                  AND bookings.deleted_at IS NULL
              ), 0),
              completed_bookings = COALESCE((
                SELECT COUNT(*) FROM bookings
                WHERE bookings.customer_id = customers.id
                  AND bookings.status = 'completed'
                  AND bookings.deleted_at IS NULL
              ), 0),
              total_complaints = COALESCE((
                SELECT COUNT(*) FROM complaints
                WHERE complaints.customer_id = customers.id
                  AND complaints.deleted_at IS NULL
              ), 0),
              total_quote_requests = COALESCE((
                SELECT COUNT(*) FROM price_quote_requests
                WHERE price_quote_requests.customer_id = customers.id
              ), 0)
        SQL);

        // Latest interaction = max(created_at) across the three tables,
        // with a type label.
        Customer::query()->chunkById(500, function ($rows) {
            foreach ($rows as $customer) {
                $latest = [];
                $b = Booking::where('customer_id', $customer->id)->max('created_at');
                $c = Complaint::where('customer_id', $customer->id)->max('created_at');
                $q = PriceQuoteRequest::where('customer_id', $customer->id)->max('created_at');
                if ($b) $latest[] = [Customer::TYPE_BOOKING,       $b];
                if ($c) $latest[] = [Customer::TYPE_COMPLAINT,     $c];
                if ($q) $latest[] = [Customer::TYPE_QUOTE_REQUEST, $q];
                usort($latest, fn($x, $y) => strcmp($y[1], $x[1]));
                if ($latest) {
                    $customer->forceFill([
                        'last_interaction_type' => $latest[0][0],
                        'last_interaction_at'   => $latest[0][1],
                        'last_seen_at'          => $latest[0][1],
                    ])->save();
                }
            }
        });
    }

    /**
     * Sweep for Customers whose user_id is still null but whose phone
     * matches a platform User. Last-write wins on duplicate phones —
     * acceptable for seeded data.
     */
    private function autoLinkUsers(): void
    {
        $phoneToUser = User::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->mapWithKeys(fn($u) => [PhoneNormalizer::normalizeOrSelf($u->phone) => $u->id])
            ->all();

        Customer::query()
            ->whereNull('user_id')
            ->chunkById(500, function ($rows) use ($phoneToUser) {
                foreach ($rows as $customer) {
                    if (isset($phoneToUser[$customer->phone])) {
                        $customer->forceFill(['user_id' => $phoneToUser[$customer->phone]])->save();
                    }
                }
            });
    }
}
