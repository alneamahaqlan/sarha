<?php

return [
    // ============================================
    // Brand
    // ============================================
    'admin_brand' => 'سعرها — لوحة التحكم',
    'clinic_brand' => 'سعرها — لوحة المجمع',

    // ============================================
    // Navigation groups (admin)
    // ============================================
    'group_clinics' => 'إدارة المجمعات',
    'group_content' => 'المحتوى والخدمات',
    'group_sales' => 'المبيعات والاشتراكات',
    'group_reports' => 'تقارير وإحصائيات',
    'group_system' => 'إعدادات النظام',

    // Navigation groups (clinic panel)
    'group_my_services' => 'إدارة خدماتي',
    'group_bookings' => 'الحجوزات والعملاء',
    'group_articles' => 'المحتوى والمقالات',
    'group_settings' => 'الإعدادات',

    // ============================================
    // Resource labels
    // ============================================
    'res_clinics' => 'المجمعات',
    'res_clinic' => 'مجمع',
    'res_users' => 'العملاء',
    'res_user' => 'عميل',
    'res_admins' => 'المسؤولون',
    'res_admin' => 'مسؤول',
    'res_bookings' => 'طلبات التواصل',
    'res_booking' => 'طلب تواصل',
    'res_subscriptions' => 'الاشتراكات',
    'res_subscription' => 'اشتراك',
    'res_sales_leads' => 'العملاء المحتملون',
    'res_sales_lead' => 'عميل محتمل',
    'res_categories' => 'التخصصات',
    'res_category' => 'تخصص',
    'res_cities' => 'المدن',
    'res_city' => 'مدينة',
    'res_articles' => 'المقالات',
    'res_article' => 'مقال',
    'res_services' => 'الخدمات',
    'res_service' => 'خدمة',
    'res_audit_logs' => 'سجل المراجعة',
    'res_audit_log' => 'سجل',
    'res_my_service' => 'خدمة',
    'res_my_services' => 'خدماتي',
    'res_my_article' => 'مقال',
    'res_my_articles' => 'مقالاتي',
    'res_clinic_profile' => 'ملف المجمع',
    'res_complaints' => 'الشكاوى',
    'res_complaint' => 'شكوى',
    'res_price_quote_requests' => 'طلبات عرض السعر',
    'res_price_quote_request' => 'طلب عرض سعر',
    'res_system_settings' => 'إعدادات النظام',

    // ============================================
    // Form/Table field labels
    // ============================================
    'fields' => [
        // Identity
        'name' => 'الاسم',
        'name_ar' => 'الاسم (عربي)',
        'name_en' => 'الاسم (إنجليزي)',
        'name_clinic' => 'اسم المجمع',
        'name_service' => 'اسم الخدمة',
        'name_city_ar' => 'اسم المدينة (عربي)',
        'name_city_en' => 'اسم المدينة (إنجليزي)',
        'name_arabic' => 'الاسم العربي',
        'slug' => 'الرابط المختصر',
        'slug_short' => 'الرابط',

        // Contact
        'phone' => 'رقم الهاتف',
        'phone_customer' => 'رقم العميل',
        'email' => 'البريد الإلكتروني',
        'email_short' => 'البريد',
        'password' => 'كلمة المرور',
        'new_password' => 'كلمة مرور جديدة',

        // Location
        'city' => 'المدينة',
        'address' => 'العنوان',

        // Description / content
        'description' => 'الوصف',
        'description_clinic' => 'وصف المجمع',
        'description_service' => 'وصف الخدمة',
        'description_short' => 'وصف',
        'notes' => 'ملاحظات',
        'notes_customer' => 'ملاحظات العميل',
        'notes_clinic' => 'ملاحظات المجمع',
        'notes_sales' => 'ملاحظات المبيعات',

        // Article
        'title' => 'العنوان',
        'title_article' => 'عنوان المقال',
        'excerpt' => 'مقتطف',
        'body' => 'محتوى المقال',
        'cover_image' => 'صورة المقال',
        'image' => 'الصورة',
        'logo' => 'الشعار',
        'gallery' => 'معرض الصور',
        'ai_generated' => 'محتوى ذكاء اصطناعي',
        'ai_short' => 'AI',
        'views_count' => 'المشاهدات',

        // Social
        'website' => 'الموقع الإلكتروني',
        'instagram' => 'إنستقرام',
        'twitter' => 'تويتر/X',
        'snapchat' => 'سناب شات',
        'google_place_id' => 'Google Place ID',

        // Status / flags
        'status' => 'الحالة',
        'is_active' => 'نشط',
        'is_featured' => 'مميز',
        'is_featured_clinic' => 'مجمع مميز',
        'is_published' => 'منشور',
        'rejection_reason' => 'سبب الرفض',

        // Subscription
        'subscription_type' => 'نوع الاشتراك',
        'subscription_starts_at' => 'بداية الاشتراك',
        'subscription_ends_at' => 'نهاية الاشتراك',
        'subscription_ends_short' => 'انتهاء الاشتراك',
        'subscription' => 'الاشتراك',
        'plan_type' => 'النوع',
        'starts_at' => 'البداية',
        'ends_at' => 'النهاية',
        'amount' => 'المبلغ',
        'amount_sar' => 'المبلغ (ريال)',
        'moyasar_payment_id' => 'رقم معاملة ميسر',

        // Service
        'price' => 'السعر',
        'price_sar' => 'السعر (ريال)',
        'old_price' => 'السعر القديم',
        'old_price_sar' => 'السعر القديم (ريال)',
        'offer_expires_at' => 'انتهاء العرض',

        // Booking
        'customer_name' => 'اسم العميل',
        'service' => 'الخدمة',
        'appointment_at' => 'موعد',

        // Categories / taxonomy
        'category' => 'التخصص',
        'categories' => 'التخصصات',
        'emoji' => 'الإيموجي',
        'icon' => 'الأيقونة',
        'clinics_count' => 'عدد المجمعات',

        // Admin/User
        'admin' => 'المسؤول',
        'admin_name' => 'المسؤول',
        'role' => 'الصلاحية',
        'bookings_count' => 'طلبات الحجز',

        // Sales lead
        'clinic_name' => 'اسم المجمع',
        'contact_name' => 'اسم المسؤول',
        'next_follow_up_at' => 'موعد المتابعة التالي',
        'next_follow_up_short' => 'المتابعة التالية',
        'assigned_to' => 'مسؤول المبيعات',
        'assigned_to_short' => 'المسؤول',

        // Audit
        'action' => 'الإجراء',
        'model_type' => 'النوع',
        'model_id' => 'المعرف',
        'ip_address' => 'عنوان IP',

        // Time
        'created_at' => 'تاريخ الإنشاء',
        'created_at_request' => 'تاريخ الطلب',
        'created_at_register' => 'تاريخ التسجيل',
        'created_at_add' => 'تاريخ الإضافة',
        'updated_at' => 'تاريخ التحديث',
        'date' => 'التاريخ',

        // Misc
        'sort_order' => 'ترتيب العرض',
        'sort_order_short' => 'الترتيب',
        'tabs_basic' => 'المعلومات الأساسية',
        'tabs_categories' => 'التصنيفات',
        'tabs_subscription' => 'الاشتراك والحالة',
        'tabs_social' => 'وسائل التواصل',
        'tabs_images' => 'الصور',
        'default_dash' => '—',
        'reference_code' => 'رقم الطلب',
        'complaint_type' => 'نوع الشكوى',
        'priority' => 'الأولوية',
        'subject' => 'الموضوع',
        'description' => 'الوصف',
        'admin_notes' => 'ملاحظات الإدارة',
        'resolution' => 'الحل',
        'assigned_admin' => 'المسؤول المعيّن',
        'rejection_reason' => 'سبب الرفض',
        'clinic_notified' => 'تم إبلاغ المجمع',
        'created_at' => 'تاريخ الإنشاء',
    ],

    // ============================================
    // Status / enum values
    // ============================================
    'status' => [
        // Clinic
        'pending' => 'قيد المراجعة',
        'active' => 'نشط',
        'suspended' => 'موقوف',
        'rejected' => 'مرفوض',

        // Booking
        'new' => 'جديد',
        'contacted' => 'تم التواصل',
        'appointment_set' => 'تم تحديد موعد',
        'completed' => 'مكتمل',
        'no_show' => 'لم يحضر',
        'cancelled' => 'ملغي',

        // Subscription
        'expired' => 'منتهي',
        'pending_payment' => 'في انتظار الدفع',

        // Sales lead
        'interested' => 'مهتم',
        'negotiating' => 'جاري التفاوض',
        'converted' => 'تحول لعميل',
        'converted_short' => 'تحول',
        'lost' => 'خسر',

        // Generic filter trinary
        'true_active' => 'نشط',
        'false_suspended' => 'موقوف',
    ],

    // ============================================
    // Plan / subscription types
    // ============================================
    'plan' => [
        'basic' => 'أساسي',
        'premium' => 'مميز',
        'basic_priced' => 'أساسي (300 ريال)',
        'premium_priced' => 'مميز (400 ريال)',
        'premium_with_star' => 'مميز ⭐',
    ],

    // ============================================
    // Admin roles
    // ============================================
    'admin_role' => [
        'super_admin' => 'مدير عام',
        'admin' => 'مدير',
        'sales' => 'مبيعات',
    ],

    // ============================================
    // Action labels
    // ============================================
    'actions' => [
        'activate' => 'تفعيل',
        'suspend' => 'إيقاف',
        'create' => 'إضافة',
        'edit' => 'تعديل',
        'view' => 'عرض',
        'delete' => 'حذف',
        'restore' => 'استعادة',
        'save' => 'حفظ',
        'save_changes' => 'حفظ التغييرات',
        'cancel' => 'إلغاء',
        'add_clinic' => 'إضافة مجمع',
        'mark_in_review' => 'قيد المراجعة',
        'resolve' => 'تم الحل',
        'reject' => 'رفض',
        'notify_clinic' => 'تنبيه المجمع',
        'convert_to_basic' => 'تحويل لأساسية',
        'convert_to_premium' => 'تحويل لمميزة',
        'approve' => 'اعتماد',
        'extend_30' => 'تمديد 30 يوماً',
        'extend_90' => 'تمديد 90 يوماً',
        'generate_excerpt_ai' => 'توليد المقدمة بالذكاء',
        'generate_article_ai' => 'كتابة المقال بالذكاء',
        'analyze_excel_ai' => 'تحليل Excel بالذكاء',
        'import_from_sheets' => 'استيراد من Google Sheets',
        'login_as' => 'دخول كمجمع',
    ],

    'tabs' => [
        'all' => 'الكل',
        'today' => 'اليوم',
        'this_month' => 'هذا الشهر',
        'expiring_soon' => 'تنتهي قريباً',
    ],

    'complaint_status' => [
        'new' => 'جديدة',
        'in_review' => 'قيد المراجعة',
        'resolved' => 'تم الحل',
        'rejected' => 'مرفوضة',
    ],

    'complaint_type' => [
        'quality' => 'جودة الخدمة',
        'pricing' => 'التسعير',
        'misleading_info' => 'معلومات مضللة',
        'other' => 'أخرى',
    ],

    'complaint_priority' => [
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
    ],

    'lead_status' => [
        'new' => 'جديد',
        'contacted' => 'تم التواصل',
        'interested' => 'مهتم',
        'negotiating' => 'جاري التفاوض',
        'converted' => 'تحول لعميل',
        'lost' => 'خسر',
    ],

    'quote_status' => [
        'new' => 'جديد',
        'replied' => 'تم الرد',
        'closed' => 'مغلق',
    ],

    // ============================================
    // Validation messages
    // ============================================
    'validation' => [
        'old_price_higher' => 'السعر القديم يجب أن يكون أكبر من السعر الحالي',
        'old_price_helper' => 'عند تعبئته يجب أن يكون أكبر من السعر الحالي، ويصبح تاريخ انتهاء العرض إلزامياً',
        'offer_expires_helper' => 'مطلوب عند وجود سعر قديم (لإظهار شارة العرض)',
        'profile_saved' => 'تم حفظ التغييرات بنجاح',
    ],

    // ============================================
    // Widget strings
    // ============================================
    'widgets' => [
        'active_clinics' => 'المجمعات النشطة',
        'pending_clinics_count' => ':count مجمع قيد المراجعة',
        'today_bookings' => 'طلبات الحجز اليوم',
        'month_bookings_count' => ':count طلب هذا الشهر',
        'active_subscriptions' => 'الاشتراكات النشطة',
        'month_revenue' => ':amount ريال إيرادات الشهر',
        'registered_users' => 'العملاء المسجلون',
        'latest_bookings' => 'آخر طلبات الحجز',
        'new_bookings' => 'طلبات الحجز الجديدة',
        'of_total_bookings' => 'من إجمالي :count طلب',
        'monthly_bookings' => 'حجوزات هذا الشهر',
        'my_active_services' => 'خدماتي النشطة',
        'subscription_status' => 'حالة الاشتراك',
        'subscription_ends_label' => 'ينتهي: :date',
        'subscription_undefined' => 'غير محدد',
        'default_user_name' => 'مستخدم',
    ],
];
