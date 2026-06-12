# خطة تكامل التتبّع: البكسلات + الموافقة (Consent) — منصّة طبية

> ملف حسّاس — يمسّ الخصوصية الصحية (PDPL)، الأمان، والأداء.
> اقرأ «المبادئ الصارمة» و«طبقات البيانات» قبل أي تنفيذ.

## ١. القرارات المعتمدة (بعد النقاش)

| القرار | الخيار المعتمد |
|--------|----------------|
| آلية المجمع | **خانات بكسل جاهزة فقط** (Meta / GA4 / Google Ads / TikTok / Snap) نرندرها نحن — **أُلغيت حاوية GTM للمجمع** (JS تعسّفي بلا فائدة إضافية) |
| GTM للمنصة (الأدمن فقط) | **مُعلّق — قرار المستخدم**: إبقاؤه (آمن لأنه مملوك للمنصة) أو إلغاؤه واستخدام خانات للمنصة أيضاً |
| نطاق تتبّع المجمع | كل الصفحات العامة **بما فيها صفحة الحجز** — عبر بكسلاتنا الجاهزة برابط معقّم وبلا PII |
| طبقات البيانات | (أ) إشارة تحويل دائماً / (ب) مطابقة مُجزّأة اختيارية بموافقة / (ج) سياق صحي **ممنوع** |
| الموافقة (Consent) | **الآن** — بانر كوكيز + Google Consent Mode v2 |
| الميزة | تفعيل/إلغاء عام من لوحة الأدمن (نافذة في الشريط الجانبي)؛ الافتراضي لكل مجمع **موقوف**؛ المجمع يطلب → الأدمن يقبل/يرفض |

## ٢. طبقات البيانات (المفتاح القانوني)

| الطبقة | المحتوى | قيمة التحسين | الخطر | القرار |
|--------|---------|--------------|-------|--------|
| **أ — إشارة التحويل** | حدث تحويل + قيمة + معرّف النقرة (fbclid/gclid/ttclid) | ٨٠–٩٠% | صفر | **دائماً** ✅ |
| **ب — مطابقة متقدّمة** | جوال/بريد **مُجزّأ (hashed)** بلا أي ذكر للخدمة | إضافي طفيف | رمادي (PDPL) | **اختياري، افتراضياً مطفأ، بموافقة صريحة** |
| **ج — السياق الصحي** | نوع الخدمة/التشخيص مربوط بشخص | لا يحسّن — تسريب فقط | **أحمر — يسبب الدعاوى** | **ممنوع منعاً باتاً** ❌ |

- منصّات الإعلان تنسب التحويل للإعلان عبر **معرّف النقرة في الكوكيز** — لا تحتاج اسم/جوال/خدمة. لذا (أ) تكفي لإقناع المجمع بنتائج إعلانه.
- بيانات العميل للمتابعة **يستلمها المجمع أصلاً في لوحته داخل المنصة** — لا داعي لتمريرها للبكسل.
- **مسؤولية قانونية:** المنصة هي «مسؤول المعالجة»؛ المخالفة تطال المنصة حتى لو أدخلها المجمع. لهذا لا نسمح بسحب بيانات صحية.

## ٣. المبادئ الصارمة

1. **صفر PII في `dataLayer` وفي البكسلات** — لا اسم/جوال/بريد/خدمة/تشخيص. أحداث مجهّلة بمعرّفات فقط.
2. **تعقيم رابط الصفحة (`page_location`)** المُرسل للبكسل على الصفحات الكاشفة للخدمة — مثلاً `/booking` بدل `/clinic/x/service/<خدمة>`. (نقدر نضمنه لأننا نرندر البكسلات بأنفسنا.)
3. **لا يُحمّل أي بكسل قبل الموافقة** — الافتراضي `denied` عبر Consent Mode v2.
4. **تحقق صارم من المعرّفات (regex) + `json_encode` عند الحقن**؛ معرّف غير صالح = يُتجاهل بصمت + يُسجّل، ولا يكسر الصفحة.
5. **الطبقة (ج) ممنوعة في الكود** — `sarhaTrack` يفرض allowlist مفاتيح يمنع تمرير الخدمة/التشخيص حتى بالخطأ.
6. **كل تحميل async.**
7. **مصدر حقيقة واحد** `TrackingContext`.
8. **تسجيل تدقيقي** لكل تغيير على إعدادات التتبّع.

