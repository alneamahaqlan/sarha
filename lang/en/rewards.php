<?php

return [
    'errors' => [
        'reward_wrong_clinic'      => 'This reward does not belong to this clinic.',
        'reward_not_active'        => 'The reward is not active (already used or voided).',
        'reward_expired'           => 'The reward has expired.',
        'reward_service_mismatch'  => 'The reward does not match this booking’s service.',
        'reward_offer_mismatch'    => 'The reward does not match this booking’s offer.',
        'reward_not_transferable'  => 'This reward cannot be transferred (not active).',
        'reward_invalid_phone'     => 'Invalid phone number.',
        'reward_transfer_to_self'  => 'You cannot transfer a reward to its current owner.',
        'reward_not_found'         => 'No reward with this code was found for this clinic.',
        'reward_not_owned'         => 'This reward is not yours — it cannot be applied to your booking.',
    ],
];
