<?php

return [
    // Redemption-gate failures (RewardService::ensureRedeemable). Keyed by
    // the exception reason so the API returns a clear, localized message.
    'errors' => [
        'reward_wrong_clinic'      => 'هذه المكافأة لا تخصّ هذا المجمع.',
        'reward_not_active'        => 'المكافأة غير فعّالة (مستخدمة أو ملغاة).',
        'reward_expired'           => 'انتهت صلاحية المكافأة.',
        'reward_service_mismatch'  => 'المكافأة لا تطابق خدمة هذا الحجز.',
        'reward_offer_mismatch'    => 'المكافأة لا تطابق عرض هذا الحجز.',
        'reward_not_transferable'  => 'لا يمكن تحويل هذه المكافأة (غير فعّالة).',
        'reward_invalid_phone'     => 'رقم الجوال غير صالح.',
        'reward_transfer_to_self'  => 'لا يمكن تحويل المكافأة لنفس صاحبها.',
        'reward_not_found'         => 'لم نجد مكافأة بهذا الكود لدى هذا المجمع.',
        'reward_not_owned'         => 'هذه المكافأة ليست ملكك — لا يمكن تطبيقها على حجزك.',
    ],
];
