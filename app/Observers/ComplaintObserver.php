<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Services\NotificationService;

class ComplaintObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(Complaint $complaint): void
    {
        $this->notifications->newComplaint($complaint);
    }
}
