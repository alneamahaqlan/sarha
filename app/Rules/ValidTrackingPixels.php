<?php

namespace App\Rules;

use App\Enums\PixelProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the `tracking_pixels` payload submitted by a clinic (or admin):
 * an array of { provider, id, enabled }. Each provider must be a known
 * PixelProvider and each ID must match that provider's strict format.
 *
 * This is the gatekeeper that stops a malformed/hostile ID from ever
 * reaching the page — a value that doesn't match the anchored pattern is
 * rejected here and never rendered. Defence-in-depth: the renderer also
 * re-validates + json_encodes before injection.
 */
class ValidTrackingPixels implements ValidationRule
{
    /** Hard cap — performance + abuse guard. */
    public const MAX_PIXELS = 8;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // optional — empty is fine
        }

        if (! is_array($value)) {
            $fail('قائمة البكسلات غير صالحة.');
            return;
        }

        if (count($value) > self::MAX_PIXELS) {
            $fail('الحد الأقصى ' . self::MAX_PIXELS . ' بكسلات.');
            return;
        }

        $seen = [];

        foreach ($value as $i => $row) {
            if (! is_array($row) || ! isset($row['provider'], $row['id'])) {
                $fail("العنصر رقم " . ($i + 1) . " ناقص.");
                return;
            }

            $provider = PixelProvider::tryFrom((string) $row['provider']);
            if ($provider === null) {
                $fail("مزوّد غير معروف: " . $row['provider']);
                return;
            }

            $id = trim((string) $row['id']);
            if (! $provider->isValidId($id)) {
                $fail("معرّف {$provider->label()} غير صالح (مثال: {$provider->example()}).");
                return;
            }

            // No duplicate provider+id pairs.
            $key = $provider->value . ':' . $id;
            if (isset($seen[$key])) {
                $fail("بكسل مكرّر: {$provider->label()}.");
                return;
            }
            $seen[$key] = true;
        }
    }
}
