<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AiAssistantLogResource;
use App\Models\AiAssistantLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paginated browse + thread view of AI assistant interactions.
 *
 * The list collapses turns into conversations (one row per
 * conversation_id) so the admin sees ~hundreds, not thousands, of
 * entries. Each row shows the head-of-thread metadata + a count.
 *
 * `show($conversationId)` returns the full ordered thread for the modal,
 * with PII masking on by default — flip `?reveal=1` to override.
 */
class AiCenterConversationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Honor the global ai_pii_masking_enabled flag — when off, every
        // list row comes back unmasked even without ?reveal=1.
        $maskingEnabled = (bool) \App\Models\SystemSetting::get('ai_pii_masking_enabled', true);
        $reveal = $request->boolean('reveal') || ! $maskingEnabled;
        $perPage = (int) min(50, max(10, $request->integer('per_page', 25)));

        // One row per conversation_id — the row's id is the FIRST turn.
        // We aggregate the metadata in SQL so we don't load the full
        // thread for every list page.
        $sub = DB::table('ai_assistant_logs')
            ->whereNotNull('conversation_id')
            ->selectRaw('
                conversation_id,
                MIN(id) as first_log_id,
                MAX(id) as last_log_id,
                MIN(created_at) as started_at,
                MAX(created_at) as ended_at,
                COUNT(*) as turn_count,
                MAX(CASE WHEN was_blocked = 1 THEN 1 ELSE 0 END) as has_blocked,
                MAX(CASE WHEN was_emergency = 1 THEN 1 ELSE 0 END) as has_emergency,
                SUM(COALESCE(tokens_in, 0) + COALESCE(tokens_out, 0)) as total_tokens,
                MAX(user_id) as any_user_id,
                MAX(visitor_id) as any_visitor_id
            ')
            ->groupBy('conversation_id');

        // Apply filters at the SQL aggregate level so paging is correct.
        $query = DB::table(DB::raw("({$sub->toSql()}) as conv"))
            ->mergeBindings($sub)
            ->select('conv.*');

        if ($from = $request->date('from')) {
            $query->where('conv.started_at', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $query->where('conv.ended_at', '<=', $to);
        }
        if (($status = $request->string('status')->toString()) !== '') {
            if ($status === 'blocked')   { $query->where('conv.has_blocked', 1); }
            if ($status === 'emergency') { $query->where('conv.has_emergency', 1); }
            if ($status === 'normal')    { $query->where('conv.has_blocked', 0)->where('conv.has_emergency', 0); }
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('conv.any_user_id', $userId);
        }
        if ($clinicId = $request->integer('clinic_id')) {
            $query->whereExists(function ($q) use ($clinicId) {
                $q->select(DB::raw(1))
                    ->from('ai_assistant_logs as l')
                    ->join('ai_assistant_log_clinics as pc', 'pc.log_id', '=', 'l.id')
                    ->whereRaw('l.conversation_id = conv.conversation_id')
                    ->where('pc.clinic_id', $clinicId);
            });
        }
        if ($categoryId = $request->integer('category_id')) {
            $query->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('ai_assistant_logs as l')
                    ->join('ai_assistant_log_categories as pcat', 'pcat.log_id', '=', 'l.id')
                    ->whereRaw('l.conversation_id = conv.conversation_id')
                    ->where('pcat.category_id', $categoryId);
            });
        }
        if ($minTurns = $request->integer('min_turns')) {
            $query->where('conv.turn_count', '>=', $minTurns);
        }

        $page = $query->orderByDesc('conv.ended_at')->paginate($perPage);

        // Hydrate head-of-thread context: clinics/categories from the
        // last turn, and a snippet from the first turn's query.
        $firstIds = collect($page->items())->pluck('first_log_id')->all();
        $lastIds  = collect($page->items())->pluck('last_log_id')->all();
        $heads = AiAssistantLog::with('user:id,name,phone')->whereIn('id', $firstIds)->get()->keyBy('id');
        $tails = AiAssistantLog::with(['clinics:id,name', 'categories:id,name,name_en'])
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('id');

        $rows = collect($page->items())->map(function ($row) use ($heads, $tails, $reveal) {
            $head = $heads->get($row->first_log_id);
            $tail = $tails->get($row->last_log_id);
            $masker = app(\App\Services\PiiMasker::class);
            $summary = $head?->query ?? '';
            return [
                'conversation_id' => $row->conversation_id,
                'started_at'      => $row->started_at,
                'ended_at'        => $row->ended_at,
                'turn_count'      => (int) $row->turn_count,
                'total_tokens'    => (int) $row->total_tokens,
                'status'          => $row->has_emergency ? 'emergency' : ($row->has_blocked ? 'blocked' : 'normal'),
                'user' => $head?->user ? [
                    'id'   => $head->user->id,
                    'name' => $reveal ? $head->user->name : $masker->maskName($head->user->name),
                ] : null,
                'visitor_id' => $head?->visitor_id,
                'summary'    => $reveal ? Str::limit($summary, 120) : Str::limit($masker->mask($summary) ?? '', 120),
                'clinics'    => $tail?->clinics->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values() ?? collect(),
                'categories' => $tail?->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values() ?? collect(),
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $maskingEnabled = (bool) \App\Models\SystemSetting::get('ai_pii_masking_enabled', true);
        $reveal = $request->boolean('reveal') || ! $maskingEnabled;

        $logs = AiAssistantLog::query()
            ->with(['user:id,name,phone', 'clinics:id,name', 'categories:id,name,name_en'])
            ->where('conversation_id', $conversation)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $logs->map(fn ($log) => (new AiAssistantLogResource($log, $reveal))->toArray(request())),
            'meta' => [
                'reveal'          => $reveal,
                'conversation_id' => $conversation,
            ],
        ]);
    }
}
