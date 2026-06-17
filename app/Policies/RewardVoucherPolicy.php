<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\RewardVoucher;

/**
 * Clinic isolation for reward vouchers: a clinic may only see/act on
 * vouchers it issued. Admins (active) have full read access. Customer
 * (User) access to their own vouchers is handled on the account side by
 * scoping to their platform identity, not through this policy.
 */
class RewardVoucherPolicy
{
    public function viewAny(Admin|Clinic $actor): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return true;
    }

    public function view(Admin|Clinic $actor, RewardVoucher $voucher): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $voucher->clinic_id;
    }

    /** Manual grant — any clinic with the feature may gift its customers. */
    public function create(Admin|Clinic $actor): bool
    {
        return $actor instanceof Admin ? $actor->is_active : true;
    }

    public function update(Admin|Clinic $actor, RewardVoucher $voucher): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $voucher->clinic_id;
    }
}
