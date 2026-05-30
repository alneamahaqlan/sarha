<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonicalizes any Saudi phone the platform sees into E.164 form
 * (+9665XXXXXXXX). The Customer entity uses this on every write so
 * dedup by phone actually works regardless of how the customer typed
 * it ("0500…", "+966500…", "966500…", "500…" etc).
 *
 * Called from:
 *   - CustomerLinker (the create/update entry point for Customer rows)
 *   - Booking/Complaint/PriceQuote observers (before resolving Customer)
 *   - CustomersBackfillSeeder (rewrites existing customer_phone columns)
 *   - Future form requests at any new phone-accepting boundary
 *
 * Rules:
 *   - Strip spaces, dashes, parentheses
 *   - "+966…", "00966…", "966…", "0…", or bare "5…" all converge on
 *     "+966" + 9 digits starting with "5"
 *   - Throws when input is empty or unparseable. Callers near the
 *     boundary should validate; callers from the backfill catch and
 *     fall back to raw input.
 */
final class PhoneNormalizer
{
    public const COUNTRY_CODE = '966';
    public const PREFIX       = '+966';
    private const MOBILE_LEN  = 9; // 5XXXXXXXX

    public static function normalize(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            throw new InvalidArgumentException('phone_empty');
        }

        // Strip everything that isn't a digit or a leading '+'
        $clean = preg_replace('/[^\d+]/', '', $input) ?? '';
        // Collapse "00" international prefix into "+"
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }
        // Drop the leading '+' for digit-only analysis; we re-add later.
        $digits = ltrim($clean, '+');

        if ($digits === '') {
            throw new InvalidArgumentException('phone_invalid');
        }

        // Case A: starts with country code "966…"
        if (str_starts_with($digits, self::COUNTRY_CODE)) {
            $rest = substr($digits, strlen(self::COUNTRY_CODE));
            // Allow a stray leading zero between 966 and the mobile body
            // (some users type +96605… by mistake).
            $rest = ltrim($rest, '0');
            return self::PREFIX . $rest;
        }

        // Case B: starts with local trunk prefix "05…"
        if (str_starts_with($digits, '05')) {
            return self::PREFIX . substr($digits, 1);
        }

        // Case C: bare mobile "5XXXXXXXX"
        if (str_starts_with($digits, '5') && strlen($digits) === self::MOBILE_LEN) {
            return self::PREFIX . $digits;
        }

        // Defensive: return prefixed-as-given so the caller can still
        // dedupe consistently across copies of the same garbage input.
        return self::PREFIX . $digits;
    }

    /**
     * Lenient variant used by the backfill — returns the original
     * input verbatim instead of throwing, so a junk row doesn't kill
     * a 10k-row batch.
     */
    public static function normalizeOrSelf(?string $input): ?string
    {
        if ($input === null) return null;
        try {
            return self::normalize($input);
        } catch (\Throwable) {
            return $input;
        }
    }

    /** "+9665XXXXXXXX" → "0XXXXXXXXX" for legacy display surfaces. */
    public static function toLocal(string $normalized): string
    {
        if (str_starts_with($normalized, self::PREFIX)) {
            return '0' . substr($normalized, strlen(self::PREFIX));
        }
        return $normalized;
    }
}
