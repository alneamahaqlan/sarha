<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateRewardRuleRequest;
use App\Http\Resources\Api\V1\ClinicRewardRuleResource;
use App\Models\ClinicRewardRule;
use App\Models\RewardVoucher;
use App\Services\ClinicActivityLogger;

/**
 * The clinic's single cashback auto-grant rule. Behind clinic.feature:
 * rewards; isolation is implicit since every query is keyed to the
 * acting clinic.
 */
class ClinicRewardRuleController extends Controller
{
    public function __construct(private readonly ClinicActivityLogger $activity) {}

    public function show(): ClinicRewardRuleResource
    {
        $clinicId = (int) auth('clinic')->id();
        $rule = ClinicRewardRule::with(['offer:id,title', 'service:id,name'])
            ->firstOrNew(['clinic_id' => $clinicId]);

        return new ClinicRewardRuleResource($rule);
    }

    public function update(UpdateRewardRuleRequest $request): ClinicRewardRuleResource
    {
        $clinicId = (int) auth('clinic')->id();
        $data = $request->validated();
        $type = $data['type'] ?? null;

        // Normalize to the chosen type's shape — clear the other type's
        // fields so a half-switched rule can't mint a mismatched voucher.
        $payload = [
            'enabled'        => $data['enabled'],
            'type'           => $type,
            'validity_days'  => $data['validity_days'] ?? null,
            'offer_id'       => $type === RewardVoucher::TYPE_OFFER_DISCOUNT ? ($data['offer_id'] ?? null) : null,
            'discount_type'  => $type === RewardVoucher::TYPE_OFFER_DISCOUNT ? ($data['discount_type'] ?? null) : null,
            'discount_value' => $type === RewardVoucher::TYPE_OFFER_DISCOUNT ? ($data['discount_value'] ?? null) : null,
            'service_id'     => $type === RewardVoucher::TYPE_FREE_SERVICE ? ($data['service_id'] ?? null) : null,
        ];

        $rule = ClinicRewardRule::updateOrCreate(['clinic_id' => $clinicId], $payload);
        $this->activity->log('reward.rule_updated', $rule, [
            'enabled' => (bool) $rule->enabled,
            'type'    => $rule->type,
        ]);

        return new ClinicRewardRuleResource($rule->load(['offer:id,title', 'service:id,name']));
    }
}
