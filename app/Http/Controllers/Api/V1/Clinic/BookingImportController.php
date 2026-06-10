<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ImportCommitRequest;
use App\Http\Requests\Api\V1\Clinic\ImportFileCommitRequest;
use App\Http\Requests\Api\V1\Clinic\ImportFilePreviewRequest;
use App\Http\Requests\Api\V1\Clinic\ImportPreviewRequest;
use App\Models\Booking;
use App\Models\ClinicImportRun;
use App\Models\ClinicImportSource;
use App\Services\ClinicActivityLogger;
use App\Services\Import\BookingImportService;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Import customers/leads from a public Google Sheet into bookings. Two
 * steps: preview (dry-run analysis, no writes) then commit (create the
 * bookings + run). Saved sources let a campaign sheet be re-pulled with the
 * same mapping. Guarded by the same "create booking" ability.
 */
class BookingImportController extends Controller
{
    public function __construct(
        private readonly BookingImportService $service,
        private readonly ClinicActivityLogger $activity,
    ) {}

    public function preview(ImportPreviewRequest $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinicId = (int) ActingClinicUser::clinicId();
        $data = $request->validated();

        try {
            $result = $this->service->preview(
                $clinicId,
                $data['sheet_url'],
                $data['column_map'],
                (int) $data['row_from'],
                (int) $data['row_to'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $this->humanError($e->getMessage())], 422);
        }

        $result['overlap'] = $this->overlappingRuns(
            $clinicId,
            $data['import_source_id'] ?? null,
            (int) $data['row_from'],
            (int) $data['row_to'],
        );

        return response()->json(['data' => $result]);
    }

    public function commit(ImportCommitRequest $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinicId = (int) ActingClinicUser::clinicId();
        $data = $request->validated();

        try {
            $result = $this->service->commit(
                clinicId: $clinicId,
                sheetUrl: $data['sheet_url'],
                columnMap: $data['column_map'],
                rowFrom: (int) $data['row_from'],
                rowTo: (int) $data['row_to'],
                defaults: $data['defaults'] ?? [],
                resolutions: $data['service_resolutions'] ?? [],
                saveSource: (bool) ($data['save_source'] ?? false),
                sourceName: $data['source_name'] ?? null,
                importSourceId: $data['import_source_id'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $this->humanError($e->getMessage())], 422);
        }

        $this->activity->log('bookings.imported', null, [
            'rows_imported' => $result['rows_imported'],
            'rows_skipped'  => $result['rows_skipped'],
            'source_id'     => $result['source_id'],
        ]);

        return response()->json(['data' => $result], 201);
    }

    /**
     * Dry-run for an uploaded CSV/XLSX file. Stores the file so the matching
     * commit can re-read it server-side, and returns a file_token handle.
     */
    public function previewFile(ImportFilePreviewRequest $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinicId = (int) ActingClinicUser::clinicId();
        $data = $request->validated();

        // Persist under a per-clinic dir with a random name; keep the real
        // extension so the reader can pick its parser.
        $ext   = strtolower($request->file('file')->getClientOriginalExtension() ?: 'csv');
        $token = Str::uuid()->toString() . '.' . $ext;
        $request->file('file')->storeAs($this->importDir($clinicId), $token, 'local');

        try {
            $result = $this->service->previewFile(
                $clinicId,
                $this->tokenPath($clinicId, $token),
                $data['column_map'],
                (int) $data['row_from'],
                (int) $data['row_to'],
            );
        } catch (RuntimeException $e) {
            @unlink($this->tokenPath($clinicId, $token));
            return response()->json(['message' => $this->humanError($e->getMessage())], 422);
        }

        $result['file_token'] = $token;
        $result['overlap'] = [];

        return response()->json(['data' => $result]);
    }

