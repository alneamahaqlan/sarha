<?php

namespace App\Services\Otp;

/**
 * Outcome of an OTP delivery attempt — which channel actually sent it (if
 * any). Lets the controller log/flash the channel without re-deriving it.
 */
class OtpDispatchResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $channel = null,
    ) {
    }

    public static function failed(): self
    {
        return new self(false, null);
    }

    public static function via(string $channel): self
    {
        return new self(true, $channel);
    }
}
