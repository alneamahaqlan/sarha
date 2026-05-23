<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminRequest;
use App\Http\Resources\Api\V1\AdminResource as AdminApiResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Admin::class);

        $query = Admin::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('filter.role')) {
            $query->where('role', $role);
        }

        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['name', 'email', 'role', 'created_at', 'is_active'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return AdminApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Admin $admin_user): AdminApiResource
    {
        $this->authorize('view', $admin_user);

        return new AdminApiResource($admin_user);
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Model casts handle hashing via 'password' => 'hashed', but be explicit:
        $data['password'] = Hash::make($data['password']);

        $admin = Admin::create($data);

        return (new AdminApiResource($admin))->response()->setStatusCode(201);
    }

    public function update(UpdateAdminRequest $request, Admin $admin_user): AdminApiResource
    {
        $data = $request->validated();

        // Mirrors Filament's dehydrated(filled) — empty password means "keep existing".
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        unset($data['password_confirmation']);

        $admin_user->update($data);

        return new AdminApiResource($admin_user);
    }

    public function destroy(Admin $admin_user): JsonResponse
    {
        $this->authorize('delete', $admin_user);

        $admin_user->delete();

        return response()->json(null, 204);
    }
}
