<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Clinic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates ~400 fake AI assistant conversations across the last 90 days
 * so Phase 2's stats / conversation viewer has realistic data the moment
 * it ships — no waiting for organic traffic to fill the chart.
 *
 * Mixes:
 *  - guests (visitor_id only) vs. registered users
 *  - matched / freeform / no_match outcomes
 *  - ~5% blocked by restrictions, ~2% emergency redirects
 *  - daytime peak distribution (more rows 9am–9pm, fewer at 3am)
 *  - per-row attached clinics + categories so the pivot stats look real
 */
class AiAssistantLogsSeeder extends Seeder
{
    private const TOTAL = 400;
    private const DAYS  = 90;

    private array $clinics    = [];
    private array $categories = [];
    private array $userIds    = [];

    public function run(): void
    {
        $this->clinics    = Clinic::query()->limit(200)->pluck('id')->all();
        $this->categories = Category::query()->limit(50)->pluck('id')->all();
        $this->userIds    = User::query()->limit(100)->pluck('id')->all();

        if (empty($this->clinics) || empty($this->categories)) {
            $this->command?->warn('AiAssistantLogsSeeder: no clinics/categories yet — skipped.');
            return;
        }

        $logRows         = [];
        $logClinicRows   = [];
        $logCategoryRows = [];
        // We use a pseudo-id counter and then `getLastInsertId` math
        // afterwards so we can build pivots in bulk without a per-row
        // insertGetId round-trip × 400.
        $logCount = 0;

        foreach ($this->generateTimestamps(self::TOTAL) as $createdAt) {
            $kind = $this->pickKind();
            [$wasBlocked, $wasEmergency] = $this->outcomeFlags($kind);
            $isGuest = rand(0, 99) < 60; // 60% guests

            $query = $this->pickQuery($kind);
            $reply = $this->pickReply($kind, $query);

            // For matched/details/freeform — surface 1–3 clinics and 1–2
            // categories. For rejected/emergency — none surface.
            $surfacedClinics    = ($wasBlocked || $wasEmergency) ? [] : $this->pickN($this->clinics, rand(1, 3));
            $surfacedCategories = ($wasBlocked || $wasEmergency) ? [] : $this->pickN($this->categories, rand(1, 2));

            $logRows[] = [
                'user_id'                   => $isGuest ? null : $this->pick($this->userIds),
                'visitor_id'                => $isGuest ? Str::uuid()->toString() : null,
                'guard'                     => $isGuest ? null : 'web',
                'query'                     => $query,
                'reply'                     => $reply,
                'kind'                      => $kind,
                'provider'                  => $this->pickProvider(),
                'model'                     => null,
                'tokens_in'                 => mb_strlen($query),
                'tokens_out'                => mb_strlen($reply),
                'locale'                    => rand(0, 9) < 9 ? 'ar' : 'en',
                'blocked_by_restriction_id' => null,
                'was_emergency'             => $wasEmergency,
                'was_blocked'               => $wasBlocked,
                'stats_bumped'              => ! empty($surfacedClinics),
                'created_at'                => $createdAt->toDateTimeString(),
                'updated_at'                => $createdAt->toDateTimeString(),
            ];

            // Defer pivot rows until after the bulk insert resolves real
            // ids — we use a sentinel offset captured below.
            $logCount++;
            $logClinicRows[$logCount]   = $surfacedClinics;
            $logCategoryRows[$logCount] = $surfacedCategories;
        }

        // Bulk insert logs, then walk back the auto-assigned ids.
        $firstId = (int) DB::table('ai_assistant_logs')->max('id') + 1;
        foreach (array_chunk($logRows, 500) as $chunk) {
            DB::table('ai_assistant_logs')->insert($chunk);
        }

        $pivotClinicInserts   = [];
        $pivotCategoryInserts = [];
        $now = now();
        foreach ($logClinicRows as $offset => $clinicIds) {
            $logId = $firstId + $offset - 1;
            foreach ($clinicIds as $cid) {
                $pivotClinicInserts[] = ['log_id' => $logId, 'clinic_id' => $cid, 'created_at' => $now];
            }
        }
        foreach ($logCategoryRows as $offset => $categoryIds) {
            $logId = $firstId + $offset - 1;
            foreach ($categoryIds as $catId) {
                $pivotCategoryInserts[] = ['log_id' => $logId, 'category_id' => $catId, 'created_at' => $now];
            }
        }

        foreach (array_chunk($pivotClinicInserts, 1000) as $chunk) {
            DB::table('ai_assistant_log_clinics')->insert($chunk);
        }
        foreach (array_chunk($pivotCategoryInserts, 1000) as $chunk) {
            DB::table('ai_assistant_log_categories')->insert($chunk);
        }

        $this->command?->info('AiAssistantLogsSeeder: '.self::TOTAL.' fake conversations + pivots seeded.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return CarbonImmutable[] sorted desc */
    private function generateTimestamps(int $count): array
    {
        $now = CarbonImmutable::now();
        $stamps = [];
        for ($i = 0; $i < $count; $i++) {
            $dayAgo = rand(0, self::DAYS);
            // Day-of-week skew: lighter on Fridays + Saturdays.
            $candidate = $now->subDays($dayAgo);
            if (in_array($candidate->dayOfWeek, [5, 6], true) && rand(0, 99) < 40) {
                continue;
            }
            // Hour skew: gaussian-ish around 14:00.
            $hour = max(0, min(23, (int) round(14 + (mt_rand(-700, 700) / 100))));
            $minute = rand(0, 59);
            $stamps[] = $candidate->setTime($hour, $minute, rand(0, 59));
        }
        // Top up if the day-of-week skip dropped us under the target.
        while (count($stamps) < $count) {
            $stamps[] = $now->subDays(rand(0, self::DAYS))->setTime(rand(8, 22), rand(0, 59), rand(0, 59));
        }
        usort($stamps, fn ($a, $b) => $a <=> $b);
        return $stamps;
    }

    private function pickKind(): string
    {
        $r = rand(0, 99);
        if ($r < 2)  return 'emergency';        // 2%
        if ($r < 7)  return 'rejected';         // 5%
        if ($r < 12) return 'no_match';         // 5%
        if ($r < 25) return 'freeform';         // 13%
        if ($r < 55) return 'matched';          // 30%
        if ($r < 75) return 'details';          // 20%
        if ($r < 85) return 'follow_up';        // 10%
        if ($r < 95) return 'greeting';         // 10%
        return 'out_of_scope';                  // 5%
    }

    /** @return array{0:bool,1:bool} [was_blocked, was_emergency] */
    private function outcomeFlags(string $kind): array
    {
        return match ($kind) {
            'emergency' => [false, true],
            'rejected'  => [true,  false],
            default     => [false, false],
        };
    }

    private function pickProvider(): string
    {
        return ['gemini', 'openai', 'anthropic'][rand(0, 9) < 7 ? 0 : rand(1, 2)];
    }

    private function pickQuery(string $kind): string
    {
        $matched = [
            'أريد عيادة أسنان في الرياض',
            'مجمعات الجلدية في جدة',
            'دكتور عيون أطفال',
            'كشف باطنية بالقرب مني',
            'عيادة تجميل بأسعار جيدة',
            'متخصص في تقويم الأسنان',
        ];
        $freeform = [
            'كيف أحجز موعداً عبر المنصة؟',
            'ما الفرق بين باقة العائلة والباقة العادية؟',
            'هل يمكنني إلغاء الحجز؟',
        ];
        $emergency = [
            'عندي ألم شديد في الصدر وضيق تنفس',
            'نزيف لا يتوقف',
            'فقدت الوعي ثم استيقظت دائخ',
        ];
        $rejected = [
            'هل هذا الدواء يناسب حالتي؟',
            'ما الجرعة المناسبة من هذا الدواء؟',
            'هل أحتاج لعملية جراحية؟',
        ];
        return match ($kind) {
            'emergency' => $emergency[array_rand($emergency)],
            'rejected'  => $rejected[array_rand($rejected)],
            'freeform', 'follow_up', 'greeting' => $freeform[array_rand($freeform)],
            default     => $matched[array_rand($matched)],
        };
    }

    private function pickReply(string $kind, string $query): string
    {
        return match ($kind) {
            'emergency' => 'يبدو أنك بحاجة لمساعدة طبية فورية. الرجاء الاتصال بالإسعاف على 997 أو الذهاب لأقرب مستشفى.',
            'rejected'  => 'هذا الموضوع خارج نطاق مساعدتي. يمكنك التواصل مع المجمعات الطبية المناسبة للاستفسار.',
            'no_match'  => 'لم أجد نتائج مطابقة. جرّب توضيح المدينة أو التخصص.',
            'greeting'  => 'مرحباً، أنا سلمى، كيف أساعدك اليوم؟',
            default     => 'إليك أقرب المجمعات التي تطابق طلبك: ...',
        };
    }

    private function pick(array $arr)
    {
        return $arr[array_rand($arr)] ?? null;
    }

    private function pickN(array $arr, int $n): array
    {
        if (empty($arr)) return [];
        $n = min($n, count($arr));
        $keys = (array) array_rand($arr, $n);
        return array_values(array_intersect_key($arr, array_flip($keys)));
    }
}
