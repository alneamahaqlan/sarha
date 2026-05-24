<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds ~12 months of realistic usage on top of the base structure created by
 * DemoSeeder (clinics, services, doctors, categories, cities). It scales the
 * transactional / engagement tables and spreads dates across 365 days with a
 * recency bias, so the platform feels like it has been live for a year.
 *
 *  - Additive & idempotent: every section tops the table up toward a target and
 *    converges on re-run instead of duplicating.
 *  - DB::table()->insert() only (no observers, no Faker) — production-safe.
 *  - Run AFTER DemoSeeder:
 *      php artisan db:seed --class=DemoSeeder --force
 *      php artisan db:seed --class=YearOfUsageSeeder --force
 */
class YearOfUsageSeeder extends Seeder
{
    private Carbon $now;
    private int $year = 365;

    public function run(): void
    {
        $this->now = now();

        if (DB::table('clinics')->count() === 0 || DB::table('services')->count() === 0) {
            $this->command?->warn('YearOfUsageSeeder: run DemoSeeder first (needs clinics + services).');
            return;
        }

        // Targets scale with the directory size so density stays realistic as
        // more complexes are added (e.g. via BulkShowcaseSeeder).
        $n = DB::table('clinics')->count();

        $this->seedUsers(max(300, $n * 6));
        $this->seedClinicStatsYear();
        $this->seedBookings(max(1000, $n * 25));
        $this->seedQuotes(max(350, $n * 3));
        $this->seedBroadcastQuotes(max(40, intdiv($n, 2)));
        $this->seedReviews(max(420, $n * 15));
        $this->seedSubscriptionHistory();
        $this->seedAiConversations(max(250, $n * 4));
        $this->seedArticles(max(120, $n * 5));
        $this->seedComplaints(max(90, $n));
        $this->seedFavorites(max(250, $n * 8));
        $this->seedAuditLogs(max(250, $n * 3));
        $this->seedNotifications(max(120, $n * 2));
        $this->seedSalesLeads(max(70, $n));

        $this->command?->info('YearOfUsageSeeder: a full year of usage is in place.');
    }

    // ---------- helpers ----------

    /** Days-ago with a recency bias (min of two uniforms skews toward small/recent). */
    private function dayAgo(?int $max = null): int
    {
        $max ??= $this->year;
        return min(rand(0, $max), rand(0, $max));
    }

    private function at(int $daysAgo): string
    {
        return $this->now->copy()->subDays($daysAgo)
            ->setTime(rand(8, 21), rand(0, 59), rand(0, 59))->toDateTimeString();
    }

    private function phone(int $seed): string
    {
        return '05' . str_pad((string) (10000000 + $seed), 8, '0', STR_PAD_LEFT);
    }

    private function pick(array $a)
    {
        return $a[array_rand($a)];
    }

    private function ids(string $table): array
    {
        return DB::table($table)->pluck('id')->all();
    }

    private function bulk(string $table, array $rows, int $chunk = 500): void
    {
        foreach (array_chunk($rows, $chunk) as $c) {
            DB::table($table)->insert($c);
        }
    }

    // ---------- sections ----------

    private function seedUsers(int $target): void
    {
        $existing = DB::table('users')->count();
        if ($existing >= $target) {
            return;
        }
        $names = ['أحمد محمد', 'فاطمة علي', 'خالد عبدالله', 'نورة سعد', 'سلطان فهد', 'ريم ناصر',
            'عبدالرحمن يوسف', 'هند سالم', 'ماجد تركي', 'لمى وليد', 'سعود حمد', 'جنى عمر',
            'طلال راشد', 'دانة فيصل', 'يزيد محمد', 'شهد عادل'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $rows[] = [
                'name'       => $this->pick($names),
                'email'      => null,
                'phone'      => $this->phone(900000 + $i),
                'is_active'  => rand(0, 20) > 0,           // ~5% deactivated
                'created_at' => $this->at($this->dayAgo()), // recency-biased growth
                'updated_at' => $this->now,
            ];
        }
        $this->bulk('users', $rows);
    }

