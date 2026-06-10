<?php

namespace App\Services;

use App\Models\CustomerNameAlias;
use App\Models\PlatformCustomer;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Live counterpart of PlatformCustomerBackfillSeeder: keeps the
 * platform-wide customer identity (one per phone) in sync as new
 * bookings / complaints / quotes / self sign-ups arrive.
 *
 * Identity & name rules (settled with the product owner):
 *   - The phone is THE identifier; everything dedupes on its E.164 form.
 *   - A name is recorded as an alias tagged with its source. The FIRST
 *     name a given clinic supplies stays its primary; later differing
 *     names become that clinic's "alternatives". Re-supplying the same
 *     name is a no-op.
 *   - A self-registered account (OTP-verified) adopts its name as the
 *     canonical/primary name everywhere and flips is_phone_verified on.
 *   - With no self name yet, the earliest clinic name stands in as
 *     canonical so admins always have something to show.
 */
class PlatformCustomerResolver
{
    /**
     * Record an interaction's name against the phone's identity and
     * return the PlatformCustomer. Safe to call on every write.
     *
     * @param  string  $source  one of CustomerNameAlias::SOURCE_*
     */
    public function record(?string $phone, string $name, string $source, ?int $clinicId, ?int $userId = null): ?PlatformCustomer
    {
        $phone = PhoneNormalizer::normalizeOrSelf($phone);
        if (! $phone) {
            return null;
        }

        $platform = PlatformCustomer::firstOrCreate(
            ['phone' => $phone],
            ['first_seen_at' => now()],
        );

        // Auto-discover a matching platform account when none was passed.
        $user = $userId ? User::find($userId) : User::where('phone', $phone)->first();

        $patch = [];

        if ($user) {
            if (! $platform->user_id)           $patch['user_id'] = $user->id;
            if (! $platform->is_phone_verified) $patch['is_phone_verified'] = true;
            if ($user->name)                    $patch['canonical_name'] = $user->name;
            $this->ensureAlias($platform->id, null, $user->name ?: '—', CustomerNameAlias::SOURCE_SELF);
        }

        // Record the name from this interaction's own source.
        if ($name !== '' && $name !== '—') {
            $aliasClinicId = $source === CustomerNameAlias::SOURCE_SELF ? null : $clinicId;
            $this->ensureAlias($platform->id, $aliasClinicId, $name, $source);
        }

        // Fill canonical from the earliest clinic name only while the
        // customer hasn't self-registered (a verified self name wins).
        $verified = $patch['is_phone_verified'] ?? $platform->is_phone_verified;
        if (! $verified && ! ($patch['canonical_name'] ?? $platform->canonical_name)) {
            $earliest = $platform->aliases()->orderBy('created_at')->orderBy('id')->first();
            if ($earliest) $patch['canonical_name'] = $earliest->name;
        }

        if ($patch) {
            $platform->forceFill($patch)->save();
        }

        return $platform;
    }

    /**
     * Called when a customer self-registers / signs in via OTP. Adopts
     * the account name as canonical and trusts the phone. Returns null
     * for accounts without a usable phone (e.g. clinic/admin users).
     */
    public function attachUser(User $user): ?PlatformCustomer
    {
        $phone = PhoneNormalizer::normalizeOrSelf($user->phone);
        if (! $phone) {
            return null;
        }

        $platform = PlatformCustomer::firstOrCreate(
            ['phone' => $phone],
            ['first_seen_at' => now()],
        );

        $platform->forceFill([
            'user_id'           => $user->id,
            'is_phone_verified' => true,
            'canonical_name'    => $user->name ?: $platform->canonical_name,
        ])->save();

        if ($user->name) {
            $this->ensureAlias($platform->id, null, $user->name, CustomerNameAlias::SOURCE_SELF);
        }

        return $platform;
    }

    /**
     * Add a name alias if that exact (entity, clinic, name) triple isn't
     * already on file. Never throws — alias upkeep must not break the
     * underlying write.
     */
    private function ensureAlias(int $platformId, ?int $clinicId, string $name, string $source): void
    {
        try {
            CustomerNameAlias::firstOrCreate(
                ['platform_customer_id' => $platformId, 'clinic_id' => $clinicId, 'name' => $name],
                ['source' => $source],
            );
        } catch (\Throwable $e) {
            Log::warning('PlatformCustomerResolver::ensureAlias failed', [
                'platform' => $platformId, 'err' => $e->getMessage(),
            ]);
        }
    }
}