## ٤. المعمارية

```
الطلب لصفحة عامة
   └─> TrackingContextResolver يبني TrackingContext من:
         • SystemSetting: feature_enabled, (platform GTM إن أُبقي), consent_enabled
         • Clinic (إن وُجد slug): tracking_status, tracking_pixels, advanced_matching_optin
         • نوع الصفحة (sensitive? booking?)  → لتعقيم الرابط
   └─> TrackingContext (singleton للطلب):
         • platformGtmId?      (اختياري حسب قرار المنصة)
         • pixels[]            {provider, id}
         • advancedMatching    (bool, طبقة ب)
         • sanitizePageUrl     (bool)
         • consentRequired
   └─> layouts/public.blade.php:
         @include('partials.tracking.consent-default')   ← قبل أي سكربت
         @include('partials.tracking.head')              ← داخل <head>
         <body> @include('partials.tracking.body')       ← noscript
         @include('partials.tracking.consent-banner')
```

### مكوّنات جديدة
- `app/Services/Tracking/TrackingContext.php` (DTO)
- `app/Services/Tracking/TrackingContextResolver.php`
- `app/Services/Tracking/PixelProvider.php` (enum المزوّدين + regex + قالب السكربت لكل مزوّد)
- `app/Rules/ValidTrackingPixel.php`
- `resources/views/partials/tracking/{consent-default,head,body,consent-banner}.blade.php`
- `resources/js/tracking.js` — `window.sarhaTrack(event, payload)` + منطق الموافقة + allowlist المفاتيح + تعقيم الرابط.

> **توافق صفحات الهبوط المستقبلية:** `TrackingContextResolver` يستقبل واجهة `Trackable` (Clinic الآن، LandingPage لاحقاً). صفحة هبوط لمجمع ترث بكسلاته؛ لعدة مجمعات يحددها الأدمن. لا إعادة بناء.

## ٥. نموذج البيانات

### `system_settings` (group=`tracking`)
- `tracking.feature_enabled` (bool) — تفعيل الميزة عامةً.
- `tracking.platform_gtm_id` (nullable) — **فقط إن قرّر المستخدم إبقاء GTM للمنصة.**
- `tracking.consent_enabled` (bool) + `consent_text_ar` / `consent_text_en`.

### `clinics` (أعمدة جديدة)
```php
$table->enum('tracking_status', ['disabled','pending','active','rejected'])->default('disabled');
$table->json('tracking_pixels')->nullable();            // [{provider,id,enabled}]
$table->boolean('advanced_matching_optin')->default(false);   // الطبقة ب
$table->text('tracking_rejection_reason')->nullable();
$table->timestamp('tracking_requested_at')->nullable();
$table->foreignId('tracking_reviewed_by')->nullable();  // admin
```
- المجمع يحرّر `tracking_pixels` كمسودة → يضغط «طلب تفعيل» → `pending`.
- الأدمن: `active` (يبدأ التتبّع) أو `rejected` + سبب. يقدر يرجّعه `disabled` (kill-switch) أي وقت.
- التتبّع يُحقن **فقط** إذا `feature_enabled` && `tracking_status==active`.

## ٦. التحقق (Validation)

| المزوّد | Regex |
|--------|-------|
| GA4 | `^G-[A-Z0-9]{4,12}$` |
| Google Ads | `^AW-\d{6,12}$` |
| Meta Pixel | `^\d{6,20}$` |
| TikTok Pixel | `^[A-Z0-9]{15,30}$` |
| Snap Pixel | `^[0-9a-f-]{36}$` |
| (GTM للمنصة فقط) | `^GTM-[A-Z0-9]{4,12}$` |

- المزوّد من allowlist ثابت (enum) لا مزوّد حر.
- حدّ أقصى **٨ بكسلات/مجمع**.
- حقن: `json_encode($id, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)`.

## ٧. مخطط الأحداث (مجهّل)

