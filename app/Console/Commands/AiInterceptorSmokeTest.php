<?php

namespace App\Console\Commands;

use App\Models\AiAssistantLog;
use App\Models\AiRestriction;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Services\AiAssistantInterceptor;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * One-shot dev tool — exercises the interceptor end-to-end so we can
 * confirm Phase 1 is wired correctly without spinning up the browser.
 */
class AiInterceptorSmokeTest extends Command
{
    protected $signature = 'ai:smoke-test';
    protected $description = 'Smoke-test the AI Center interceptor.';

    public function handle(AiAssistantInterceptor $interceptor): int
    {
        $req = Request::create('/test', 'POST');
        try { $req->setLaravelSession(app('session')->driver()); } catch (\Throwable $e) {}

        AiRestriction::query()->delete();
        Cache::forget(AiRestriction::CACHE_KEY);

        $this->info('── Test 1: banned topic ─────────────────────');
        $banned = AiRestriction::create([
            'type'              => AiRestriction::TYPE_BANNED_TOPIC,
            'value'             => 'وصفة دواء',
            'response_override' => 'لا أستطيع وصف الأدوية، راجع طبيبك المختص.',
            'is_active'         => true,
        ]);
        $out = $interceptor->handle('أريد وصفة دواء للصداع', null, $req);
        $this->line("  kind: {$out['kind']}");
        $this->line("  reply: {$out['reply']}");

        $this->info(PHP_EOL . '── Test 2: emergency keyword ───────────────');
        $emergency = AiRestriction::create([
            'type'      => AiRestriction::TYPE_EMERGENCY_KEYWORD,
            'value'     => 'سكتة قلبية',
            'is_active' => true,
        ]);
        $out = $interceptor->handle('أعتقد أنني أتعرض لسكتة قلبية', null, $req);
        $this->line("  kind: {$out['kind']}");
        $this->line('  reply: ' . mb_substr($out['reply'], 0, 120));

        $this->info(PHP_EOL . '── Test 3: normal query (logging + stat bumps) ──');
        $beforeLogs = AiAssistantLog::count();
        $clinic = Clinic::publiclyVisible()->first();
        $beforeStats = (int) ClinicStat::where('clinic_id', $clinic?->id)
            ->where('date', today())->value('search_appearances');
        $out = $interceptor->handle('أريد عيادة أسنان في الرياض', null, $req);
        $afterLogs = AiAssistantLog::count();
        $this->line("  kind: {$out['kind']}");
        $this->line("  log rows before: {$beforeLogs}, after: {$afterLogs}");
        $this->line('  surfaced clinic ids: ' . implode(',', $out['clinics']->pluck('id')->all()));
        $latestLog = AiAssistantLog::latest('id')->first();
        $this->line("  latest log kind={$latestLog?->kind}, clinics_count=" . ($latestLog?->clinics()->count() ?? 0));

        $this->info(PHP_EOL . '── Counts ──────────────────────────────────');
        $this->line('  total logs: ' . AiAssistantLog::count());
        $this->line('  blocked logs: ' . AiAssistantLog::where('was_blocked', true)->count());
        $this->line('  emergency logs: ' . AiAssistantLog::where('was_emergency', true)->count());

        $banned->delete();
        $emergency->delete();
        $this->info(PHP_EOL . '✓ Done.');

        return self::SUCCESS;
    }
}
