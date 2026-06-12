<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerNameAlias;
use App\Models\PlatformCustomer;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;

/**
 * Backfill the platform-wide customer identity from the existing
 * per-clinic Customer rows.
 *
 * Pass 1 — walk every Customer (by id, so lazyById keyset pagination
 *   stays valid): create one PlatformCustomer per distinct normalized
 *   phone, point the Customer at it, and record the clinic-supplied
 *   name as a 'clinic' alias whose created_at is rolled back to the
 *   customer's first_seen_at ("earliest name wins" stays accurate).
 *
 * Pass 2 — resolve each identity's canonical name + verification:
 *   a self-registered User match → adopt User.name, mark verified, add
 *   a 'self' alias; otherwise the earliest clinic alias becomes
 *   canonical.
 *
 * Idempotent (firstOrCreate everywhere). Must run AFTER
 * CustomersBackfillSeeder.
 */
class PlatformCustomerBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('  Platform-customer backfill: pass 1 — linking by phone…');
        $this->linkCustomers();

        $this->command?->info('  Platform-customer backfill: pass 2 — canonical names + verification…');
        $this->resolveIdentities();

        $count = PlatformCustomer::count();
        $this->command?->info("  ✓ Platform-customer backfill complete ({$count} identities)");
    }

    /** Pass 1: one PlatformCustomer per phone; link rows + clinic aliases. */
    private function linkCustomers(): void
    {
        Customer::query()
            ->whereNotNull('phone')
            ->lazyById(500) // id-ordered — do NOT add another orderBy (breaks keyset paging)
            ->each(function (Customer $customer) {
                $phone = PhoneNormalizer::normalizeOrSelf($customer->phone);
                if (! $phone) return;

                $platform = PlatformCustomer::firstOrCreate(
                    ['phone' => $phone],
                    ['first_seen_at' => $customer->first_seen_at],
                );

                if ($customer->platform_customer_id !== $platform->id) {
                    $customer->forceFill(['platform_customer_id' => $platform->id])->save();
                }

                if ($customer->name && $customer->name !== '—') {
                    $this->ensureAlias(
                        $platform->id, $customer->clinic_id, $customer->name,
                        CustomerNameAlias::SOURCE_CLINIC, $customer->first_seen_at,
                    );
                }

                // Roll first_seen_at back to the earliest interaction.
                if ($customer->first_seen_at &&
                    ($platform->first_seen_at === null || $customer->first_seen_at < $platform->first_seen_at)) {
                    $platform->forceFill(['first_seen_at' => $customer->first_seen_at])->save();
                }
            });
    }

    /** Pass 2: adopt self name + verify, else earliest clinic name. */
    private function resolveIdentities(): void
    {
        // Map normalized phone → platform User, once.
        $phoneToUser = User::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'name'])
            ->mapWithKeys(fn (User $u) => [
                PhoneNormalizer::normalizeOrSelf($u->phone) => $u,
            ]);

        PlatformCustomer::query()
            ->with(['aliases'])
            ->lazyById(500)
            ->each(function (PlatformCustomer $platform) use ($phoneToUser) {
                $user  = $phoneToUser->get($platform->phone);
                $patch = [];

                if ($user) {
                    $patch['user_id']           = $user->id;
                    $patch['is_phone_verified'] = true;
                    if ($user->name) $patch['canonical_name'] = $user->name;

                    $this->ensureAlias(
                        $platform->id, null, $user->name ?? '—',
                        CustomerNameAlias::SOURCE_SELF, $platform->first_seen_at,
                    );
                } else {
                    // Earliest clinic-supplied name stands in as canonical.
                    $earliest = $platform->aliases
                        ->sortBy('created_at')
                        ->first(fn (CustomerNameAlias $a) => $a->name !== '—');
                    if ($earliest) $patch['canonical_name'] = $earliest->name;
                }

                if ($patch) $platform->forceFill($patch)->save();
            });
    }

    /**
     * firstOrCreate an alias, then force its created_at to the historical
     * first_seen_at so ordering reflects when the name was really given.
     */
    private function ensureAlias(int $platformId, ?int $clinicId, string $name, string $source, $when): void
    {
        $alias = CustomerNameAlias::firstOrCreate(
            ['platform_customer_id' => $platformId, 'clinic_id' => $clinicId, 'name' => $name],
            ['source' => $source],
        );

        if ($when && $alias->wasRecentlyCreated) {
            $alias->forceFill(['created_at' => $when])->save();
        }
    }
}
