<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Models\Customer;
use App\Services\CustomerLinker;

class ComplaintCustomerLinkObserver
{
    public function __construct(private readonly CustomerLinker $linker) {}

    public function creating(Complaint $complaint): void
    {
        if ($complaint->customer_id) return;
        $customer = $this->linker->forComplaint($complaint);
        if ($customer) {
            $complaint->customer_id = $customer->id;
        }
    }

    public function created(Complaint $complaint): void
    {
        if (! $complaint->customer_id) return;
        $customer = Customer::find($complaint->customer_id);
        if ($customer) {
            $this->linker->recordInteraction($customer, Customer::TYPE_COMPLAINT, $complaint->created_at);
        }
    }
}
