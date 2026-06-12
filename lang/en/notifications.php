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
        'contact_reminder_due' => [
            'title' => 'Reminder to contact a customer',
            'body'  => 'It is time to contact :customer:note_suffix',
        ],
        'task_reminder_due' => [
            'title' => 'Task reminder',
            'body'  => 'Time for: :title:note_suffix',
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
        'cart_reminder_due' => [
            'title' => 'You have items in your cart',
            'body'  => 'You have :count item(s) waiting in your cart — finish booking now.',
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
        'sales_followup_due' => [
            'title' => 'Lead follow-up overdue',
            'body'  => 'Time to follow up with the lead ":lead":assignee_suffix',
        ],
    ],

    // Fragment appended to a sales-followup body when the lead is unassigned.
    'sales_followup_unassigned' => '(unassigned)',

    // Fragment appended to the contact-reminder body when assigned.
    'reminder' => [
        'assigned_to' => ' — assigned to :name',
    ],

    'preview' => [
        'booking_created'           => 'New booking pending your review',
        'booking_cancelled_by_user' => 'A customer cancelled a booking',
        'complaint_created'         => 'New complaint awaiting reply',
        'quote_created'             => 'New price-quote request',
        'contact_reminder_due'      => 'You have a customer to contact',
        'task_reminder_due'         => 'You have a task due',
        'booking_confirmed'         => 'Your booking was confirmed',
        'complaint_replied'         => 'Your complaint got a reply',
        'quote_replied'             => 'Your quote request got a reply',
        'cart_reminder_due'         => 'You have items waiting in your cart',
        'clinic_pending_approval'   => 'A new complex is awaiting approval',
        'ai_emergency'              => 'Alert: AI emergency conversation',
        'sales_followup_due'        => 'You have an overdue lead follow-up',
        'subscription_expiring_soon'=> 'Your subscription expires soon',
        'subscription_expired'      => 'Your subscription has expired',
        'subscription_activated'    => 'A new subscription is active',
        'subscription_cancelled'    => 'Your subscription was cancelled',
    ],
];
