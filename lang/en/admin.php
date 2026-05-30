<?php

return [
    // ============================================
    // Brand
    // ============================================
    'admin_brand' => 'Medical Complexes Directory — Admin Panel',
    'clinic_brand' => 'Medical Complexes Directory — Complex Panel',

    // ============================================
    // Navigation groups (admin)
    // ============================================
    'group_clinics' => 'Complex Management',
    'group_content' => 'Content & Services',
    'group_sales' => 'Sales & Subscriptions',
    'group_reports' => 'Reports & Analytics',
    'group_system' => 'System Settings',

    // Navigation groups (clinic panel)
    'group_my_services' => 'My Services',
    'group_bookings' => 'Bookings & Customers',
    'group_articles' => 'Content & Articles',
    'group_settings' => 'Settings',

    // ============================================
    // Resource labels
    // ============================================
    'res_clinics' => 'Complexes',
    'res_clinic' => 'Complex',
    'res_users' => 'Customers',
    'res_user' => 'Customer',
    'res_admins' => 'Administrators',
    'res_admin' => 'Administrator',
    'res_bookings' => 'Booking Requests',
    'res_booking' => 'Booking',
    'res_subscriptions' => 'Subscriptions',
    'res_subscription' => 'Subscription',
    'res_sales_leads' => 'Sales Leads',
    'res_sales_lead' => 'Lead',
    'res_categories' => 'Specialties',
    'res_category' => 'Specialty',
    'res_cities' => 'Cities',
    'res_city' => 'City',
    'res_articles' => 'Articles',
    'res_article' => 'Article',
    'res_services' => 'Services',
    'res_service' => 'Service',
    'res_audit_logs' => 'Audit Log',
    'res_audit_log' => 'Entry',
    'res_my_service' => 'Service',
    'res_my_services' => 'My Services',
    'res_my_article' => 'Article',
    'res_my_articles' => 'My Articles',
    'res_clinic_profile' => 'Complex Profile',
    'res_complaints' => 'Complaints',
    'res_complaint' => 'Complaint',
    'res_price_quote_requests' => 'Price quote requests',
    'res_price_quote_request' => 'Price quote',
    'res_system_settings' => 'System Settings',
    'res_system_setting' => 'Setting',
    'res_system_settings_plural' => 'Settings',

    // ============================================
    // Form/Table field labels
    // ============================================
    'fields' => [
        // Identity
        'name' => 'Name',
        'name_ar' => 'Name (Arabic)',
        'name_en' => 'Name (English)',
        'name_clinic' => 'Complex name',
        'name_service' => 'Service name',
        'name_city_ar' => 'City name (Arabic)',
        'name_city_en' => 'City name (English)',
        'name_arabic' => 'Arabic name',
        'slug' => 'Short URL',
        'slug_short' => 'Slug',

        // Contact
        'phone' => 'Phone number',
        'phone_customer' => 'Customer phone',
        'email' => 'Email address',
        'email_short' => 'Email',
        'password' => 'Password',
        'new_password' => 'New password',

        // Location
        'city' => 'City',
        'address' => 'Address',

        // Description / content
        'description' => 'Description',
        'description_clinic' => 'Complex description',
        'description_service' => 'Service description',
        'description_short' => 'Description',
        'notes' => 'Notes',
        'notes_customer' => 'Customer notes',
        'notes_clinic' => 'Complex notes',
        'notes_sales' => 'Sales notes',

        // Article
        'title' => 'Title',
        'title_article' => 'Article title',
        'excerpt' => 'Excerpt',
        'body' => 'Article body',
        'cover_image' => 'Cover image',
        'image' => 'Image',
        'logo' => 'Logo',
        'gallery' => 'Photo gallery',
        'ai_generated' => 'AI-generated content',
        'ai_short' => 'AI',
        'views_count' => 'Views',

        // Social
        'website' => 'Website',
        'instagram' => 'Instagram',
        'twitter' => 'Twitter / X',
        'snapchat' => 'Snapchat',
        'google_place_id' => 'Google Place ID',

        // Status / flags
        'status' => 'Status',
        'is_active' => 'Active',
        'is_featured' => 'Featured',
        'is_featured_clinic' => 'Featured complex',
        'is_published' => 'Published',
        'rejection_reason' => 'Rejection reason',

        // Subscription
        'subscription_type' => 'Subscription plan',
        'subscription_starts_at' => 'Subscription starts',
        'subscription_ends_at' => 'Subscription ends',
        'subscription_ends_short' => 'Ends on',
        'subscription' => 'Subscription',
        'plan_type' => 'Plan',
        'starts_at' => 'Starts',
        'ends_at' => 'Ends',
        'amount' => 'Amount',
        'amount_sar' => 'Amount (SAR)',
        'moyasar_payment_id' => 'Moyasar payment ID',

        // Service
        'price' => 'Price',
        'price_sar' => 'Price (SAR)',
        'old_price' => 'Old price',
        'old_price_sar' => 'Old price (SAR)',
        'offer_expires_at' => 'Offer expires at',

        // Booking
        'customer_name' => 'Customer name',
        'service' => 'Service',
        'appointment_at' => 'Appointment',

        // Categories / taxonomy
        'category' => 'Specialty',
        'categories' => 'Specialties',
        'emoji' => 'Emoji',
        'services_count' => 'Services count',
        'file' => 'File',
        'icon' => 'Icon',
        'clinics_count' => 'Complexes',

        // Admin/User
        'admin' => 'Administrator',
        'admin_name' => 'Administrator',
        'role' => 'Role',
        'bookings_count' => 'Bookings',

        // Sales lead
        'clinic_name' => 'Complex name',
        'contact_name' => 'Contact name',
        'next_follow_up_at' => 'Next follow-up',
        'next_follow_up_short' => 'Follow-up',
        'assigned_to' => 'Sales rep',
        'assigned_to_short' => 'Owner',

        // Audit
        'action' => 'Action',
        'model_type' => 'Type',
        'model_id' => 'ID',
        'ip_address' => 'IP address',

        // Time
        'created_at' => 'Created at',
        'created_at_request' => 'Request date',
        'created_at_register' => 'Registered',
        'created_at_add' => 'Added',
        'updated_at' => 'Updated',
        'date' => 'Date',

        // Misc
        'sort_order' => 'Sort order',
        'sort_order_short' => 'Order',
        'tabs_basic' => 'Basic info',
        'tabs_categories' => 'Specialties',
        'tabs_subscription' => 'Subscription & status',
        'tabs_social' => 'Social links',
        'tabs_images' => 'Images',
        'default_dash' => '—',
        'reference_code' => 'Reference',
        'complaint_type' => 'Complaint type',
        'priority' => 'Priority',
        'subject' => 'Subject',
        'admin_notes' => 'Admin notes',
        'resolution' => 'Resolution',
        'assigned_admin' => 'Assigned admin',
        'clinic_notified' => 'Complex notified',
        'setting_key' => 'Key',
        'value' => 'Value',
        'value_type' => 'Type',
        'group' => 'Group',
        'label' => 'Label',
        'license_number' => 'License number',
        'district' => 'District',
        'last_contact_at' => 'Last contact',
        'sales_notes' => 'Sales notes',
        'service_name' => 'Service name',
        'clinic_reply' => 'Complex reply',
        'days_left' => 'Days left',
        'sub_clinic' => 'Sub clinic',
    ],

    // ============================================
    // Status / enum values
    // ============================================
    'status' => [
        // Clinic
        'pending' => 'Pending',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'rejected' => 'Rejected',

        // Booking
        'new' => 'New',
        'contacted' => 'Contacted',
        'appointment_set' => 'Appointment set',
        'completed' => 'Completed',
        'no_show' => 'No-show',
        'cancelled' => 'Cancelled',

        // Subscription
        'expired' => 'Expired',
        'pending_payment' => 'Pending payment',

        // Sales lead
        'interested' => 'Interested',
        'negotiating' => 'Negotiating',
        'converted' => 'Converted',
        'converted_short' => 'Converted',
        'lost' => 'Lost',

        // Generic filter trinary
        'true_active' => 'Active',
        'false_suspended' => 'Suspended',
    ],

    // ============================================
    // Plan / subscription types
    // ============================================
    'plan' => [
        'basic' => 'Basic',
        'premium' => 'Premium',
        'basic_priced' => 'Basic (300 SAR)',
        'premium_priced' => 'Premium (400 SAR)',
        'premium_with_star' => 'Premium',
    ],

    // ============================================
    // Admin roles
    // ============================================
    'admin_role' => [
        'super_admin' => 'Super admin',
        'admin' => 'Admin',
        'sales' => 'Sales',
    ],

    // ============================================
    // Action labels
    // ============================================
    'actions' => [
        'activate' => 'Activate',
        'suspend' => 'Suspend',
        'create' => 'Create',
        'edit' => 'Edit',
        'view' => 'View',
        'delete' => 'Delete',
        'restore' => 'Restore',
        'save' => 'Save',
        'save_changes' => 'Save changes',
        'cancel' => 'Cancel',
        'add_clinic' => 'Add complex',
        'mark_in_review' => 'Mark in review',
        'resolve' => 'Resolve',
        'reject' => 'Reject',
        'notify_clinic' => 'Notify complex',
        'convert_to_basic' => 'Convert to Basic',
        'convert_to_premium' => 'Convert to Premium',
        'approve' => 'Approve',
        'extend_30' => 'Extend 30 days',
        'extend_90' => 'Extend 90 days',
        'generate_excerpt_ai' => 'Generate excerpt with AI',
        'generate_article_ai' => 'Write article with AI',
        'analyze_excel_ai' => 'Analyze Excel with AI',
        'import_from_sheets' => 'Import from Google Sheets',
        'login_as' => 'Log in as complex',
    ],

    'tabs' => [
        'all' => 'All',
        'today' => 'Today',
        'this_month' => 'This month',
        'expiring_soon' => 'Expiring soon',
    ],

    'complaint_status' => [
        'new' => 'New',
        'in_review' => 'In review',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
    ],

    'complaint_type' => [
        'quality' => 'Service quality',
        'pricing' => 'Pricing',
        'misleading_info' => 'Misleading info',
        'other' => 'Other',
    ],

    'complaint_priority' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],

    'import_services_title' => 'Import services (Excel/CSV)',
    'import_analysis_results' => 'Column analysis results',
    'import_no_file' => 'Please upload a file first',
    'import_unreadable' => 'Could not read the file',
    'import_empty' => 'File is empty or has no valid rows',
    'import_no_match' => 'No match',
    'import_no_mapping' => 'No columns can be imported (confidence <50%)',
    'import_analyze_first' => 'Analyze the file first',
    'import_ai_disabled_heuristic' => 'AI not configured — used simple heuristic matching',
    'import_import_now' => 'Import :count rows',
    'import_success' => ':count services imported successfully ✓',

    'lead_converted' => 'Lead ":clinic" converted to an active complex ✓',
    'subscription_extended' => 'Subscription extended by :days days',
    'booking_approved' => 'Complex approved and 90-day subscription activated ✓',

    'impersonation_banner' => 'You are managing this complex panel on behalf of admin :admin — every action is logged',
    'impersonation_stop' => 'End session',
    'impersonation_confirm_heading' => 'Log in as ":clinic"?',
    'impersonation_confirm_body' => 'Your access will be recorded in the audit log, and the complex will be notified. You can return at any time via the red banner at the top.',

    'mass_notify_title' => 'Send mass notification',
    'mass_notify_audience' => 'Target audience',
    'mass_notify_audience_all' => 'All active complexes',
    'mass_notify_audience_premium' => 'Premium only',
    'mass_notify_audience_basic' => 'Basic only',
    'mass_notify_audience_expiring' => 'Expiring within 10 days',
    'mass_notify_urgent' => 'Urgent',
    'mass_notify_send' => 'Send now',
    'mass_notify_sent' => 'Notification sent to :count complexes ✓',
    'mass_notify_confirm_heading' => 'Confirm send',
    'mass_notify_confirm_body' => 'An in-app notification will be created for all recipients immediately.',

    'notif' => [
        'bell_label' => 'Notifications',
        'mark_all_read' => 'Mark all as read',
        'view_all' => 'View all notifications',
        'empty' => 'No notifications right now',
        'just_now' => 'Just now',

        'new_booking_title' => 'New contact request',
        'new_booking_body'  => ':customer submitted a request to :clinic',
        'new_booking_clinic_title' => 'New contact request',
        'new_booking_clinic_body'  => ':customer (ref :ref)',

        'new_complaint_title' => 'New complaint',
        'new_complaint_body'  => ':subject',

        'new_lead_title' => 'New complex registration',
        'new_lead_body'  => ':clinic requested to join — review in the sales pipeline',

        'new_quote_title' => 'New price quote request',
        'new_quote_body'  => 'Service: :service',
        'broadcast_quote_title' => 'New price quote request in your city',
        'broadcast_quote_body'  => 'Service: :service — reply quickly to win the customer',

        'lead_converted_title' => 'Lead converted',
        'lead_converted_body'  => ':clinic is now an active complex',

        'expiring_title' => 'Subscription expiring soon',
        'expiring_body'  => ':clinic subscription ends in :days days',

        'expired_title' => 'Your subscription expired',
        'expired_body'  => 'Your complex is no longer visible to visitors — renew to be listed again.',
        'expired_title_admin' => ':clinic subscription expired',
        'expired_body_admin'  => 'The complex is no longer visible to visitors.',

        'offer_expiring_title' => 'Offers ending soon',
        'offer_expiring_body'  => 'You have :count offers ending within 3 days',

        'approved_title' => 'Your complex was approved ✓',
        'approved_body'  => 'A 90-day subscription has been activated. You can now manage your services.',

        'rejected_title' => 'Your complex registration was rejected',
        'rejected_body'  => 'Reason: :reason',

        'impersonated_title' => 'An admin entered your account',
        'impersonated_body'  => 'Admin :admin is managing your panel for review',

        'limit_warn_title' => 'Article limit approaching',
        'limit_warn_body'  => 'You have published :used of :limit articles this month. Upgrade for unlimited articles.',
        'limit_hit_title' => 'Monthly limit reached',
        'limit_hit_body'  => 'Your article was saved as a draft because your current plan allows :limit published articles per month.',

        'article_published_title' => 'New article published',
        'article_published_body'  => ':clinic published ":article" — review the content.',
    ],

    'ai' => [
        'title_first' => 'Please enter the article title first',
        'not_configured' => 'Claude API key is not configured. Add ANTHROPIC_API_KEY to .env',
        'generated' => 'Generated successfully',
        'failed' => 'Generation failed',
        'excel_analyzing' => 'Analyzing file…',
        'excel_done' => 'Analysis complete',
    ],

    'lead_status' => [
        'new' => 'New',
        'contacted' => 'Contacted',
        'interested' => 'Interested',
        'negotiating' => 'Negotiating',
        'converted' => 'Converted',
        'lost' => 'Lost',
    ],

    'quote_status' => [
        'new' => 'New',
        'replied' => 'Replied',
        'closed' => 'Closed',
    ],

    // ============================================
    // Validation messages
    // ============================================
    'validation' => [
        'old_price_higher' => 'Old price must be greater than current price',
        'old_price_helper' => 'When filled, must be greater than current price and the offer expiry becomes required',
        'offer_expires_helper' => 'Required when an old price is set (so the offer badge can be displayed)',
        'profile_saved' => 'Changes saved successfully',
        'city_has_clinics' => 'Cannot delete a city that has complexes',
        'category_has_clinics' => 'Cannot delete a specialty that has complexes',
        'sub_clinic_has_services' => 'Cannot delete a clinic that has services',
    ],

    // Specialty (category) requests submitted by complexes
    'category_requests' => [
        'submitted' => 'Your specialty request was sent to the admins for review',
        'approved' => 'Specialty approved and added',
        'rejected' => 'Specialty request rejected',
        'already_reviewed' => 'This request has already been reviewed',
    ],

    // ============================================
    // Widget strings
    // ============================================
    'widgets' => [
        'active_clinics' => 'Active complexes',
        'pending_clinics_count' => ':count complex(es) pending review',
        'today_bookings' => 'Today\'s bookings',
        'month_bookings_count' => ':count booking(s) this month',
        'active_subscriptions' => 'Active subscriptions',
        'month_revenue' => ':amount SAR revenue this month',
        'registered_users' => 'Registered customers',
        'latest_bookings' => 'Latest booking requests',
        'new_bookings' => 'New booking requests',
        'of_total_bookings' => 'of :count total',
        'monthly_bookings' => 'Bookings this month',
        'my_active_services' => 'My active services',
        'subscription_status' => 'Subscription status',
        'subscription_ends_label' => 'Ends: :date',
        'subscription_undefined' => 'Not set',
        'default_user_name' => 'Customer',
    ],

    'user_profile' => [
        'timeline' => [
            'login'             => 'Logged in',
            'logout'             => 'Logged out',
            'otp_verified'       => 'OTP verified',
            'search'             => 'Searched for ":q"',
            'filter'             => 'Applied city/category filter',
            'clinic_view'        => 'Visited :clinic',
            'compare'            => 'Compared :count complexes',
            'action_click'       => 'Clicked :button on :clinic',
            'account_edit'       => 'Edited account details',
            'booking_created'    => 'Created booking at :clinic — :service',
            'booking_cancelled'  => 'Cancelled booking at :clinic',
            'complaint_created'  => 'Filed complaint: :subject',
            'ai_conversation'    => 'AI conversation (:turns turns): :query',
            'favorite_added'     => 'Favorited :clinic',
            'quote_request'      => 'Quote request for :service',
        ],
    ],

    'packages' => [
        'cannot_delete_with_clinics' => 'Cannot delete a package with :count clinic(s) on it. Move them to another package first.',
    ],

    'subscriptions' => [
        'renewal_note' => 'Auto-renewal of previous subscription #:ref',
        'approval_trial_note' => 'Trial period granted on clinic approval (:days days).',
    ],
];
