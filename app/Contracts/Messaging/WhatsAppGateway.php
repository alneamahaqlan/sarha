<?php

namespace App\Contracts\Messaging;

use App\Models\WhatsAppSender;

/**
 * Low-level transport that pushes a WhatsApp message through a specific
 * provider (Wappi today). Kept separate from the channel so the provider can
 * be swapped by binding a different implementation (Dependency Inversion).
 */
interface WhatsAppGateway
{
    /** Send a message from the given sender profile. Returns true on success. */
    public function send(WhatsAppSender $sender, string $phone, string $message): bool;
}
