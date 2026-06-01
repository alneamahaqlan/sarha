<?php

namespace App\Contracts\Messaging;

/**
 * A delivery channel for OTP codes (SMS, WhatsApp, …).
 *
 * Each channel owns its own message template and "enabled" rule so the
 * dispatcher can stay closed for modification: adding a new channel means
 * adding a class, not editing the orchestrator (Open/Closed + SRP).
 */
interface OtpChannel
{
    /** Stable machine key, e.g. "sms" or "whatsapp". */
    public function key(): string;

    /** Whether the admin has enabled this channel and it is usable. */
    public function isEnabled(): bool;

    /** Deliver the code to the given phone. Returns true on success. */
    public function sendCode(string $phone, string $code): bool;
}
