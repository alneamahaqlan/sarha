<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantLog;
use App\Models\AiUserInteractionSummary;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * One-shot endpoint backing the "AI Interests" section on the
 * super-admin's user profile page.
 *
 * Returns:
 *   - rollup KPIs (total conversations, top specialty, top clinics)
 *   - timeline of the most recent 10 summaries with linked clinic +
 *     category names so the React side can render badges directly
 *
 * Strictly admin-only — the user themselves never sees this surface
 * (Phase 2 privacy decision documented in the spec).
 */
class UserAiInterestsController extends Controller
{
    public function show(int $user): JsonResponse
    {
        $user = User::query()->select(['id', 'name', 'phone'])->findOrFail($user);

        // Conversations + last interaction across ALL of this user's
        // logs (not just summaries — summaries only exist for
        // matched/details/freeform turns, not greetings).
        $logsBase = AiAssistantLog::where('user_id', $user->id);
        $conversationCount = (clone $logsBase)
            ->whereNotNull('conversation_id')
            ->distinct('conversation_id')
            ->count('conversation_id');
        $lastInteractionAt = (clone $logsBase)->max('created_at');

        // Top categories + clinics across the whole history.
        $topCategoryIds = $this->topCategoryIds($user->id);
        $topClinicIds   = $this->topClinicIds($user->id);

        $categories = Category::query()->whereIn('id', $topCategoryIds->keys())
            ->get(['id', 'name', 'name_en', 'slug', 'emoji'])
            ->keyBy('id');
        $clinics = Clinic::query()->whereIn('id', $topClinicIds->keys())
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $topSpecialty = $topCategoryIds->keys()->first();
        $topCat = $topSpecialty !== null ? $categories->get($topSpecialty) : null;
        $topSpecialtyData = $topCat ? [
            'id'    => $topCat->id,
            'name'  => $topCat->display_name ?? $topCat->name,
            'count' => $topCategoryIds->get($topSpecialty),
        ] : null;

        // Now load the full clinics/categories ids that show up across
        // ALL of this user's timeline summaries, not just the top-5 used
        // for the rollup, so the timeline can render every linked badge.
        $allTimelineCategoryIds = AiUserInteractionSummary::where('user_id', $user->id)
            ->pluck('categories')
            ->flatten()->unique()->filter()->values();
        $allTimelineClinicIds = AiUserInteractionSummary::where('user_id', $user->id)
            ->pluck('clinics')
            ->flatten()->unique()->filter()->values();
        $timelineCategories = Category::query()->whereIn('id', $allTimelineCategoryIds)
            ->get(['id', 'name', 'name_en', 'emoji'])->keyBy('id');
        $timelineClinics = Clinic::query()->whereIn('id', $allTimelineClinicIds)
            ->get(['id', 'name', 'slug'])->keyBy('id');

        $summaries = AiUserInteractionSummary::query()
            ->where('user_id', $user->id)
            ->orderByDesc('generated_at')
            ->limit(10)
            ->get();

        $timeline = $summaries->map(function (AiUserInteractionSummary $s) use ($timelineCategories, $timelineClinics) {
            $catIds = collect($s->categories ?? [])->take(5);
            $clinicIds = collect($s->clinics ?? [])->take(5);
            $log = $s->log_id ? AiAssistantLog::query()->select(['id', 'conversation_id', 'created_at'])->find($s->log_id) : null;
            return [
                'id'              => $s->id,
                'topic'           => $s->topic,
                'seriousness'     => $s->seriousness,
                'generated_at'    => $s->generated_at?->toIso8601String(),
                'conversation_id' => $log?->conversation_id,
                'categories'      => $catIds
                    ->map(fn ($id) => $timelineCategories->get($id))
                    ->filter()
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name ?? $c->name, 'emoji' => $c->emoji])
                    ->values(),
                'clinics' => $clinicIds
                    ->map(fn ($id) => $timelineClinics->get($id))
                    ->filter()
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
                    ->values(),
            ];
        });

        return response()->json([
            'data' => [
                'has_history'         => $conversationCount > 0,
                'conversation_count'  => $conversationCount,
                'last_interaction_at' => $lastInteractionAt,
                'top_specialty'       => $topSpecialtyData,
                'top_clinics'         => $topClinicIds->take(3)->map(fn ($count, $id) => $clinics->get($id) ? [
                    'id'    => $clinics->get($id)->id,
                    'name'  => $clinics->get($id)->name,
                    'slug'  => $clinics->get($id)->slug,
                    'count' => $count,
                ] : null)->filter()->values(),
                'timeline'            => $timeline,
            ],
        ]);
    }

    /** @return \Illuminate\Support\Collection<int,int> keyed by category_id, valued by count */
    private function topCategoryIds(int $userId): \Illuminate\Support\Collection
    {
        return \DB::table('ai_assistant_log_categories as p')
            ->join('ai_assistant_logs as l', 'l.id', '=', 'p.log_id')
            ->where('l.user_id', $userId)
            ->selectRaw('p.category_id, COUNT(*) as c')
            ->groupBy('p.category_id')
            ->orderByDesc('c')
            ->limit(5)
            ->pluck('c', 'p.category_id');
    }

    /** @return \Illuminate\Support\Collection<int,int> keyed by clinic_id */
    private function topClinicIds(int $userId): \Illuminate\Support\Collection
    {
        return \DB::table('ai_assistant_log_clinics as p')
            ->join('ai_assistant_logs as l', 'l.id', '=', 'p.log_id')
            ->where('l.user_id', $userId)
            ->selectRaw('p.clinic_id, COUNT(*) as c')
            ->groupBy('p.clinic_id')
            ->orderByDesc('c')
            ->limit(5)
            ->pluck('c', 'p.clinic_id');
    }
}
