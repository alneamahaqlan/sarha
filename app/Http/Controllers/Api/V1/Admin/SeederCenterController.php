<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeederRun;
use App\Services\SeederCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seeder Center (مركز السيدر) — super-admin tooling to inventory, hide/restore,
 * purge, and reseed demo data without touching real, app-entered records.
 * Every endpoint is restricted to super-admins.
 */
class SeederCenterController extends Controller
{
    public function __construct(private readonly SeederCenterService $service)
    {
    }

    private function guard(): void
    {
        abort_unless(
            (bool) (auth('admin')->user()?->isSuperAdmin()),
            403,
            'هذه الأداة متاحة لمدير النظام الأعلى فقط.',
        );
    }

    /** Per-batch summary cards. */
    public function inventory(): JsonResponse
    {
        $this->guard();

        return response()->json(['data' => $this->service->inventory()]);
    }

    /** Per-table drill-down for one batch. */
    public function breakdown(string $batch): JsonResponse
    {
        $this->guard();

        return response()->json(['data' => $this->service->breakdown($batch)]);
    }

    /** Soft-hide a batch from the whole platform (instant, reversible). */
    public function hide(string $batch): JsonResponse
    {
        $this->guard();
        $affected = $this->service->hide($batch);

        return response()->json(['data' => ['hidden' => $affected]]);
    }

    /** Restore (un-hide) a batch — same data, no reseed. */
    public function unhide(string $batch): JsonResponse
    {
        $this->guard();
        $affected = $this->service->unhide($batch);

        return response()->json(['data' => ['restored' => $affected]]);
    }

    /** Real rows that would be affected by purging this batch. */
    public function conflicts(string $batch): JsonResponse
    {
        $this->guard();

        return response()->json(['data' => $this->service->conflicts($batch)]);
    }

    /**
     * Permanently delete a batch. Returns 409 with the conflicts list when
     * real data references the batch and `force` was not passed.
     */
    public function purge(string $batch, Request $request): JsonResponse
    {
        $this->guard();
        $force = $request->boolean('force');

        $result = $this->service->purge($batch, $force);

        if ($result['conflicts'] !== []) {
            return response()->json([
                'message'   => 'لا يمكن الحذف: توجد بيانات حقيقية مرتبطة بهذه المجموعة.',
                'conflicts' => $result['conflicts'],
            ], 409);
        }

        return response()->json(['data' => ['deleted' => $result['deleted']]]);
    }

    /** Re-run a batch's seeder to regenerate purged data. */
    public function reseed(string $batch): JsonResponse
    {
        $this->guard();
        $admin = auth('admin')->user();

        $run = $this->service->reseed($batch, $admin?->id, $admin?->name);

        return response()->json(['data' => $this->presentRun($run->refresh())], 202);
    }

    /** Poll a reseed run's status (used for heavy async batches). */
    public function runStatus(SeederRun $run): JsonResponse
    {
        $this->guard();

        return response()->json(['data' => $this->presentRun($run)]);
    }

    private function presentRun(SeederRun $run): array
    {
        return [
            'id'           => $run->id,
            'batch'        => $run->batch,
            'status'       => $run->status,
            'message'      => $run->message,
            'rows_created' => $run->rows_created,
            'finished_at'  => $run->finished_at?->toIso8601String(),
        ];
    }
}
