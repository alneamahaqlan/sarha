<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserActivityEvent;
use App\Models\UserVisitSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives the super-admin "comprehensive user profile" screen a
 * realistic test bed: 5 behavioural archetypes × 4 users each = 20
 * users with distinguishable visit histories + event timelines so
 * every panel (engagement, decision-stage, risk flags, hours chart)
 * has at least one demo case to render.
 *
 *  - active_serious: many sessions + searches that culminate in
 *    bookings, leans on a single city/category. Engagement = active,
 *    decision_stage = near_decision.
 *  - explorer:       lots of searches across categories, no bookings.
 *    Engagement = active, triggers `search_no_booking` risk flag.
 *  - idle:           one login a month ago, nothing since. Engagement
 *    = idle, drives the "no activity" empty-state branches.
 *  - complainer:     normal activity + 4+ complaints in the last
 *    month → triggers `repeated_complaints` risk flag.
 *  - geo_spread:     multiple visit sessions in the last 24h from
 *    very different IPs → triggers `suspicious_activity` flag.
 *
 * Idempotent: skips users that already have any user_activity_event
 * row so re-running the chain converges instead of inflating counts.
 */
class UserActivityPatternsSeeder extends Seeder
{
    public function run(): void
    {
        $allUsers = User::orderBy('id')->limit(60)->get();
        if ($allUsers->count() < 20) {
            $this->command?->warn('UserActivityPatternsSeeder: need ≥20 users — skipped.');
            return;
        }

        $cityIds = DB::table('cities')->pluck('id')->all();
        $catIds  = DB::table('categories')->pluck('id')->all();
        $clinicIds = DB::table('clinics')->pluck('id')->all();
        if (empty($cityIds) || empty($catIds) || empty($clinicIds)) {
            $this->command?->warn('UserActivityPatternsSeeder: lookups missing — skipped.');
            return;
        }

        $patterns = [
            'active_serious' => $allUsers->slice(0, 4),
            'explorer'       => $allUsers->slice(4, 4),
            'idle'           => $allUsers->slice(8, 4),
            'complainer'     => $allUsers->slice(12, 4),
            'geo_spread'     => $allUsers->slice(16, 4),
        ];

        foreach ($patterns as $pattern => $users) {
            foreach ($users as $u) {
                if (UserActivityEvent::where('user_id', $u->id)->exists()) continue;
                $this->seedPattern($pattern, $u, $cityIds, $catIds, $clinicIds);
            }
        }

        $this->command?->info('UserActivityPatternsSeeder: seeded 5 behavioural archetypes × 4 users.');
    }

