<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin review of complex-submitted specialty (category) requests. */
class CategoryRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CategoryRequest::query()->with('clinic:id,name')->latest();

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (CategoryRequest $r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'status'     => $r->status,
                'clinic'     => $r->clinic ? ['id' => $r->clinic->id, 'name' => $r->clinic->name] : null,
                'created_at' => $r->created_at,
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

    public function approve(CategoryRequest $categoryRequest): JsonResponse
    {
        abort_unless($categoryRequest->status === 'pending', 422, __('admin.category_requests.already_reviewed'));

        // Reuse an existing category with the same name if present, else create one.
        $category = Category::firstOrCreate(
            ['name' => $categoryRequest->name],
            [
                'slug'       => Str::slug($categoryRequest->name) ?: Str::random(8),
                'is_active'  => true,
                'sort_order' => (int) (Category::max('sort_order') ?? 0) + 1,
            ],
        );

        $categoryRequest->update([
            'status'      => 'approved',
            'category_id' => $category->id,
            'reviewed_by' => (int) auth('admin')->id(),
        ]);

        // Auto-attach the newly approved specialty to the service the
        // request came from (if any) — saves the complex from having to
        // re-edit the service after approval.
        if ($categoryRequest->service_id) {
            $service = \App\Models\Service::find($categoryRequest->service_id);
            if ($service) {
                $service->categories()->syncWithoutDetaching([$category->id]);
            }
        }

        return response()->json(['message' => __('admin.category_requests.approved')]);
    }

    public function reject(CategoryRequest $categoryRequest): JsonResponse
    {
        abort_unless($categoryRequest->status === 'pending', 422, __('admin.category_requests.already_reviewed'));

        $categoryRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => (int) auth('admin')->id(),
        ]);

        return response()->json(['message' => __('admin.category_requests.rejected')]);
    }
}