    /** Execute a file import referenced by the preview's file_token. */
    public function commitFile(ImportFileCommitRequest $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinicId = (int) ActingClinicUser::clinicId();
        $data = $request->validated();

        $path = $this->tokenPath($clinicId, $data['file_token']);
        if (! is_file($path)) {
            return response()->json(['message' => 'انتهت صلاحية الملف المرفوع — أعد رفعه ثم المعاينة.'], 422);
        }

        try {
            $result = $this->service->commitFile(
                clinicId: $clinicId,
                filePath: $path,
                columnMap: $data['column_map'],
                rowFrom: (int) $data['row_from'],
                rowTo: (int) $data['row_to'],
                defaults: $data['defaults'] ?? [],
                resolutions: $data['service_resolutions'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $this->humanError($e->getMessage())], 422);
        } finally {
            // Single-use: drop the upload once the commit resolves either way
            // (validation failures keep it so the clinic can retry mapping).
            if (isset($result)) {
                @unlink($path);
            }
        }

        $this->activity->log('bookings.imported', null, [
            'rows_imported' => $result['rows_imported'],
            'rows_skipped'  => $result['rows_skipped'],
            'source_id'     => null,
        ]);

        return response()->json(['data' => $result], 201);
    }

    /** Per-clinic storage dir (relative to the local disk root). */
    private function importDir(int $clinicId): string
    {
        return "imports/{$clinicId}";
    }

    /** Absolute path for a stored upload token, hardened against traversal. */
    private function tokenPath(int $clinicId, string $token): string
    {
        // Resolve through the SAME 'local' disk storeAs() wrote to, so the
        // path always matches its configured root (storage/app/private on
        // Laravel 11+). basename strips any traversal; the regex constrains it.
        return Storage::disk('local')->path($this->importDir($clinicId) . '/' . basename($token));
    }

    /** Saved sources for this clinic (most recent first). */
    public function sources(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinicId = (int) ActingClinicUser::clinicId();

        $sources = ClinicImportSource::where('clinic_id', $clinicId)
            ->withCount('runs')
            ->latest()
            ->get(['id', 'name', 'sheet_url', 'column_map', 'defaults', 'service_aliases', 'last_imported_at', 'created_at']);

        return response()->json(['data' => $sources]);
    }

    /** One source with its run history (date + row range). */
    public function showSource(ClinicImportSource $source): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        abort_unless($source->clinic_id === (int) ActingClinicUser::clinicId(), 404);

        $source->load(['runs' => fn ($q) => $q->latest()]);

        return response()->json(['data' => $source]);
    }

    public function destroySource(ClinicImportSource $source): JsonResponse
    {
        $this->authorize('create', Booking::class);
        abort_unless($source->clinic_id === (int) ActingClinicUser::clinicId(), 404);

        $source->delete();

        return response()->json(null, 204);
    }

    /**
     * Prior runs (of the same saved source) whose row range overlaps the
     * requested one — so the UI can warn about re-importing the same rows.
     *
     * @return array<int,array{row_from:int,row_to:int,imported_at:?string}>
     */
    private function overlappingRuns(int $clinicId, ?int $sourceId, int $from, int $to): array
    {
        if (! $sourceId) {
            return [];
        }

        return ClinicImportRun::where('clinic_id', $clinicId)
            ->where('import_source_id', $sourceId)
            ->where('row_from', '<=', $to)
            ->where('row_to', '>=', $from)
            ->latest()
            ->get(['row_from', 'row_to', 'created_at'])
            ->map(fn (ClinicImportRun $r) => [
                'row_from'    => $r->row_from,
                'row_to'      => $r->row_to,
                'imported_at' => $r->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Map a thrown error key to an Arabic message for the clinic. */
    private function humanError(string $key): string
    {
        return [
            'import.invalid_url'      => 'رابط Google Sheet غير صالح.',
            'import.sheet_not_public' => 'الشيت غير متاح للعموم — فعّل المشاركة "أي شخص لديه الرابط".',
            'import.sheet_unreadable' => 'تعذّر قراءة الشيت. تأكد من الرابط ثم أعد المحاولة.',
            'import.sheet_empty'      => 'الشيت فارغ.',
            'import.invalid_range'    => 'نطاق الصفوف غير صحيح.',
            'import.range_too_large'  => 'النطاق كبير جداً — الحد الأقصى ' . \App\Services\Import\GoogleSheetReader::MAX_ROWS . ' صف في الاستيراد الواحد.',
            'import.invalid_column'   => 'حرف عمود غير صالح في خريطة الأعمدة.',
            'import.file_unreadable'  => 'تعذّر قراءة الملف. تأكد أنه CSV أو XLSX سليم.',
            'import.file_unsupported' => 'صيغة الملف غير مدعومة — استخدم CSV أو XLSX.',
            'import.file_empty'       => 'الملف فارغ.',
        ][$key] ?? 'تعذّر إتمام الاستيراد.';
    }
}