```js
sarhaTrack('view_clinic',  { clinic_id, clinic_slug, city });
sarhaTrack('view_offer',   { clinic_id, offer_id });
sarhaTrack('click_book',   { clinic_id, source });
sarhaTrack('click_call',   { clinic_id });
sarhaTrack('click_whatsapp',{ clinic_id });
sarhaTrack('submit_booking',{ clinic_id, booking_ref, value? });  // ❌ لا اسم/جوال/خدمة
```
- `sarhaTrack` يفرض allowlist للمفاتيح؛ أي مفتاح خارجها يُسقَط (حماية من تسرّب الطبقة ج).
- على صفحة الحجز: يرسل التحويل مع **رابط معقّم**.
- الطبقة (ب): إن `advanced_matching_optin` && موافقة → يُرسل جوال/بريد **مُجزّأ (SHA-256)** بلا أي حقل خدمة.

## ٨. الموافقة (Consent Mode v2)

1. قبل أي سكربت: `gtag('consent','default',{ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied', analytics_storage:'denied', wait_for_update:500})`.
2. بانر (قبول/رفض/تخصيص) — الاختيار في كوكي `sarha_consent`.
3. عند القبول: `gtag('consent','update',{...granted})` + حقن سكربتات البكسل عند هذه اللحظة فقط.
4. من رفض/لم يتفاعل = صفر بكسل = صفحة أخف.

## ٩. لوحة الأدمن (نافذة الشريط الجانبي)

- قسم «التتبّع والبكسلات»:
  - مفتاح `feature_enabled` العام.
  - (إن أُبقي) حقل GTM المنصة.
  - **طابور الطلبات** (`pending`): بيانات المجمع + بكسلاته → قبول/رفض بسبب.
  - قائمة المجمعات النشطة + إيقاف (kill-switch).
  - نص بانر الموافقة (ar/en).

## ١٠. لوحة المجمع

- قسم «بكسلات التتبّع» (يظهر فقط إن `feature_enabled`):
  - خانات المزوّدين + تحقق فوري.
  - مفتاح المطابقة المتقدّمة (ب) مع تنبيه الموافقة.
  - زر «طلب تفعيل» → `pending`؛ عرض الحالة (بانتظار/مرفوض+سبب/نشط).

## ١١. المراحل

1. **الأساس:** migration + `TrackingContext`/Resolver + التحقق + (اختياري) GTM المنصة + Consent Mode default. *قابل للاختبار وحده.*
2. **الموافقة:** بانر + بوابة التحميل. *تسبق إطلاق أي بكسل.*
3. **ميزة المجمع + الإشراف:** أعمدة + لوحة المجمع (طلب) + لوحة الأدمن (قبول/رفض/إيقاف) + الحقن المشروط.
4. **الأحداث:** غرس `sarhaTrack` المجهّل + تعقيم الرابط على صفحة الحجز.
5. **توافق صفحات الهبوط:** تفعيل `Trackable` لـ LandingPage عند بناء المنشئ.
6. **ضبط الجودة:** اختبار، تدقيق أداء (Lighthouse)، **تدقيق تسرّب PII** عبر فحص طلبات الشبكة (تأكيد عدم تسرّب الخدمة في الرابط/الحمولة).

## ١٢. قائمة فحص المخاطر

- [ ] لا PII ولا سياق صحي في أي حمولة بكسل (allowlist + مراجعة شبكة في مرحلة ٦).
- [ ] تعقيم `page_location` على الصفحات الكاشفة للخدمة.
- [ ] لا تحميل بكسل قبل الموافقة.
- [ ] معرّف خبيث لا يكسر السكربت (regex + json_encode).
- [ ] الحقن فقط عند `feature_enabled` && `active`.
- [ ] kill-switch الأدمن يوقف فوراً + تفريغ الكاش.
- [ ] المطابقة المتقدّمة (ب) مُجزّأة فقط + موافقة + بلا خدمة + افتراضياً مطفأة.
- [ ] لا تتبّع أثناء انتحال الأدمن لهوية المجمع.
- [ ] لا تتبّع للوحة React الداخلية.
- [ ] تسجيل تدقيقي لتغييرات الإعدادات والموافقة/الرفض.
- [ ] حدّ ٨ بكسلات/مجمع + async + noscript.

## ١٣. مفتوح للحسم لاحقاً

- إبقاء/إلغاء GTM المنصة (قرار المستخدم).
- ربط الميزة بالباقات المدفوعة؟ (subscription_package).
- مدّة كوكي الموافقة + الصياغة القانونية لنصّ الموافقة (مراجعة قانونية سعودية).
