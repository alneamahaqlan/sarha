<?php

namespace App\Observers;

use App\Models\PriceQuoteRequest;
use App\Services\NotificationService;

class PriceQuoteRequestObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(PriceQuoteRequest $quote): void
    {
        $this->notifications->newPriceQuoteRequest($quote);
    }
}
