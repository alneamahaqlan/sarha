# شجرة المشروع — مرجع التنسيق بين الجلسات

> الهدف: لمّا تشتغل بأكثر من جلسة بالتوازي، استخدم هالشجرة عشان كل جلسة تاخذ منطقة ملفات
> مختلفة وما يصير تعارض (conflict) في git. اتفق على المنطقة قبل ما تبدأ التعديل.

منصة **سرها** — دليل المجمعات الطبية (Laravel API + React-Admin SPA + واجهة عامة Blade).

```
sarha/
│
├── app/                                  ← كود الـ Backend (PHP / Laravel)
│   ├── Console/Commands/                 مهام مجدولة: تذكيرات، مزامنة، إشعارات
│   ├── Contracts/Messaging/              واجهات OtpChannel / WhatsAppGateway
│   ├── Enums/                            ClinicRole, NotificationEvent, PixelProvider…
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/Admin/             API لوحة مدير المنصة
│   │   │   ├── Api/V1/Clinic/            API جهة العيادة (حجوزات، CRM، كتالوج)
│   │   │   ├── Api/V1/Shared/            مصادقة، إشعارات، رفع ملفات، AI
│   │   │   ├── Auth/                     OtpController
│   │   │   └── Public/                   الموقع العام (الرئيسية، عيادة، سلة، مقارنة)
│   │   ├── Middleware/                   SetLocale, EnsureClinicFeature/Role, Tracking
│   │   ├── Requests/Api/V1/{Admin,Clinic}/   تحقق المدخلات (FormRequests)
│   │   └── Resources/Api/V1/             مخرجات الـ API (JSON Resources)
│   ├── Jobs/                             SendWebPushJob, SummarizeUserInteractionJob
│   ├── Livewire/                         AiChat
│   ├── Mail/                             ClinicApprovedMail, CriticalAlertMail
│   ├── Models/                           نماذج Eloquent (Clinic, Booking, User, CartItem…)
│   ├── Observers/                        مراقبو الأحداث (audit, customer-link)
│   ├── Policies/                         صلاحيات
│   ├── Providers/                        AppServiceProvider
│   ├── Rules/                            ValidTrackingPixels
│   ├── Services/                         منطق الأعمال
│   │   ├── Ai/{Contracts,Providers}/     Anthropic / Gemini / OpenAI
│   │   ├── Messaging/Channels/           SMS / WhatsApp OTP
│   │   ├── Otp/  Tracking/
│   │   └── (Booking, Clinic, Catalog, Homepage, Customer… services)
│   ├── Support/  View/Composers/
│
├── bootstrap/   config/   routes/        إعداد التطبيق + المسارات (api/web/console/channels)
│
├── database/
│   ├── factories/   migrations/   seeders/
│
├── lang/{ar,en}/                         الترجمات
│
├── resources/
│   ├── css/   js/                        أصول الواجهة العامة (favorites.js, app.js)
│   ├── react-admin/src/                  ← تطبيق React (لوحة الإدارة)
│   │   ├── app/        (layouts, routes)
│   │   ├── components/ lib/ types/ styles/
│   │   ├── locales/{ar,en}/admin.json
│   │   └── features/                     وحدات الميزات (كل وحدة منفصلة):
│   │       admins · ai-center · ai-chat · analytics · articles · audit-logs ·
│   │       auth · bookings · cart · catalog-services · categories ·
│   │       category-requests · cities · clinic · clinic-reports · clinics ·
│   │       complaints · customer-reports · dashboard · homepage-sections ·
│   │       impersonation · lookups · mass-notify · nav-badges ·
│   │       navigation-links · notifications · price-quotes · sales-leads ·
│   │       services · static-pages · subscription-packages · subscriptions ·
│   │       system-settings · tracking · users · whatsapp-senders
│   └── views/                            ← قوالب Blade (الواجهة العامة)
│       ├── auth/  components/  emails/  errors/
│       ├── layouts/{partials}/
│       ├── livewire/  vendor/pagination/
│       └── public/{account,pages,partials,sections}/
│
├── public/                              نقطة الدخول + أصول مبنية + sw.js
├── docs/{qa,system-dossier,tracking}/   التوثيق (ملف المستثمرين، خطة التتبع، QA)
├── e2e/   tests/{Feature,Unit}/         اختبارات (Playwright + PHPUnit)
└── scripts/                            سكربتات مساعدة
```

## مناطق العمل المقترحة لتفادي التعارض

عند العمل بجلستين، خصّص لكل جلسة طبقة كاملة:

| المنطقة | المسار | ملاحظة |
|---|---|---|
| Backend / API | `app/`, `routes/`, `database/migrations/` | المايقريشن جديد دائمًا = ملف جديد (آمن) |
| React Admin | `resources/react-admin/src/features/<feature>/` | افصل حسب الـ feature |
| الواجهة العامة | `resources/views/public/`, `resources/js/` | |
| الترجمات | `lang/{ar,en}/`, `locales/{ar,en}/admin.json` | نقطة تعارض شائعة — نسّق التعديل |
| الإعدادات/المسارات | `routes/api.php`, `routes/web.php` | ملفات مشتركة — عدّلها جلسة وحدة |

**أعلى نقاط الاحتكاك (نفس الملف يعدّله الكل):** `routes/api.php` · `routes/web.php` ·
`lang/*/site.php` · `app/Models/Clinic.php` · `app/Models/User.php`. اتفق مين يلمسها.
