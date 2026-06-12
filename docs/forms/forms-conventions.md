# قواعد النماذج الموحّدة (Forms Conventions)

> **الحالة:** قيد التنفيذ — المرحلة الأولى (React) بدأت بنموذج أولي.
> **آخر تحديث:** 2026-06-10

قاعدتان إلزاميتان تُطبَّقان على **كل** نماذج النظام بكل أركانه (الأدمن، لوحة المجمع، العام):

1. **كل قائمة منسدلة قابلة للبحث** عند تجاوز عدد الخيارات حدّاً معيّناً.
2. **عند الضغط على زر الحفظ مع وجود حقول إجبارية ناقصة**: يظهر
   - نص أحمر صغير **تحت كل حقل** ناقص، و
   - **صندوق أحمر أسفل النموذج** يسرد كل الحقول الإجبارية الناقصة.

المبدأ الهندسي: **المركزية لا النسخ** — التعديل في مكوّنات مشتركة لا في كل نموذج على حدة.

> ⚠️ **قاعدة إلزامية ثالثة (لإظهار كل الأخطاء دفعة واحدة):** كل نموذج إدخال يجب أن يحمل `noValidate` (React) / `novalidate` (Blade). بدونها يتولّى المتصفح التحقّق الأصلي ويوقف الإرسال عند **أول** حقل فارغ (فقاعة واحدة كل مرة)، فلا يصل الأمر إلى تحقّق zod/السيرفر الذي يُظهر كل الحقول معاً تحت كل حقل + في الملخّص. (مُطبّقة على كل نماذج React الـ 30 ونماذج Blade لإدخال البيانات.)

---

## بنية النماذج في النظام (مؤكَّدة من الكود)

| الركن | التقنية | عدد النماذج/القوائم | الحالة |
|-------|---------|--------------------|--------|
| الأدمن + لوحة المجمع | React SPA — `react-hook-form` + `zod` + Tailwind | **55** ملفاً تستورد `Select` المشترك، **27** ملف نماذج | قيد التنفيذ |
| العام (Public) | Blade + Alpine | **8** ملفات `<select>` | لاحقاً (المرحلة 2) |

نقطة الاختناق الذهبية: **كل القوائم في React تستورد مكوّناً واحداً** هو
[components/ui/select.tsx](../../resources/react-admin/src/components/ui/select.tsx) → تعديله يعمّ تلقائياً.

---

## القيد التقني الحاسم

`Select` المشترك يُستخدم بطريقتين تعتمدان **دلالات `<select>` الأصلية**:
- `{...form.register('field')}` (حدث `onChange` أصلي) — 18 موضعاً.
- `value={...} onChange={(e) => e.target.value}` (مُتحكَّم) — ~48 موضعاً.

لذلك **لا يجوز** استبدال `<select>` بمكوّن مخصّص (سيكسر `register` و`e.target.value` و`<option>` children).

**الحل المعتمد:** `Select` يُبقي عنصر `<select>` أصلياً **مخفياً** (يستقبل كل الـ props كما هي) ويضيف فوقه **طبقة بحث مرئية**. عند الاختيار من الطبقة المرئية يُضبط قيمة الـ select المخفي عبر الـ native setter ويُطلَق حدث `change` يتصاعد (bubbles) → فيشتغل `register` و`onChange` المُتحكَّم معاً. النتيجة: **صفر تعديل في الـ 55 موضعاً**.

---

## المكوّنات المشتركة (المرحلة 1 — React)

| المكوّن | الموقع | الدور |
|---------|--------|------|
| `Select` (مطوّر) | `components/ui/select.tsx` | قائمة منسدلة قابلة للبحث، نفس الـ API السابق تماماً |
| `FieldError` | `components/forms/FieldError.tsx` | نص أحمر تحت الحقل (بدل تكرار `<p className=destructive>`) |
| `FormErrorSummary` | `components/forms/FormErrorSummary.tsx` | صندوق أحمر أسفل النموذج يسرد الحقول الإجبارية الناقصة |

### `Select` — خصائص جديدة (اختيارية، متوافقة رجعياً)
- `searchable?: boolean` — فرض إظهار/إخفاء مربع البحث. الافتراضي: يظهر تلقائياً عند **> 5 خيارات**.
- `placeholder?: string` — نص العنصر النائب.
- البحث **عربي-آمن**: يطبّع الهمزات/الألف/التاء المربوطة والتشكيل قبل المطابقة.
- دعم RTL، إغلاق بالنقر خارجاً و Escape، تنقّل بالأسهم.

