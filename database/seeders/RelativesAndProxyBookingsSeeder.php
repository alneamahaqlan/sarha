<?php

namespace Database\Seeders;

use App\Models\Relative;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gives a handful of existing users 1-2 saved relatives and converts
 * some of their existing bookings into "by proxy" bookings so the
 * 3 filter tabs (all / self / relatives) and the clinic-side
 * "بالنيابة" badge all have real data to render on a fresh seed.
 *
 * Idempotent: skips users that already have any relative row, and only
 * mutates bookings that don't yet have a relative_id assigned.
 */
class RelativesAndProxyBookingsSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();
        if (empty($userIds)) {
            $this->command?->warn('RelativesAndProxyBookingsSeeder: no users yet — skipped.');
            return;
        }

        // Spread relatives across the first ~12 users (enough to fill the
        // filter tabs without seeding hundreds of rows we'll never see).
        $targets = array_slice($userIds, 0, 12);
        $relativesByUser = [];
        $now = now();

        // Pre-canned realistic combos so the demo data reads naturally.
        $bank = [
            ['type' => 'father',    'name' => 'والدي عبدالعزيز'],
            ['type' => 'mother',    'name' => 'والدتي ريم'],
            ['type' => 'son',       'name' => 'ابني تركي'],
            ['type' => 'daughter',  'name' => 'ابنتي رهف'],
            ['type' => 'wife',      'name' => 'زوجتي سارة'],
            ['type' => 'brother',   'name' => 'أخي ناصر'],
            ['type' => 'sister',    'name' => 'أختي منيرة'],
            ['type' => 'grandmother','name' => 'جدتي نورة'],
        ];

        foreach ($targets as $i => $uid) {
            if (DB::table('relatives')->where('user_id', $uid)->exists()) {
                continue;
            }
            $count = ($i % 3) + 1; // 1, 2, or 3 relatives per user
            $picks = collect($bank)->shuffle()->take($count)->values();
            $relativesByUser[$uid] = [];
            foreach ($picks as $j => $pick) {
                $id = DB::table('relatives')->insertGetId([
                    'user_id'             => $uid,
                    'name'                => $pick['name'],
                    'relationship_type'   => $pick['type'],
                    'relationship_label'  => null,
                    'phone'               => '055' . str_pad((string) ($uid * 10 + $j), 7, '0', STR_PAD_LEFT),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
                $relativesByUser[$uid][] = [
                    'id'    => $id,
                    'name'  => $pick['name'],
                    'phone' => '055' . str_pad((string) ($uid * 10 + $j), 7, '0', STR_PAD_LEFT),
                    'type'  => $pick['type'],
                ];
            }
        }

        // For each user we just gave relatives to, convert ~half of their
        // existing bookings into by-proxy ones so the "حجوزات أقاربي" tab
        // isn't empty. We mutate customer_name / customer_phone so the
        // clinic sees the relative (matches the live flow).
        $converted = 0;
        foreach ($relativesByUser as $uid => $rels) {
            $bookings = DB::table('bookings')
                ->where('user_id', $uid)
                ->whereNull('relative_id')
                ->orderByDesc('id')
                ->limit(max(1, (int) ceil(count($rels) * 1.5)))
                ->get(['id']);

            foreach ($bookings as $b) {
                $rel = $rels[array_rand($rels)];
                DB::table('bookings')->where('id', $b->id)->update([
                    'booker_user_id' => $uid,
                    'relative_id'    => $rel['id'],
                    'customer_name'  => $rel['name'],
                    'customer_phone' => $rel['phone'],
                    'updated_at'     => $now,
                ]);
                $converted++;
            }
        }

        $totalRelatives = array_sum(array_map('count', $relativesByUser));
        $this->command?->info("RelativesAndProxyBookingsSeeder: created {$totalRelatives} relatives across "
            . count($relativesByUser) . " users, converted {$converted} bookings to by-proxy.");
    }
}