    private function seedClinicStatsYear(): void
    {
        foreach ($this->ids('clinics') as $cid) {
            // Skip clinics already carrying a near-full year of stats.
            if (DB::table('clinic_stats')->where('clinic_id', $cid)->count() >= $this->year - 5) {
                continue;
            }
            $rows = [];
            for ($d = 0; $d < $this->year; $d++) {
                $date = $this->now->copy()->subDays($d);
                $boost = in_array((int) $date->dayOfWeek, [4, 5], true) ? 1.3 : 1.0;
                // Gentle upward growth toward the present (older days a bit quieter).
                $growth = 0.6 + 0.4 * (($this->year - $d) / $this->year);
                $views = max(1, (int) round(rand(20, 220) * $boost * $growth));
                $appearances = $views + rand(40, 400);
                $bookings = intdiv($views, rand(15, 30)) + rand(0, 2);
                $quotes = intdiv($views, rand(25, 60)) + rand(0, 1);
                $rows[] = [
                    'clinic_id'            => $cid,
                    'date'                 => $date->toDateString(),
                    'search_appearances'   => $appearances,
                    'page_views'           => $views,
                    'bookings_count'       => $bookings,
                    'quote_requests_count' => $quotes,
                    'whatsapp_clicks'      => (int) round($views * (rand(6, 16) / 100)),
                    'call_clicks'          => (int) round($views * (rand(3, 10) / 100)),
                    'directions_clicks'    => (int) round($views * (rand(4, 12) / 100)),
                    'booking_clicks'       => $bookings + rand(0, 6),
                    'created_at'           => $this->now,
                    'updated_at'           => $this->now,
                ];
            }
            DB::table('clinic_stats')->upsert(
                $rows,
                ['clinic_id', 'date'],
                ['search_appearances', 'page_views', 'bookings_count', 'quote_requests_count',
                 'whatsapp_clicks', 'call_clicks', 'directions_clicks', 'booking_clicks', 'updated_at']
            );
        }
    }

