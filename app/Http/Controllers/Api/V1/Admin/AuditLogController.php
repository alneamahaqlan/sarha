<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource as AuditLogApiResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('admin_name', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%");
            });
        }

        if ($adminId = $request->input('filter.admin_id')) {
            $query->where('admin_id', $adminId);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'action', 'admin_name'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return AuditLogApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(AuditLog $auditLog): AuditLogApiResource
    {
        $this->authorize('view', $auditLog);

        return new AuditLogApiResource($auditLog);
    }
}
