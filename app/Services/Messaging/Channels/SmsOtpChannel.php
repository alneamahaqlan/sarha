<?php

namespace App\Services\Messaging\Channels;

use App\Contracts\Messaging\OtpChannel;
use App\Models\SystemSetting;
use App\Services\SmsService;

/**
 * Delivers the OTP over SMS (Unifonic) by wrapping the existing SmsService.
 */
class SmsOtpChannel implements OtpChannel
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    public function key(): string
    {
        return 'sms';
    }

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('otp_sms_enabled', true);
    }

    public function sendCode(string $phone, string $code): bool
    {
        return $this->sms->send($phone, __('site.otp_sms', ['code' => $code]));
    }
}
