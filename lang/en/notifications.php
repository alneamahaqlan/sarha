<?php

return [
    'events' => [
        // ── Clinic-side ───────────────────────────────────────────────
        'booking_created' => [
            'title' => 'New booking received',
            'body'  => ':customer just booked appointment :reference_code.',
        ],
        'booking_cancelled_by_user' => [
            'title' => 'Customer cancelled a booking',
            'body'  => 'Booking :reference_code was cancelled by :customer.',
        ],
        'complaint_created' => [
            'title' => 'New complaint',
            'body'  => ':customer filed a complaint that needs a reply.',
        ],
        'quote_created' => [
            'title' => 'New price-quote request',
            'body'  => ':customer is asking for a quote on :service.',
        ],

        // ── Subscription lifecycle ────────────────────────────────────
        'subscription_expiring_soon' => [
            'title' => 'Your subscription expires soon',
            'body'  => 'Your ":package" plan expires in :days days — contact admin to renew.',
        ],
        'subscription_expired' => [
            'title' => 'Your subscription has expired',
            'body'  => 'Your ":package" plan has ended. You have :grace grace days before features are suspended — contact admin to renew.',
        ],
        'subscription_activated' => [
            'title' => 'Your subscription is active',
            'body'  => 'Your ":package" plan is active until :ends_at. Thank you for choosing the platform.',
        ],
        'subscription_cancelled' => [
            'title' => 'Your subscription was cancelled',
            'body'  => 'Your ":package" subscription has been cancelled. Contact admin for any questions.',
        ],

        // ── User-side ─────────────────────────────────────────────────
        'booking_confirmed' => [
            'title' => 'Booking confirmed',
            'body'  => ':clinic confirmed your booking :reference_code.',
        ],
        'complaint_replied' => [
            'title' => 'Reply on your complaint',
            'body'  => ':clinic replied to your complaint.',
        ],
        'quote_replied' => [
            'title' => 'Reply on your quote',
            'body'  => ':clinic replied to your price request for :service.',
        ],

        // ── Admin-side ────────────────────────────────────────────────
        'clinic_pending_approval' => [
            'title' => 'New complex pending approval',
            'body'  => ':clinic signed up via the public form and needs review.',
        ],
        'ai_emergency' => [
            'title' => '🚨 AI emergency conversation',
            'body'  => 'Critical symptoms detected in user :customer\'s chat.',
        ],
    ],

    'preview' => [
        'booking_created'           => 'New booking pending your review',
        'booking_cancelled_by_user' => 'A customer cancelled a booking',
        'complaint_created'         => 'New complaint awaiting reply',
        'quote_created'             => 'New price-quote request',
        'booking_confirmed'         => 'Your booking was confirmed',
        'complaint_replied'         => 'Your complaint got a reply',
        'quote_replied'             => 'Your quote request got a reply',
        'clinic_pending_approval'   => 'A new complex is awaiting approval',
        'ai_emergency'              => 'Alert: AI emergency conversation',
        'subscription_expiring_soon'=> 'Your subscription expires soon',
        'subscription_expired'      => 'Your subscription has expired',
        'subscription_activated'    => 'A new subscription is active',
        'subscription_cancelled'    => 'Your subscription was cancelled',
    ],
];
