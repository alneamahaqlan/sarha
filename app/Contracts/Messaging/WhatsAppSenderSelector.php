<?php

namespace App\Contracts\Messaging;

use App\Models\WhatsAppSender;

/**
 * Picks which registered WhatsApp number should send the next message.
 * Different strategies (round-robin, least-recently-used, weighted) can be
 * bound without touching the channel.
 */
interface WhatsAppSenderSelector
{
    /** Returns the chosen sender, or null when none is available. */
    public function select(): ?WhatsAppSender;
}
