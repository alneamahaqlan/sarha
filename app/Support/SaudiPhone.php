<?php

namespace App\Support;

/**
 * Normalises Saudi phone numbers to international digits (9665XXXXXXXX) with
 * no plus sign or separators — the form both Unifonic (SMS) and Wappi
 * (WhatsApp) expect as a recipient. Centralised so every transport agrees.
 */
class SaudiPhone
{
    public static function toInternational(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '966')) {
            return $digits;
        }
        if (str_starts_with($digits, '05')) {
            return '966' . substr($digits, 1);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '966' . $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '966' . substr($digits, 1);
        }

        return $digits;
    }
}
