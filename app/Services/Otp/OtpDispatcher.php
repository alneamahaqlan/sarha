<?php

namespace App\Services\Otp;

use App\Contracts\Messaging\OtpChannel;
use App\Models\SystemSetting;
use App\Services\Messaging\Channels\SmsOtpChannel;
use App\Services\Messaging\Channels\WhatsAppOtpChannel;

/**
 * Decides how an OTP code reaches the user. The active channel is driven
 * entirely by the admin panel: which channels are enabled, which one is
 * primary, and whether to fall back to the other if the primary fails.
 *
 * Adding a future channel (e.g. Telegram) means registering it in
 * {@see self::channels()} — the dispatch loop itself never changes.
 */
class OtpDispatcher
{
    public function __construct(
        private readonly SmsOtpChannel $sms,
        private readonly WhatsAppOtpChannel $whatsapp,
    ) {
    }

    public function send(string $phone, string $code): OtpDispatchResult
    {
        foreach ($this->resolveOrder() as $channel) {
            if ($channel->sendCode($phone, $code)) {
                return OtpDispatchResult::via($channel->key());
            }
        }

        return OtpDispatchResult::failed();
    }

    /**
     * Build the ordered list of channels to try: the admin-selected primary
     * first, then the other one only when fallback is enabled — and only
     * channels the admin has actually turned on.
     *
     * @return OtpChannel[]
     */
    private function resolveOrder(): array
    {
        $channels = $this->channels();
        $primary  = SystemSetting::get('otp_primary_channel', 'whatsapp');
        $fallback = (bool) SystemSetting::get('otp_fallback_enabled', true);

        // Primary channel first, then the remaining channels in registration order.
        $ordered = [];
        if (isset($channels[$primary])) {
            $ordered[] = $channels[$primary];
        }
        foreach ($channels as $key => $channel) {
            if ($key !== $primary) {
                $ordered[] = $channel;
            }
        }

        $enabled = array_values(array_filter($ordered, fn (OtpChannel $c) => $c->isEnabled()));

        return $fallback ? $enabled : array_slice($enabled, 0, 1);
    }

    /** @return array<string, OtpChannel> keyed by channel key */
    private function channels(): array
    {
        return [
            $this->whatsapp->key() => $this->whatsapp,
            $this->sms->key()      => $this->sms,
        ];
    }
}
