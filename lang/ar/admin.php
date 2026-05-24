<?php

return [
    // ============================================
    // Brand
    // ============================================
    'admin_brand' => 'دليل المجمعات الطبية — لوحة التحكم',
    'clinic_brand' => 'دليل المجمعات الطبية — لوحة المجمع',

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
    'res_system_setting' => 'إعداد',
    'res_system_settings_plural' => 'الإعدادات',

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
        'services_count' => 'عدد الخدمات',
        'file' => 'الملف',
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
        'admin_notes' => 'ملاحظات الإدارة',
        'resolution' => 'الحل',
        'assigned_admin' => 'المسؤول المعيّن',
        'clinic_notified' => 'تم إبلاغ المجمع',
        'setting_key' => 'المفتاح',
        'value' => 'القيمة',
        'value_type' => 'النوع',
        'group' => 'المجموعة',
        'label' => 'التسمية',
        'license_number' => 'رقم الترخيص',
        'district' => 'الحي',
        'last_contact_at' => 'آخر تواصل',
        'sales_notes' => 'ملاحظات المبيعات',
        'service_name' => 'اسم الخدمة',
        'clinic_reply' => 'رد المجمع',
        'days_left' => 'أيام متبقية',
        'sub_clinic' => 'العيادة الفرعية',
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
        'premium_with_star' => 'مميز',
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

    'import_services_title' => 'استيراد الخدمات (Excel/CSV)',
    'import_analysis_results' => 'نتائج تحليل الأعمدة',
    'import_no_file' => 'يرجى رفع ملف أولاً',
    'import_unreadable' => 'تعذّر قراءة الملف',
    'import_empty' => 'الملف فارغ أو لا يحتوي على صفوف صالحة',
    'import_no_match' => 'لم يتم التطابق',
    'import_no_mapping' => 'لا توجد أعمدة قابلة للاستيراد (الثقة <50%)',
    'import_analyze_first' => 'حلّل الملف أولاً',
    'import_ai_disabled_heuristic' => 'AI غير مفعّل — تم استخدام مطابقة بسيطة',
    'import_import_now' => 'استورد :count صف',
    'import_success' => 'تم استيراد :count خدمة بنجاح ✓',

    'lead_converted' => 'تم تحويل العميل المحتمل ":clinic" إلى مجمع نشط ✓',
    'subscription_extended' => 'تم تمديد الاشتراك :days يوماً',
    'booking_approved' => 'تم اعتماد المجمع وتفعيل اشتراك 90 يوماً ✓',

    'impersonation_banner' => 'أنت تدير لوحة المجمع نيابة عن المسؤول :admin — كل إجراء يُسجَّل',
    'impersonation_stop' => 'إنهاء الجلسة',
    'impersonation_confirm_heading' => 'الدخول كمجمع ":clinic"؟',
    'impersonation_confirm_body' => 'سيُسجَّل دخولك في سجل المراجعة، وسيظهر للمجمع تنبيه بدخولك. يمكنك العودة في أي وقت من الشريط الأحمر أعلى الصفحة.',

    'mass_notify_title' => 'إرسال إشعار جماعي',
    'mass_notify_audience' => 'الفئة المستهدفة',
    'mass_notify_audience_all' => 'جميع المجمعات النشطة',
    'mass_notify_audience_premium' => 'المميزة فقط',
    'mass_notify_audience_basic' => 'الأساسية فقط',
    'mass_notify_audience_expiring' => 'تنتهي خلال 10 أيام',
    'mass_notify_urgent' => 'عاجل',
    'mass_notify_send' => 'إرسال الآن',
    'mass_notify_sent' => 'تم إرسال الإشعار إلى :count مجمع ✓',
    'mass_notify_confirm_heading' => 'تأكيد الإرسال',
    'mass_notify_confirm_body' => 'سيتم إنشاء إشعار داخلي لجميع المستلمين فوراً.',

    'notif' => [
        'bell_label' => 'الإشعارات',
        'mark_all_read' => 'تحديد الكل كمقروء',
        'view_all' => 'عرض كل الإشعارات',
        'empty' => 'لا توجد إشعارات حالياً',
        'just_now' => 'الآن',

        'new_booking_title' => 'طلب تواصل جديد',
        'new_booking_body'  => ':customer أرسل طلب تواصل مع :clinic',
        'new_booking_clinic_title' => 'طلب تواصل جديد من عميل',
        'new_booking_clinic_body'  => ':customer (طلب :ref)',

        'new_complaint_title' => 'شكوى جديدة',
        'new_complaint_body'  => ':subject',

        'new_lead_title' => 'طلب تسجيل مجمع جديد',
        'new_lead_body'  => ':clinic طلب الانضمام — راجعه في قائمة المبيعات',

        'new_quote_title' => 'طلب عرض سعر جديد',
        'new_quote_body'  => 'خدمة: :service',

        'lead_converted_title' => 'تم تحويل عميل محتمل',
        'lead_converted_body'  => ':clinic أصبح مجمعاً نشطاً',

        'expiring_title' => 'اشتراك يقترب من الانتهاء',
        'expiring_body'  => 'اشتراك :clinic سينتهي خلال :days يوماً',

        'expired_title' => 'انتهى اشتراكك',
        'expired_body'  => 'لم يعد مجمعك ظاهراً للزوار — جدّد الاشتراك للعودة.',
        'expired_title_admin' => 'انتهى اشتراك :clinic',
        'expired_body_admin'  => 'المجمع لم يعد ظاهراً للزوار.',

        'offer_expiring_title' => 'عروض ستنتهي قريباً',
        'offer_expiring_body'  => 'لديك :count عرض ستنتهي خلال 3 أيام',

        'approved_title' => 'تم اعتماد مجمعك ✓',
        'approved_body'  => 'تم تفعيل اشتراكك لمدة 90 يوماً. يمكنك الآن إدارة خدماتك.',

        'rejected_title' => 'تم رفض تسجيل مجمعك',
        'rejected_body'  => 'السبب: :reason',

        'impersonated_title' => 'دخل مسؤول إلى حسابك',
        'impersonated_body'  => 'الأدمن :admin يدير لوحتك حالياً للمراجعة',

        'limit_warn_title' => 'قاربت على حد المقالات',
        'limit_warn_body'  => 'نشرت :used من 5 مقالات هذا الشهر. ترقّى للمميزة لمقالات غير محدودة.',
        'limit_hit_title' => 'تجاوزت الحد الشهري',
        'limit_hit_body'  => 'تم حفظ مقالتك كمسودة لأن باقتك الأساسية تسمح بـ 5 مقالات شهرياً.',

        'article_published_title' => 'تم نشر مقال جديد',
        'article_published_body'  => 'نشر :clinic مقال ":article" — راجع المحتوى.',
    ],

    'ai' => [
        'title_first' => 'أدخل عنوان المقال أولاً',
        'not_configured' => 'مفتاح Claude API غير مفعّل. أضف ANTHROPIC_API_KEY في .env',
        'generated' => 'تم التوليد بنجاح',
        'failed' => 'تعذّر التوليد',
        'excel_analyzing' => 'يجري تحليل الملف…',
        'excel_done' => 'تم التحليل',
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
        'city_has_clinics' => 'لا يمكن حذف مدينة مرتبطة بمجمعات',
        'category_has_clinics' => 'لا يمكن حذف تصنيف مرتبط بمجمعات',
        'sub_clinic_has_services' => 'لا يمكن حذف عيادة مرتبطة بخدمات',
    ],

    // Specialty (category) requests submitted by complexes
    'category_requests' => [
        'submitted' => 'تم إرسال طلب التخصص للإدارة للمراجعة',
        'approved' => 'تم اعتماد التخصص وإضافته',
        'rejected' => 'تم رفض طلب التخصص',
        'already_reviewed' => 'تمت مراجعة هذا الطلب مسبقاً',
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