### نمط الاستخدام في أي نموذج (بعد التطوير)
```tsx
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';

// تحت الحقل:
<FieldError message={form.formState.errors.name?.message} />

// قبل DialogFooter / زر الحفظ:
<FormErrorSummary errors={form.formState.errors} labels={{ name: t('admins.name'), role: t('admins.role') }} />
```
- `labels` اختياري: يحوّل اسم الحقل التقني إلى تسمية مقروءة في الملخص. إن غاب يُعرض اسم الحقل.

---

## كيف تجد كل المواضع (أوامر قابلة للتكرار)

```bash
# قوائم React المنسدلة (تستورد المكوّن المشترك):
grep -rl "@/components/ui/select" resources/react-admin/src        # 55

# نماذج React (react-hook-form):
grep -rl "react-hook-form" resources/react-admin/src              # 27

# قوائم Blade العامة:
grep -rl "<select" resources/views                                # 8
```

---

## حالة التعميم

1. **النموذج الأولي:** `AdminForm.tsx`. ✅
2. **تعميم React — مكتمل ✅:** 29 نموذجاً موحَّداً (`FieldError` + `FormErrorSummary`)، يشمل:
   - نماذج react-hook-form (إنشاء/تعديل الكيانات + صفحات الفهرس ذات الحواري).
   - **نماذج التحقّق اليدوي** (`errors: Record<string,string>`): SubscriptionForm، TeamMemberFormDialog، PackageEditDialog، AiRestrictionDialog — لذا وُسِّع `FormErrorSummary` ليقبل القيم النصّية إضافةً لشكل RHF.
   - البحث في القوائم يعمل تلقائياً في كل النظام عبر `Select` المطوّر.
3. **استثناءات مقصودة (لم تُوحَّد):**
   - تلميحات تحقّق فوري (`invalid` + رسالة ثابتة) في صفحتي التتبّع — نمط لحظي لا «خطأ عند الحفظ».
   - حواري إجراء بحقل واحد (ClinicActions، complaints/ActionDialogs، SettingEditDialog) — الرسالة الحمراء المضمّنة تكفي والملخّص سيكون تكراراً.
4. **المرحلة 2 — Blade — مكتملة ✅:**
   - مكوّنان: [`components/form/select.blade.php`](../../resources/views/components/form/select.blade.php) (Alpine، يبقي `<select>` أصلياً مخفياً كطبقة، نفس فلسفة React) و[`components/form/errors.blade.php`](../../resources/views/components/form/errors.blade.php) (صندوق أحمر من `$errors`، يدعم `:only=[...]`).
   - 18 قائمة محوَّلة عبر 8 ملفات عامة (التسجيل/الحجز/الشكاوى/البلاغات/feedback/landing/البحث/الواجهة).
   - ملخّصات الأخطاء العلوية وُحِّدت إلى `<x-form.errors />`.
   - مفاتيح ترجمة: `site.form_select_placeholder/form_search/form_no_results/form_errors_title`.
   - **استثناء مقصود:** قائمتا «نوع العلاقة» في booking-form (مرتبطتان بـ JS عبر `data-relationship-select`/`data-edit-field`؛ التحويل يخاطر بكسر منطق الأقارب، وهما قائمتان صغيرتان).

### استخدام Blade
```blade
<x-form.errors />                 {{-- ملخّص أحمر لكل الأخطاء --}}
<x-form.select name="city_id" required>
    <option value="">@lang('site.search_all_cities')</option>
    @foreach($cities as $c)
        <option value="{{ $c->id }}" @selected(old('city_id') == $c->id)>{{ $c->display_name }}</option>
    @endforeach
</x-form.select>
```
- مرّر `<option>` كـ slot تماماً كـ `<select>` عادي؛ `required`/`old()`/المتحكّمات تعمل كما هي.
- كلاسات التخطيط (مثل `md:col-span-2`) على `<x-form.select class="...">` تذهب لعنصر الغلاف؛ تنسيق المظهر يوفّره المكوّن.
- يحتاج `php artisan view:clear` بعد تعديل المكوّن + `vite build` لتوليد كلاسات Tailwind الجديدة.

> ملاحظة جودة: فحص `tsc` يُظهر أخطاء نوع سابقة (zod-resolver generics) موثّقة في ذاكرة `react-admin-build-workflow`؛ لم تُدخِل هذه المهمة أي خطأ نوع جديد، والبناء عبر esbuild ناجح.

---

## ملاحظات للأداء/الجودة
- البناء عبر Vite مطلوب بعد كل تعديل tsx (انظر ذاكرة `react-admin-build-workflow`).
- للقوائم الضخمة (مثل قائمة المجمعات) البحث يقلّل التمرير ويُحسّن الإدخال.
- إمكانية لاحقة: إبقاء `<select>` الأصلي على شاشات اللمس (UX أفضل للجوال) — مؤجّلة.
</content>

