<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SubscriptionPackageRequest;
use App\Http\Resources\Api\V1\SubscriptionPackageResource;
use App\Models\Clinic;
use App\Models\SubscriptionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Super-admin CRUD for the subscription packages catalogue.
 *
 * Safety rules:
 *   - delete is rejected if any clinic is still on the package
 *     (FK is nullOnDelete but we'd rather warn the admin loudly
 *     than silently strand 500 clinics on a null package)
 *   - everything else is a straight save; FeatureGate reads the
 *     fresh row immediately, so an edit takes effect on the next
 *     request without a cache flush
 */
class SubscriptionPackageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $packages = SubscriptionPackage::query()
            ->withCount('clinics')
            ->ordered()
            ->get();

        return SubscriptionPackageResource::collection($packages);
    }

    public function show(SubscriptionPackage $subscriptionPackage): SubscriptionPackageResource
    {
        return new SubscriptionPackageResource($subscriptionPackage->loadCount('clinics'));
    }

    public function store(SubscriptionPackageRequest $request): JsonResponse
    {
        $package = SubscriptionPackage::create($request->validated());
        return (new SubscriptionPackageResource($package->loadCount('clinics')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(SubscriptionPackageRequest $request, SubscriptionPackage $subscriptionPackage): SubscriptionPackageResource
    {
        $subscriptionPackage->update($request->validated());
        return new SubscriptionPackageResource($subscriptionPackage->loadCount('clinics'));
    }

    public function destroy(SubscriptionPackage $subscriptionPackage): JsonResponse
    {
        $count = Clinic::where('subscription_package_id', $subscriptionPackage->id)->count();
        if ($count > 0) {
            return response()->json([
                'message' => __('admin.packages.cannot_delete_with_clinics', ['count' => $count]),
                'clinics_count' => $count,
            ], 422);
        }
        $subscriptionPackage->delete();
        Log::info('SubscriptionPackage deleted', ['id' => $subscriptionPackage->id, 'slug' => $subscriptionPackage->slug]);
        return response()->json(['data' => ['ok' => true]]);
    }
}
