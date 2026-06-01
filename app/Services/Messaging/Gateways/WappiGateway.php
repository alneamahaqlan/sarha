<?php

namespace App\Services\Messaging\Gateways;

use App\Contracts\Messaging\WhatsAppGateway;
use App\Models\WhatsAppSender;
use App\Support\SaudiPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages through Wappi (https://wappi.pro).
 *
 * Each {@see WhatsAppSender} carries its own `profile_id` + `token`. When the
 * app runs locally, or the chosen sender has no credentials yet, the message
 * is logged instead of sent so dev/testing never blocks — same graceful
 * fallback as {@see \App\Services\SmsService}.
 */
class WappiGateway implements WhatsAppGateway
{
    public function send(WhatsAppSender $sender, string $phone, string $message): bool
    {
        $recipient = SaudiPhone::toInternational($phone);

        if (app()->isLocal() || ! $sender->hasCredentials()) {
            Log::info("[WhatsApp dev via {$sender->phone}] to {$recipient}: {$message}");

            return true;
        }

        $baseUrl  = rtrim(config('services.wappi.base_url', 'https://wappi.pro'), '/');
        $endpoint = $baseUrl . '/api/sync/message/send';

        try {
            $res = Http::withHeaders([
                'Authorization' => $sender->token,
            ])->asJson()->post($endpoint . '?profile_id=' . urlencode($sender->profile_id), [
                'recipient' => $recipient,
                'body'      => $message,
            ]);

            $ok = $res->successful() && $res->json('status') === 'done';

            if (! $ok) {
                Log::warning('Wappi WhatsApp send failed', [
                    'sender' => $sender->phone,
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::error('Wappi WhatsApp error: ' . $e->getMessage(), ['sender' => $sender->phone]);

            return false;
        }
    }
}
