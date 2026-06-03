<?php

namespace App\Services;

use App\Support\SaudiPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends transactional SMS through Unifonic. Credentials live in
 * config('services.unifonic'). When credentials are missing or the app runs
 * locally, the message is logged instead of sent so dev/testing never blocks.
 */
class SmsService
{
    private const ENDPOINT = 'https://el.cloud.unifonic.com/rest/SMS/messages';

    public function send(string $phone, string $message): bool
    {
        $recipient = SaudiPhone::toInternational($phone);
        $appSid = config('services.unifonic.app_sid');

        // No credentials or local env → log instead of sending.
        if (! $appSid || app()->isLocal()) {
            Log::info("[SMS dev] to {$recipient}: {$message}");

            return true;
        }

        try {
            $res = Http::asForm()->post(self::ENDPOINT, [
                'AppSid'       => $appSid,
                'SenderID'     => config('services.unifonic.sender_id'),
                'Body'         => $message,
                'Recipient'    => $recipient,
                'responseType' => 'JSON',
            ]);

            $ok = $res->successful()
                && in_array($res->json('success'), [true, 'true'], true);

            if (! $ok) {
                Log::warning('Unifonic SMS failed', ['status' => $res->status(), 'body' => $res->body()]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::error('Unifonic SMS error: '.$e->getMessage());

            return false;
        }
    }
}
