<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\PriceQuoteRequest;
use App\Services\CustomerLinker;

class PriceQuoteCustomerLinkObserver
{
    public function __construct(private readonly CustomerLinker $linker) {}

    public function creating(PriceQuoteRequest $quote): void
    {
        if ($quote->customer_id) return;
        $customer = $this->linker->forPriceQuote($quote);
        if ($customer) {
            $quote->customer_id = $customer->id;
        }
    }

    public function created(PriceQuoteRequest $quote): void
    {
        if (! $quote->customer_id) return;
        $customer = Customer::find($quote->customer_id);
        if ($customer) {
            $this->linker->recordInteraction($customer, Customer::TYPE_QUOTE_REQUEST, $quote->created_at);
        }
    }
}
