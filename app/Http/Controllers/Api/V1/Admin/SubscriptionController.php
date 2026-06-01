<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource as SubscriptionApiResource;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin CRUD + lifecycle actions for clinic subscriptions.
 *
 * The store path now flows through SubscriptionService so that
 * amount + dates + the clinic's denormalised fields all derive
 * from the chosen package + cycle. Admin types in less, drift is
 * eliminated.
 *
 * Two new lifecycle endpoints:
 *   POST /subscriptions/{id}/renew   — chain a new sub on the same package
 *   POST /subscriptions/{id}/cancel  — end the sub today + flip status
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subscription::class);

        $query = Subscription::query()
            ->with(['clinic:id,name', 'package:id,slug,name_ar,name_en,color_token,monthly_price']);

        if ($search = $request->string('search')->toString()) {
            $query->whereHas('clinic', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }
        if ($clinicId = $request->input('filter.clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }
        if ($packageId = $request->input('filter.subscription_package_id')) {
            $query->where('subscription_package_id', $packageId);
        }
        if ($cycle = $request->input('filter.billing_cycle')) {
            $query->where('billing_cycle', $cycle);
        }
        if ($request->boolean('filter.expiring')) {
            $reminderDays = (int) (\App\Models\SystemSetting::where('key', 'subscription_reminder_days')->value('value') ?? 10);
            $query->where('status', 'active')
                  ->whereBetween('ends_at', [now(), now()->addDays($reminderDays)]);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'amount', 'ends_at', 'status', 'billing_cycle'];
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
        return new SubscriptionApiResource($subscription->load(['clinic:id,name', 'package']));
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $clinic  = Clinic::findOrFail($data['clinic_id']);
        $package = SubscriptionPackage::findOrFail($data['subscription_package_id']);

        $sub = $this->service->activate(
            clinic:         $clinic,
            package:        $package,
            cycle:          $data['billing_cycle'],
            bonusMonths:    (int) ($data['bonus_months'] ?? 0),
            adminId:        $request->user('admin')?->getKey(),
            notes:          $data['notes'] ?? null,
            amountOverride: isset($data['amount']) ? (float) $data['amount'] : null,
        );

        return (new SubscriptionApiResource($sub->load(['clinic:id,name', 'package'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionApiResource
    {
        $subscription->update($request->validated());
        // Clinic's denormalised fields might depend on this row —
        // re-sync to keep `subscription_ends_at` honest after manual edits.
        if ($subscription->clinic) {
            $this->service->syncClinic($subscription->clinic);
        }
        return new SubscriptionApiResource($subscription->fresh()->load(['clinic:id,name', 'package']));
    }

    /**
     * Renew an existing subscription with the same package + cycle.
     * One-click button on the index page for "the customer paid for
     * another quarter".
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);
        $sub = $this->service->renew($subscription, $request->user('admin')?->getKey());
        return response()->json([
            'data' => new SubscriptionApiResource($sub->load(['clinic:id,name', 'package'])),
        ]);
    }

    /**
     * Cancel today. The sub stays in history but its status flips
     * and `ends_at` becomes today; the clinic's denormalised fields
     * are re-synced from whatever's left.
     */
    public function cancel(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);
        $sub = $this->service->cancel($subscription);
        return response()->json([
            'data' => new SubscriptionApiResource($sub->load(['clinic:id,name', 'package'])),
        ]);
    }
}
