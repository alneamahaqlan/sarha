<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreComplaintRequest;
use App\Http\Resources\Api\V1\ComplaintResource;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Complaints raised BY the authenticated complex (source = clinic). The complex
 * can file a complaint and track its status; admins handle/resolve them.
 */
class ComplaintController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clinicId = (int) auth('clinic')->id();

        $query = Complaint::query()
            ->where('source', 'clinic')
            ->where('clinic_id', $clinicId);

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ComplaintResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $clinic = auth('clinic')->user();
        $data = $request->validated();

        $complaint = Complaint::create([
            'source'         => 'clinic',
            'clinic_id'      => $clinic->id,
            'customer_name'  => $clinic->name,
            'customer_phone' => $clinic->phone ?? '',
            'customer_email' => $clinic->email,
            'type'           => $data['type'],
            'priority'       => $data['priority'] ?? 'medium',
            'status'         => 'new',
            'subject'        => $data['subject'],
            'description'    => $data['description'],
        ]);

        return (new ComplaintResource($complaint))->response()->setStatusCode(201);
    }
}
