<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource as SubscriptionApiResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subscription::class);

        $query = Subscription::query()->with('clinic:id,name');

        if ($search = $request->string('search')->toString()) {
            $query->whereHas('clinic', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($type = $request->input('filter.type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }
        if ($clinicId = $request->input('filter.clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'amount', 'ends_at', 'status', 'type'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return SubscriptionApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Subscription $subscription): SubscriptionApiResource
    {
        $this->authorize('view', $subscription);

        return new SubscriptionApiResource($subscription->load('clinic:id,name'));
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by_admin_id'] = $request->user('admin')?->getKey();

        $sub = Subscription::create($data);

        return (new SubscriptionApiResource($sub->load('clinic:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionApiResource
    {
        $subscription->update($request->validated());

        return new SubscriptionApiResource($subscription->fresh()->load('clinic:id,name'));
    }
}
