<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\CatalogService;
use App\Services\CatalogMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Typeahead for the unified catalog used by the clinic "add service" form.
 * The clinic types a name; we suggest existing canonical services so they
 * link to one (instant publish) instead of spawning a new pending request.
 */
class CatalogServiceController extends Controller
{
    public function __construct(private readonly CatalogMatchService $matcher) {}

    public function suggest(Request $request): JsonResponse
    {
        $q = $request->string('q')->toString();

        $items = $this->matcher->suggest($q, 10)->map(fn (CatalogService $c) => [
            'id'          => $c->id,
            'name'        => $c->name,
            'name_en'     => $c->name_en,
            'category_id' => $c->category_id,
        ])->values();

        return response()->json(['data' => $items]);
    }
}