    private function seedPattern(string $pattern, User $user, array $cityIds, array $catIds, array $clinicIds): void
    {
        $events = [];
        $visits = [];
        $now = now();

        // Pick a stable preferred city + category for the archetype so
        // the "preferred city/category" insight surfaces something
        // deterministic on each user.
        $favCity = $cityIds[$user->id % count($cityIds)];
        $favCat  = $catIds[$user->id % count($catIds)];

        switch ($pattern) {
            case 'active_serious':
                // 12 visits in the last 30 days, each with searches,
                // clinic views, an action click, and one booking
                // burst near the end.
                for ($i = 0; $i < 12; $i++) {
                    $start = $now->copy()->subDays($i * 2)->subMinutes(rand(0, 600));
                    $end = $start->copy()->addMinutes(rand(8, 35));
                    $visits[] = $this->visit($user, $start, $end);
                    $events[] = $this->event($user, UserActivityEvent::TYPE_LOGIN, $start);
                    $events[] = $this->event($user, UserActivityEvent::TYPE_SEARCH, $start->copy()->addMinutes(2),
                        ['q' => 'تنظيف الأسنان', 'city_id' => $favCity, 'category_id' => $favCat, 'results_count' => 12]);
                    $events[] = $this->event($user, UserActivityEvent::TYPE_CLINIC_VIEW, $start->copy()->addMinutes(5),
                        ['slug' => 'demo'], relatedClinicId: $clinicIds[array_rand($clinicIds)]);
                    if ($i % 3 === 0) {
                        $events[] = $this->event($user, UserActivityEvent::TYPE_ACTION_CLICK, $start->copy()->addMinutes(7),
                            ['button' => ['whatsapp', 'call', 'directions'][rand(0, 2)]],
                            relatedClinicId: $clinicIds[array_rand($clinicIds)]);
                    }
                    $events[] = $this->event($user, UserActivityEvent::TYPE_LOGOUT, $end);
                }
                break;

            case 'explorer':
                // 25+ searches across categories. No action clicks.
                for ($i = 0; $i < 25; $i++) {
                    $at = $now->copy()->subDays(rand(0, 45))->subMinutes(rand(0, 1440));
                    $events[] = $this->event($user, UserActivityEvent::TYPE_SEARCH, $at, [
                        'q' => ['زراعة أسنان', 'بوتكس', 'فحص نظر', 'تقويم', 'تنظيف'][rand(0, 4)],
                        'city_id' => $cityIds[array_rand($cityIds)],
                        'category_id' => $catIds[array_rand($catIds)],
                        'results_count' => rand(3, 30),
                    ]);
                    if ($i % 4 === 0) {
                        $events[] = $this->event($user, UserActivityEvent::TYPE_FILTER, $at,
                            ['city_id' => $favCity, 'category_id' => $favCat]);
                    }
                }
                // 8 short visit sessions to make engagement = active.
                for ($i = 0; $i < 8; $i++) {
                    $start = $now->copy()->subDays($i)->subHours(rand(0, 6));
                    $visits[] = $this->visit($user, $start, $start->copy()->addMinutes(5));
                }
                break;

            case 'idle':
                // One login 35 days ago and nothing since.
                $at = $now->copy()->subDays(35);
                $events[] = $this->event($user, UserActivityEvent::TYPE_LOGIN, $at);
                $events[] = $this->event($user, UserActivityEvent::TYPE_SEARCH, $at->copy()->addMinutes(3), [
                    'q' => 'بحث قديم', 'city_id' => $favCity, 'category_id' => $favCat, 'results_count' => 1,
                ]);
                $events[] = $this->event($user, UserActivityEvent::TYPE_LOGOUT, $at->copy()->addMinutes(8));
                $visits[] = $this->visit($user, $at, $at->copy()->addMinutes(8));
                break;

            case 'complainer':
                // Normal activity + 4 complaints in the last 30 days
                // (writes directly to complaints table so the existing
                // tabs + risk flag both light up).
                for ($i = 0; $i < 4; $i++) {
                    DB::table('complaints')->insert([
                        'reference_code' => 'CMP-DEMO' . $user->id . $i,
                        'user_id'       => $user->id,
                        'source'        => 'customer',
                        'customer_name' => $user->name,
                        'customer_phone'=> $user->phone,
                        'type'          => 'quality',
                        'subject'       => "شكوى رقم " . ($i + 1),
                        'description'   => 'تفاصيل الشكوى التجريبية لاختبار علامات التنبيه.',
                        'status'        => ['new', 'in_review', 'resolved'][rand(0, 2)],
                        'priority'      => 'medium',
                        'created_at'    => $now->copy()->subDays(rand(1, 25))->toDateTimeString(),
                        'updated_at'    => $now,
                    ]);
                }
                for ($i = 0; $i < 5; $i++) {
                    $start = $now->copy()->subDays($i * 3);
                    $visits[] = $this->visit($user, $start, $start->copy()->addMinutes(10));
                    $events[] = $this->event($user, UserActivityEvent::TYPE_LOGIN, $start);
                    $events[] = $this->event($user, UserActivityEvent::TYPE_CLINIC_VIEW, $start->copy()->addMinutes(2),
                        ['slug' => 'demo'], relatedClinicId: $clinicIds[array_rand($clinicIds)]);
                }
                break;

            case 'geo_spread':
                // 5 visit sessions in the last 24h, each from a wildly
                // different IP. Triggers the "suspicious_activity" flag.
                $ips = ['10.0.0.1', '203.45.66.7', '88.12.43.99', '195.16.250.4', '116.202.10.5'];
                foreach ($ips as $idx => $ip) {
                    $start = $now->copy()->subHours(20 - $idx * 4);
                    $end = $start->copy()->addMinutes(7);
                    $visits[] = $this->visit($user, $start, $end, $ip);
                    $events[] = $this->event($user, UserActivityEvent::TYPE_LOGIN, $start);
                    $events[] = $this->event($user, UserActivityEvent::TYPE_CLINIC_VIEW, $start->copy()->addMinutes(2),
                        ['slug' => 'demo'], relatedClinicId: $clinicIds[array_rand($clinicIds)]);
                }
                break;
        }

        if (! empty($events)) {
            foreach (array_chunk($events, 200) as $chunk) {
                DB::table('user_activity_events')->insert($chunk);
            }
        }
        if (! empty($visits)) {
            DB::table('user_visit_sessions')->insert($visits);
        }
    }

    private function event(User $u, string $type, $at, ?array $details = null, ?int $relatedClinicId = null): array
    {
        return [
            'user_id'           => $u->id,
            'type'              => $type,
            'title'             => null,
            'details'           => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'related_clinic_id' => $relatedClinicId,
            'related_url'       => null,
            'visit_session_id'  => null,
            'ip'                => '127.0.0.1',
            'user_agent'        => 'Mozilla/5.0 SeederBot',
            'occurred_at'       => $at,
            'created_at'        => $at,
            'updated_at'        => $at,
        ];
    }

    private function visit(User $u, $start, $end, ?string $ip = null): array
    {
        return [
            'user_id'          => $u->id,
            'started_at'       => $start,
            'last_activity_at' => $end,
            'ended_at'         => $end,
            'ip'               => $ip ?? '127.0.0.1',
            'user_agent'       => 'Mozilla/5.0 SeederBot',
            'created_at'       => $start,
            'updated_at'       => $end,
        ];
    }
}
