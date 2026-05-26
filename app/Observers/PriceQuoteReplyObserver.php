<?php

namespace App\Observers;

use App\Models\PriceQuoteReply;
use App\Services\NotificationService;

class PriceQuoteReplyObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(PriceQuoteReply $reply): void
    {
        $this->notifications->quoteReplied($reply);
    }
}
