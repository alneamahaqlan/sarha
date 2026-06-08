<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClinicBookingStage;
use App\Services\BookingStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-clinic custom Kanban stages ("الحل الوسط"). Clinics add, rename,
 * recolour, reorder and delete their own columns. Each stage is pinned
 * to a fixed semantic `kind` (new|confirmed|completed|cancelled) so the
 * status-driven business logic (stats, suggestions, completion flow)
 * never changes.
 */
class BookingStageController extends Controller
{
    public function __construct(private readonly BookingStageService $stages) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinicId = (int) auth('clinic')->id();

        return response()->json(['data' => $this->stages->ensureDefaults($clinicId)
            ->map(fn (ClinicBookingStage $s) => $this->present($s))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinicId = (int) auth('clinic')->id();
        $data = $this->validatePayload($request);

        // Append to the end of the board.
        $maxOrder = (int) ClinicBookingStage::where('clinic_id', $clinicId)->max('sort_order');

        $stage = ClinicBookingStage::create([
            'clinic_id'  => $clinicId,
            'name'       => $data['name'],
            'kind'       => $data['kind'],
            'color'      => $data['color'] ?? 'slate',
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['data' => $this->present($stage)], 201);
    }

    public function update(Request $request, ClinicBookingStage $stage): JsonResponse
    {
        $this->authorizeStage($stage);
        $data = $this->validatePayload($request);

        $stage->update([
            // Base stages have system-fixed name + kind (they label the 4
            // scopes for the whole board) — only their colour is editable.
            'name'  => $stage->is_default ? $stage->name : $data['name'],
            'kind'  => $stage->is_default ? $stage->kind : $data['kind'],
            'color' => $data['color'] ?? $stage->color,
        ]);

        return response()->json(['data' => $this->present($stage)]);
    }

    public function destroy(ClinicBookingStage $stage): JsonResponse
    {
        $this->authorizeStage($stage);

        // The 4 base stages anchor each kind and can't be deleted — this
        // guarantees every kind always keeps a fallback column, so no
        // booking can ever fall off the board.
        if ($stage->is_default) {
            return response()->json([
                'message' => 'لا يمكن حذف المراحل الأساسية الأربع — يمكنك فقط حذف المراحل التي أضفتها.',
            ], 422);
        }

        // Bookings in this stage fall back to their kind's primary (base)
        // stage via the nullOnDelete FK + Booking::scopeForStage().
        $stage->delete();

        return response()->json(['data' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinicId = (int) auth('clinic')->id();

        $data = $request->validate([
            'ordered_ids'   => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        // Only reorder ids that actually belong to this clinic.
        $owned = ClinicBookingStage::where('clinic_id', $clinicId)
            ->whereIn('id', $data['ordered_ids'])->pluck('id')->all();

        $order = 0;
        foreach ($data['ordered_ids'] as $id) {
            if (in_array((int) $id, $owned, true)) {
                ClinicBookingStage::where('id', $id)->update(['sort_order' => $order++]);
            }
        }

        return response()->json(['data' => $this->stages->ensureDefaults($clinicId)
            ->map(fn (ClinicBookingStage $s) => $this->present($s))->values()]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'  => ['required', 'string', 'max:40'],
            'kind'  => ['required', Rule::in(ClinicBookingStage::KINDS)],
            'color' => ['nullable', Rule::in(ClinicBookingStage::COLORS)],
        ]);
    }

    private function authorizeStage(ClinicBookingStage $stage): void
    {
        $this->authorize('create', Booking::class);
        abort_unless($stage->clinic_id === (int) auth('clinic')->id(), 404);
    }

    private function present(ClinicBookingStage $stage): array
    {
        return [
            'id'         => $stage->id,
            'name'       => $stage->name,
            'kind'       => $stage->kind,
            'color'      => $stage->color,
            'sort_order' => $stage->sort_order,
            'is_default' => (bool) $stage->is_default,
        ];
    }
}
