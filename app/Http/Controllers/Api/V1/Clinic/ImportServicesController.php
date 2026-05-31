<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\AnalyzeCsvRequest;
use App\Http\Requests\Api\V1\Clinic\AnalyzeTextRequest;
use App\Http\Requests\Api\V1\Clinic\ConfirmImportRequest;
use App\Http\Requests\Api\V1\Clinic\ImportServicesRequest;
use App\Services\ImportServicesService;
use Illuminate\Http\JsonResponse;

class ImportServicesController extends Controller
{
    public function __construct(private readonly ImportServicesService $importer) {}

    /**
     * Step 1: parse the uploaded CSV and return headers + sample rows + AI
     * column-mapping suggestions. Mirrors Filament ImportServices::analyze().
     */
    public function analyze(AnalyzeCsvRequest $request): JsonResponse
    {
        $path = $request->file('file')->getRealPath();
        $parsed = $this->importer->parseCsv($path);

        if (empty($parsed['headers'])) {
            return response()->json(['message' => __('admin.import_empty')], 422);
        }

        $analysis = $this->importer->analyzeColumns($parsed['headers'], $parsed['rows']);

        return response()->json([
            'data' => [
                'headers'  => $parsed['headers'],
                'rows'     => $parsed['rows'],
                'analysis' => $analysis,
            ],
        ]);
    }

    /**
     * Free-text path: the clinic pastes a price list / .txt and the AI extracts
     * a draft catalog (services + offers) for on-screen review. Nothing is
     * saved here — the confirmed rows come back through confirm().
     */
    public function extractText(AnalyzeTextRequest $request): JsonResponse
    {
        if (! $this->importer->aiAvailable()) {
            return response()->json(['message' => __('admin.import_ai_unavailable')], 422);
        }

        try {
            $catalog = $this->importer->extractCatalogFromText(
                (int) auth('clinic')->id(),
                $request->validated()['text'],
            );
        } catch (\Throwable) {
            return response()->json(['message' => __('admin.import_ai_failed')], 422);
        }

        if (empty($catalog['services']) && empty($catalog['offers'])) {
            return response()->json(['message' => __('admin.import_empty')], 422);
        }

        return response()->json(['data' => $catalog]);
    }

    /**
     * Persist the catalog rows the clinic ticked in the review table. Services
     * and offers are saved together in one transaction; returns the counts.
     */
    public function confirm(ConfirmImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->importer->confirmCatalog(
            (int) auth('clinic')->id(),
            $data['services'] ?? [],
            $data['offers'] ?? [],
        );

        return response()->json([
            'data'    => $result,
            'message' => __('admin.import_confirm_success', [
                'services' => $result['services_created'],
                'offers'   => $result['offers_created'],
            ]),
        ]);
    }

    /**
     * Step 2: given headers + rows + mapping (confirmed by the user), import.
     * Mirrors Filament ImportServices::importNow().
     */
    public function execute(ImportServicesRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Normalise mapping keys to int (incoming JSON may serialise as strings).
        $mapping = [];
        foreach ($data['mapping'] as $idx => $field) {
            if ($field === null || $field === '') continue;
            $mapping[(int) $idx] = (string) $field;
        }

        if (empty($mapping)) {
            return response()->json(['message' => __('admin.import_no_mapping')], 422);
        }

        $imported = $this->importer->importRows(
            (int) auth('clinic')->id(),
            $data['headers'],
            $data['rows'],
            $mapping,
        );

        return response()->json([
            'data'    => ['imported' => $imported],
            'message' => __('admin.import_success', ['count' => $imported]),
        ]);
    }
}
