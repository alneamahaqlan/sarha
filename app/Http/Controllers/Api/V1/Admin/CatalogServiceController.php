<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateCatalogServiceRequest;
use App\Models\CatalogService;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin management of the unified service catalog. The key workflow is
 * reviewing PENDING entries (a clinic proposed a new canonical service):
 * approve flips it active AND un-hides every clinic service waiting on it;
 * reject keeps it for audit and leaves those services hidden.
 *
 * Mirrors the CategoryRequest review controller's shape and guards.
 */
class CatalogServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CatalogService::query()
            ->with(['category:id,name', 'requestedByClinic:id,name'])
            ->withCount('services')
            ->latest();

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (CatalogService $c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'name_en'        => $c->name_en,
                'aliases'        => $c->aliases ?? [],
                'status'         => $c->status,
                'category'       => $c->category ? ['id' => $c->category->id, 'name' => $c->category->name] : null,
                'requested_by'   => $c->requestedByClinic ? ['id' => $c->requestedByClinic->id, 'name' => $c->requestedByClinic->name] : null,
                'services_count' => $c->services_count,
                'created_at'     => $c->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
            ],
        ]);
    }

    /**
     * Edit a canonical catalog entry — primarily its alternative names
     * (aliases), which widen matching and cross-clinic search. Also allows
     * fixing the display name(s) and re-categorising.
     */
    public function update(UpdateCatalogServiceRequest $request, CatalogService $catalogService): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('aliases', $data)) {
            // Trim, drop blanks + duplicates, and never let an alias equal the
            // canonical name itself.
            $data['aliases'] = collect($data['aliases'] ?? [])
                ->map(fn ($a) => trim((string) $a))
                ->filter()
                ->reject(fn (string $a) => $a === trim((string) ($data['name'] ?? $catalogService->name)))
                ->unique()
                ->values()
                ->all();
        }

        $catalogService->update($data);

        return response()->json([
            'message' => __('admin.catalog_services.updated'),
            'data'    => [
                'id'      => $catalogService->id,
                'name'    => $catalogService->name,
                'name_en' => $catalogService->name_en,
                'aliases' => $catalogService->aliases ?? [],
            ],
        ]);
    }

    public function approve(CatalogService $catalogService): JsonResponse
    {
        abort_unless($catalogService->status === CatalogService::STATUS_PENDING, 422, __('admin.catalog_services.already_reviewed'));

        DB::transaction(function () use ($catalogService) {
            $catalogService->update([
                'status'      => CatalogService::STATUS_ACTIVE,
                'reviewed_by' => (int) auth('admin')->id(),
            ]);

            // Un-hide every clinic service that was waiting on this request.
            Service::where('catalog_service_id', $catalogService->id)
                ->where('approval_status', Service::APPROVAL_PENDING)
                ->update(['approval_status' => Service::APPROVAL_APPROVED]);
        });

        return response()->json(['message' => __('admin.catalog_services.approved')]);
    }

    public function reject(CatalogService $catalogService): JsonResponse
    {
        abort_unless($catalogService->status === CatalogService::STATUS_PENDING, 422, __('admin.catalog_services.already_reviewed'));

        $catalogService->update([
            'status'      => CatalogService::STATUS_REJECTED,
            'reviewed_by' => (int) auth('admin')->id(),
        ]);

        // Linked services stay hidden (approval_status remains 'pending').
        return response()->json(['message' => __('admin.catalog_services.rejected')]);
    }
}
