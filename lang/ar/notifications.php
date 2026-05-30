<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Notification event catalog — Arabic
     |--------------------------------------------------------------------------
     | Each event has `title` and `body`. Placeholders:
     |   :clinic        - clinic name
     |   :customer      - customer name (only safe to show for in-app + sound;
     |                    Web Push lock-screen previews must NOT include it)
     |   :reference_code     - booking reference code
     |   :service       - service name
     |   :conversation  - AI conversation id
     */
    'events' => [
        // ── Clinic-side ───────────────────────────────────────────────
        'booking_created' => [
            'title' => 'حجز جديد وَصلك',
            'body'  => 'العميل :customer حجز موعداً برقم :reference_code.',
        ],
        'booking_cancelled_by_user' => [
            'title' => 'العميل ألغى حجزه',
            'body'  => 'الحجز :reference_code تم إلغاؤه من قِبَل العميل :customer.',
        ],
        'complaint_created' => [
            'title' => 'شكوى جديدة',
            'body'  => 'العميل :customer قدّم شكوى تَتطلّب رداً.',
        ],
        'quote_created' => [
            'title' => 'طلب سعر جديد',
            'body'  => 'العميل :customer يَطلب عرض سعر لـ :service.',
        ],

        // ── Subscription lifecycle ────────────────────────────────────
        'subscription_expiring_soon' => [
            'title' => 'اشتراكك يَنتهي قريباً',
            'body'  => 'باقتك ":package" تَنتهي خلال :days يوم — تَواصل مع الإدارة للتجديد.',
        ],
        'subscription_expired' => [
            'title' => 'انتهى اشتراكك',
            'body'  => 'باقتك ":package" انتهت. لديك :grace أيام مهلة سماح قبل تعليق المزايا — تَواصل لتجديد الاشتراك.',
        ],
        'subscription_activated' => [
            'title' => 'تم تَفعيل اشتراكك',
            'body'  => 'تم تَفعيل باقة ":package" حتى :ends_at. شكراً لاختيارك المنصة.',
        ],
        'subscription_cancelled' => [
            'title' => 'تم إلغاء اشتراكك',
            'body'  => 'تم إلغاء اشتراكك في باقة ":package". لِأي استفسار تواصل مع الإدارة.',
        ],

        // ── User-side ─────────────────────────────────────────────────
        'booking_confirmed' => [
            'title' => 'تم تأكيد حجزك',
            'body'  => 'المجمع :clinic أكّد حجزك رقم :reference_code.',
        ],
        'complaint_replied' => [
            'title' => 'رد على شكواك',
            'body'  => 'المجمع :clinic رد على شكواك.',
        ],
        'quote_replied' => [
            'title' => 'رد على طلب سعرك',
            'body'  => 'المجمع :clinic رد على طلب السعر لـ :service.',
        ],

        // ── Admin-side ────────────────────────────────────────────────
        'clinic_pending_approval' => [
            'title' => 'مجمع جديد بانتظار الموافقة',
            'body'  => 'المجمع :clinic سجّل عبر النموذج العام ويَنتظر مراجعتك.',
        ],
        'ai_emergency' => [
            'title' => '🚨 محادثة طوارئ من المساعد الذكي',
            'body'  => 'تم اكتشاف أعراض حرجة في محادثة المستخدم :customer.',
        ],
    ],

    // Privacy-safe preview text for Web Push lock-screen rendering —
    // never names a customer or shows medical detail. The bell + the
    // page itself show the full body once the user is authenticated.
    'preview' => [
        'booking_created'           => 'حجز جديد بانتظار مراجعتك',
        'booking_cancelled_by_user' => 'العميل ألغى حجزاً',
        'complaint_created'         => 'شكوى جديدة بانتظار الرد',
        'quote_created'             => 'طلب سعر جديد',
        'booking_confirmed'         => 'تم تأكيد حجزك',
        'complaint_replied'         => 'تم الرد على شكواك',
        'quote_replied'             => 'تم الرد على طلب سعرك',
        'clinic_pending_approval'   => 'تسجيل مجمع جديد ينتظر الموافقة',
        'ai_emergency'              => 'تنبيه: محادثة طوارئ من المساعد الذكي',
        'subscription_expiring_soon'=> 'اشتراكك يَنتهي قريباً',
        'subscription_expired'      => 'انتهى اشتراكك',
        'subscription_activated'    => 'تم تَفعيل اشتراك جديد',
        'subscription_cancelled'    => 'تم إلغاء اشتراكك',
    ],
];