    private function seedBookings(int $target): void
    {
        $existing = DB::table('bookings')->count();
        if ($existing >= $target) {
            return;
        }
        $userIds = $this->ids('users');
        $servicesByClinic = DB::table('services')->get(['id', 'clinic_id'])
            ->groupBy('clinic_id')->map(fn ($g) => $g->pluck('id')->all());
        $clinicIds = $servicesByClinic->keys()->all();
        if (empty($clinicIds)) {
            return;
        }
        $names = ['عبدالله', 'سارة', 'محمد', 'منى', 'فيصل', 'جواهر', 'بدر', 'العنود', 'ناصر', 'ريم'];
        $sources = ['website', 'website', 'website', 'ai_assistant', 'phone'];

        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $cid = $this->pick($clinicIds);
            $svc = $this->pick($servicesByClinic[$cid]);
            $d = $this->dayAgo();
            // Older bookings are resolved; recent ones are still in the pipeline.
            $status = $d > 30
                ? $this->pick(['completed', 'completed', 'completed', 'no_show', 'cancelled', 'appointment_set'])
                : $this->pick(['new', 'new', 'contacted', 'appointment_set', 'completed']);
            $rows[] = [
                'clinic_id'      => $cid,
                'user_id'        => rand(0, 1) ? $this->pick($userIds) : null,
                'service_id'     => $svc,
                'customer_name'  => $this->pick($names),
                'customer_phone' => $this->phone(1100000 + $i),
                'notes'          => rand(0, 2) === 0 ? 'أرغب بموعد صباحي.' : null,
                'status'         => $status,
                'clinic_notes'   => in_array($status, ['completed', 'no_show'], true) ? 'تمت المتابعة.' : null,
                'appointment_at' => in_array($status, ['appointment_set', 'completed'], true)
                    ? $this->now->copy()->subDays($d)->addDays(rand(1, 7))->toDateTimeString() : null,
                'source'         => $this->pick($sources),
                'created_at'     => $this->at($d),
                'updated_at'     => $this->now,
            ];
        }
        $this->bulk('bookings', $rows);
    }

    private function seedQuotes(int $target): void
    {
        $existing = DB::table('price_quote_requests')->whereNotNull('clinic_id')->count();
        if ($existing >= $target) {
            return;
        }
        $clinicIds = $this->ids('clinics');
        $userIds = $this->ids('users');
        $services = ['زراعة أسنان', 'عملية ليزك', 'جلسات تنحيف', 'تقويم شفاف', 'عملية تجميل', 'تبييض أسنان', 'حقن فيلر'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $d = $this->dayAgo();
            $st = $d > 20 ? $this->pick(['replied', 'replied', 'closed']) : $this->pick(['new', 'new', 'replied']);
            $rows[] = [
                'clinic_id'      => $this->pick($clinicIds),
                'user_id'        => rand(0, 1) ? $this->pick($userIds) : null,
                'customer_name'  => 'مستفسر ' . ($i + 1),
                'customer_phone' => $this->phone(1300000 + $i),
                'service_name'   => $this->pick($services),
                'description'    => 'أرجو تزويدي بالسعر التقريبي والمدة وأقرب موعد متاح.',
                'status'         => $st,
                'clinic_reply'   => $st === 'new' ? null : 'السعر يبدأ من ' . (rand(5, 50) * 100) . ' ريال حسب الحالة.',
                'created_at'     => $this->at($d),
                'updated_at'     => $this->now,
            ];
        }
        $this->bulk('price_quote_requests', $rows);
    }

    private function seedBroadcastQuotes(int $target): void
    {
        $existing = DB::table('price_quote_requests')->whereNull('clinic_id')->count();
        if ($existing >= $target) {
            return;
        }
        $cityIds = collect($this->ids('cities'));
        $userIds = $this->ids('users');
        $services = ['زراعة أسنان', 'عملية ليزك', 'جلسات تنحيف', 'تقويم شفاف', 'باقة عناية شاملة'];
        $clinicsByCity = DB::table('clinics')->where('status', 'active')->get(['id', 'city_id'])->groupBy('city_id');

        for ($i = $existing; $i < $target; $i++) {
            $cities = $cityIds->shuffle()->take(rand(1, 3))->values();
            $d = $this->dayAgo();
            $reqId = DB::table('price_quote_requests')->insertGetId([
                'clinic_id'      => null,
                'user_id'        => $this->pick($userIds),
                'customer_name'  => 'طالب عرض ' . ($i + 1),
                'customer_phone' => $this->phone(1600000 + $i),
                'service_name'   => $this->pick($services),
                'description'    => 'أرغب بمعرفة السعر التقريبي والمدة وأقرب موعد متاح، مع تفاصيل الباقة إن وُجدت.',
                'status'         => $d > 15 ? 'replied' : $this->pick(['new', 'replied']),
                'created_at'     => $this->at($d),
                'updated_at'     => $this->now,
            ]);

            foreach ($cities as $cid) {
                DB::table('price_quote_request_city')->insertOrIgnore([
                    'price_quote_request_id' => $reqId,
                    'city_id'                => $cid,
                ]);
            }

            $clinics = collect();
            foreach ($cities as $cid) {
                $clinics = $clinics->merge($clinicsByCity[$cid] ?? collect());
            }
            foreach ($clinics->shuffle()->take(rand(1, 4))->values() as $idx => $clinic) {
                DB::table('price_quote_replies')->insertOrIgnore([
                    'price_quote_request_id' => $reqId,
                    'clinic_id'              => $clinic->id,
                    'body'                   => 'يسعدنا خدمتك. السعر يبدأ من ' . (rand(5, 50) * 100) . ' ريال حسب الحالة، ويمكن تحديد موعد خلال 48 ساعة.',
                    'price'                  => rand(5, 50) * 100,
                    'is_public'              => $idx === 0,
                    'created_at'             => $this->at(max(0, $d - rand(0, 2))),
                    'updated_at'             => $this->now,
                ]);
            }
        }
    }

    private function seedReviews(int $target): void
    {
        $existing = DB::table('google_reviews')->count();
        if ($existing >= $target) {
            return;
        }
        $clinicIds = $this->ids('clinics');
        $names = ['زائر Google', 'مريض راضٍ', 'عميل', 'مستخدم خرائط', 'أبو محمد', 'أم سارة'];
        $texts = ['خدمة ممتازة وطاقم محترف.', 'تجربة جيدة بشكل عام.', 'مواعيد منضبطة ونظافة عالية.',
            'أنصح بالتعامل معهم.', 'الأسعار مناسبة والجودة عالية.', 'استقبال راقٍ ومتابعة بعد الزيارة.'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $rows[] = [
                'clinic_id'        => $this->pick($clinicIds),
                'reviewer_name'    => $this->pick($names),
                'rating'           => $this->pick([5, 5, 5, 4, 4, 3]), // skewed positive
                'review_text'      => $this->pick($texts),
                'google_review_id' => 'gr_' . Str::random(14),
                'reviewed_at'      => $this->at($this->dayAgo()),
                'is_visible'       => true,
                'created_at'       => $this->now,
                'updated_at'       => $this->now,
            ];
        }
        $this->bulk('google_reviews', $rows);
    }

    private function seedSubscriptionHistory(): void
    {
        $adminIds = $this->ids('admins');
        // Per-clinic idempotency: only build history for clinics that have none yet.
        $alreadyHave = DB::table('subscriptions')->distinct()->pluck('clinic_id')->flip();
        $clinics = DB::table('clinics')->whereNotNull('subscription_type')->get(['id', 'subscription_type']);
        $rows = [];
        foreach ($clinics as $clinic) {
            if ($alreadyHave->has($clinic->id)) {
                continue;
            }
            $type = $clinic->subscription_type ?: 'basic';
            $amount = $type === 'premium' ? 400 : 300;
            // Up to four consecutive 90-day terms across the year (renewal history).
            for ($q = 3; $q >= 0; $q--) {
                $start = $this->now->copy()->subDays(($q + 1) * 90 - rand(0, 4))->startOfDay();
                $end = $start->copy()->addDays(90);
                $rows[] = [
                    'clinic_id'           => $clinic->id,
                    'type'                => $type,
                    'amount'              => $amount,
                    'starts_at'           => $start->toDateTimeString(),
                    'ends_at'             => $end->toDateTimeString(),
                    'status'              => $end->isFuture() ? 'active' : 'expired',
                    'created_by_admin_id' => $this->pick($adminIds),
                    'notes'               => $q === 0 ? null : 'تجديد ربع سنوي.',
                    'created_at'          => $start->toDateTimeString(),
                    'updated_at'          => $this->now,
                ];
            }
        }
        $this->bulk('subscriptions', $rows);
    }

    private function seedAiConversations(int $target): void
    {
        $existing = DB::table('ai_conversations')->count();
        if ($existing >= $target) {
            return;
        }
        $clinicIds = $this->ids('clinics');
        $types = ['chat', 'chat', 'article_generation', 'excel_analysis'];
        $titles = ['استفسار عن خدمة', 'توليد مقال توعوي', 'تحليل ملف أسعار', 'مقارنة عيادات', 'صياغة عرض'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $rows[] = [
                'clinic_id' => $this->pick($clinicIds),
                'title'     => $this->pick($titles) . ' ' . ($i + 1),
                'type'      => $this->pick($types),
                'messages'  => json_encode([
                    ['role' => 'user', 'content' => 'كيف أكتب مقالاً عن صحة الأسنان؟'],
                    ['role' => 'assistant', 'content' => 'يمكنك البدء بمقدمة بسيطة ثم نصائح عملية...'],
                ], JSON_UNESCAPED_UNICODE),
                'metadata'  => null,
                'created_at' => $this->at($this->dayAgo()),
                'updated_at' => $this->now,
            ];
        }
        $this->bulk('ai_conversations', $rows);
    }

    private function seedArticles(int $target): void
    {
        $existing = DB::table('articles')->count();
        if ($existing >= $target) {
            return;
        }
        $clinicIds = $this->ids('clinics');
        $titles = ['نصائح للعناية بالأسنان', 'كيف تحافظ على بشرتك', 'أهمية الفحص الدوري للعيون',
            'التغذية السليمة للأطفال', 'الوقاية من أمراض القلب', 'متى تزور طبيب العظام',
            'علامات تستوجب زيارة الطبيب', 'فوائد العلاج الطبيعي', 'العناية بعد عمليات التجميل'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $d = $this->dayAgo();
            $published = rand(0, 4) > 0; // ~80% published
            $rows[] = [
                'clinic_id'        => $this->pick($clinicIds),
                'title'            => $this->pick($titles) . ' (' . ($i + 1) . ')',
                'slug'             => Str::slug('article-' . $i . '-' . Str::random(6)),
                'body'             => '<p>محتوى المقال الطبي التوعوي. يقدم معلومات مفيدة للقارئ حول الموضوع بأسلوب مبسط وواضح.</p>',
                'meta_description' => 'ملخص قصير للمقال الطبي التوعوي.',
                'tags'             => json_encode(['صحة', 'توعية']),
                'is_published'     => $published,
                'published_at'     => $published ? $this->at($d) : null,
                // Older articles accumulate more views.
                'views_count'      => $published ? (int) round(rand(20, 4000) * (1 + $d / $this->year)) : 0,
                'ai_generated'     => rand(0, 1) === 1,
                'created_at'       => $this->at($d),
                'updated_at'       => $this->now,
            ];
        }
        $this->bulk('articles', $rows);
    }

    private function seedComplaints(int $target): void
    {
        $existing = DB::table('complaints')->count();
        if ($existing >= $target) {
            return;
        }
        $clinicIds = $this->ids('clinics');
        $userIds = $this->ids('users');
        $adminIds = $this->ids('admins');
        $types = ['quality', 'pricing', 'misleading_info', 'other'];
        $priorities = ['low', 'medium', 'high'];
        $sources = ['customer', 'customer', 'customer', 'clinic', 'admin'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $d = $this->dayAgo();
            $st = $d > 20 ? $this->pick(['resolved', 'resolved', 'rejected', 'in_review']) : $this->pick(['new', 'new', 'in_review']);
            $source = $this->pick($sources);
            $rows[] = [
                'reference_code'    => 'CMP-' . strtoupper(Str::random(8)),
                'clinic_id'         => $this->pick($clinicIds),
                'user_id'           => $source === 'customer' ? $this->pick($userIds) : null,
                'booking_id'        => null,
                'source'            => $source,
                'customer_name'     => $source === 'clinic' ? ('مجمع مشتكٍ ' . ($i + 1)) : ('مشتكٍ ' . ($i + 1)),
                'customer_phone'    => $this->phone(1800000 + $i),
                'customer_email'    => rand(0, 1) ? 'c' . $i . '@mail.sa' : null,
                'type'              => $this->pick($types),
                'status'            => $st,
                'priority'          => $this->pick($priorities),
                'subject'           => 'شكوى بخصوص الخدمة',
                'description'       => 'تفاصيل الشكوى المقدمة حول التجربة.',
                'admin_notes'       => in_array($st, ['in_review', 'resolved', 'rejected'], true) ? 'تمت المراجعة.' : null,
                'resolution'        => $st === 'resolved' ? 'تم حل المشكلة والتواصل مع العميل.' : null,
                'assigned_admin_id' => $st === 'new' ? null : $this->pick($adminIds),
                'resolved_at'       => $st === 'resolved' ? $this->at(max(0, $d - rand(1, 5))) : null,
                'clinic_notified'   => rand(0, 1) === 1,
                'created_at'        => $this->at($d),
                'updated_at'        => $this->now,
            ];
        }
        $this->bulk('complaints', $rows);
    }

    private function seedFavorites(int $target): void
    {
        $existing = DB::table('favorites')->count();
        if ($existing >= $target) {
            return;
        }
        $userIds = $this->ids('users');
        $clinicIds = $this->ids('clinics');
        if (empty($userIds) || empty($clinicIds)) {
            return;
        }
        $made = $existing;
        $guard = 0;
        while ($made < $target && $guard < 30000) {
            $guard++;
            $made += DB::table('favorites')->insertOrIgnore([
                'user_id'    => $this->pick($userIds),
                'clinic_id'  => $this->pick($clinicIds),
                'created_at' => $this->at($this->dayAgo()),
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedAuditLogs(int $target): void
    {
        $existing = DB::table('audit_logs')->count();
        if ($existing >= $target) {
            return;
        }
        $admins = DB::table('admins')->get(['id', 'name']);
        if ($admins->isEmpty()) {
            return;
        }
        $actions = ['clinic.approved', 'clinic.rejected', 'clinic.suspended', 'clinic.updated',
            'sales_lead.converted', 'subscription.created', 'subscription.renewed', 'city.updated', 'complaint.resolved'];
        $clinicIds = $this->ids('clinics');
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $admin = $admins->random();
            $rows[] = [
                'admin_id'   => $admin->id,
                'admin_name' => $admin->name,
                'action'     => $this->pick($actions),
                'model_type' => 'App\\Models\\Clinic',
                'model_id'   => $this->pick($clinicIds),
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'active']),
                'ip_address' => '127.0.0.' . rand(1, 254),
                'user_agent' => 'Mozilla/5.0 (YearOfUsageSeeder)',
                'created_at' => $this->at($this->dayAgo()),
                'updated_at' => $this->now,
            ];
        }
        $this->bulk('audit_logs', $rows);
    }

    private function seedNotifications(int $target): void
    {
        $existing = DB::table('platform_notifications')->count();
        if ($existing >= $target) {
            return;
        }
        $adminIds = $this->ids('admins');
        $clinicIds = $this->ids('clinics');
        $types = ['new_booking', 'new_complaint', 'new_price_quote', 'lead_converted', 'subscription_expiring'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $toAdmin = rand(0, 1) === 1;
            $d = $this->dayAgo(120);
            $rows[] = [
                'notifiable_type' => $toAdmin ? 'App\\Models\\Admin' : 'App\\Models\\Clinic',
                'notifiable_id'   => $toAdmin ? $this->pick($adminIds) : $this->pick($clinicIds),
                'type'            => $this->pick($types),
                'icon'            => 'heroicon-o-bell',
                'url'             => $toAdmin ? '/app/admin/dashboard' : '/app/clinic/dashboard',
                'priority'        => $this->pick($priorities),
                'title'           => 'إشعار ' . ($i + 1),
                'body'            => 'تحديث جديد على المنصة.',
                'data'            => json_encode(['demo' => true]),
                'read_at'         => $d > 7 ? $this->at(max(0, $d - 1)) : null, // older ones read
                'created_at'      => $this->at($d),
                'updated_at'      => $this->now,
            ];
        }
        $this->bulk('platform_notifications', $rows);
    }

    private function seedSalesLeads(int $target): void
    {
        $existing = DB::table('sales_leads')->count();
        if ($existing >= $target) {
            return;
        }
        $cityIds = $this->ids('cities');
        $adminIds = $this->ids('admins');
        $statuses = ['new', 'contacted', 'interested', 'negotiating', 'converted', 'lost'];
        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $d = $this->dayAgo();
            $rows[] = [
                'clinic_name'       => 'مجمع محتمل ' . ($i + 1),
                'contact_name'      => 'مسؤول ' . ($i + 1),
                'phone'             => $this->phone(1900000 + $i),
                'email'             => 'lead' . ($i + 1) . '@prospect.sa',
                'license_number'    => 'LIC-Y' . (10000 + $i),
                'city_id'           => $this->pick($cityIds),
                'district'          => 'حي ' . $this->pick(['الورود', 'النزهة', 'الياسمين', 'الربيع']),
                'address'           => 'شارع رقم ' . rand(1, 90),
                'status'            => $d > 30 ? $this->pick(['converted', 'lost', 'negotiating']) : $this->pick($statuses),
                'notes'             => 'تم التواصل المبدئي.',
                'sales_notes'       => 'مهتم بالباقة المميزة.',
                'assigned_to'       => $this->pick($adminIds),
                'next_follow_up_at' => $this->now->copy()->addDays(rand(-5, 15))->toDateTimeString(),
                'last_contact_at'   => $this->at(max(0, $d - rand(0, 5))),
                'created_at'        => $this->at($d),
                'updated_at'        => $this->now,
            ];
        }
        $this->bulk('sales_leads', $rows);
    }
}
