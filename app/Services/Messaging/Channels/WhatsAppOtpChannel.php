<?php

namespace App\Services\Messaging\Channels;

use App\Contracts\Messaging\OtpChannel;
use App\Contracts\Messaging\WhatsAppGateway;
use App\Contracts\Messaging\WhatsAppSenderSelector;
use App\Models\SystemSetting;
use App\Models\WhatsAppSender;
use Illuminate\Support\Facades\Log;

/**
 * Delivers the OTP over WhatsApp. Picks a sender number via the selector,
 * sends through the gateway, then records the outcome so the rotation +
 * circuit breaker stay accurate.
 */
class WhatsAppOtpChannel implements OtpChannel
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly WhatsAppSenderSelector $selector,
    ) {
    }

    public function key(): string
    {
        return 'whatsapp';
    }

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('otp_whatsapp_enabled', false)
            && WhatsAppSender::query()->active()->withCredentials()->exists();
    }

    public function sendCode(string $phone, string $code): bool
    {
        $sender = $this->selector->select();

        if (! $sender) {
            Log::warning('No active WhatsApp sender available for OTP delivery.');

            return false;
        }

        $ok = $this->gateway->send($sender, $phone, __('site.otp_whatsapp', ['code' => $code]));

        $ok ? $sender->recordSuccess() : $sender->recordFailure();

        return $ok;
    }
}
