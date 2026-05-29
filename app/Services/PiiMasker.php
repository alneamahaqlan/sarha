<?php

namespace App\Services;

/**
 * Default-on masking for the AI Center conversation views. Catches the
 * common PII shapes that show up in chat queries — Saudi mobile numbers,
 * emails, and admin-supplied names. The super-admin can flip a "reveal"
 * toggle to see raw text when investigating a specific incident.
 *
 * Not exhaustive — this is an admin-only surface, so it's about reducing
 * casual exposure (one-finger reveal stays available), not enforcing
 * data-redaction guarantees.
 */
class PiiMasker
{
    public function mask(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // Saudi mobile: 05XXXXXXXX → 05•••••XXX (keep last 3 for ref).
        $text = preg_replace_callback('/\b05\d{8}\b/u', function ($m) {
            $n = $m[0];
            return substr($n, 0, 2) . str_repeat('•', 5) . substr($n, -3);
        }, $text);

        // Emails: name@domain.tld → na***@d***.tld
        $text = preg_replace_callback('/\b([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+)\.([A-Za-z]{2,})\b/u', function ($m) {
            $local  = mb_substr($m[1], 0, 2) . '***';
            $domain = mb_substr($m[2], 0, 1) . '***';
            return $local . '@' . $domain . '.' . $m[3];
        }, $text);

        return $text;
    }

    /** Mask a display name to first letter + asterisks. */
    public function maskName(?string $name): ?string
    {
        if (! $name) return $name;
        $name = trim($name);
        if (mb_strlen($name) <= 1) return $name;
        return mb_substr($name, 0, 1) . str_repeat('*', max(2, mb_strlen($name) - 1));
    }

    /** Mask a phone to 05•••••XXX (last 3 visible). */
    public function maskPhone(?string $phone): ?string
    {
        if (! $phone) return $phone;
        return preg_replace('/\b(\d{2})\d{5}(\d{3})\b/', '$1•••••$2', $phone);
    }
}
