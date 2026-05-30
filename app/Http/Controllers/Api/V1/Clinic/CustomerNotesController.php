<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreCustomerNoteRequest;
use App\Http\Resources\Api\V1\CustomerNoteResource;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Multi-note thread on a Customer entity.
 *
 * Auth model:
 *   - read: customers.view (all 3 roles)
 *   - create: customers.notes.create (reception also gets this)
 *   - update/delete: author OR customers.notes.manage (owner)
 *   - togglePin: same as update
 */
class CustomerNotesController extends Controller
{
    public function index(Customer $customer): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $notes = $customer->notes()->limit(100)->get();
        return response()->json([
            'data' => CustomerNoteResource::collection($notes)->resolve(),
        ]);
    }

    public function store(StoreCustomerNoteRequest $request, Customer $customer): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $validated = $request->validated();

        $note = CustomerNote::create([
            'customer_id'     => $customer->id,
            'body'            => trim($validated['body']),
            'is_pinned'       => (bool) ($validated['is_pinned'] ?? false),
            'created_by_type' => ActingClinicUser::actorType(),
            'created_by_id'   => ActingClinicUser::actorId(),
            'created_by_name' => ActingClinicUser::actorName() ?? '—',
        ]);

        return response()->json([
            'data' => (new CustomerNoteResource($note))->resolve(),
        ], 201);
    }

    public function update(Request $request, Customer $customer, CustomerNote $note): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $this->ensureBelongsToCustomer($note, $customer);
        $this->ensureCanMutate($note);

        $validated = $request->validate([
            'body'      => ['sometimes', 'required', 'string', 'max:5000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['body']))      $note->body      = trim($validated['body']);
        if (isset($validated['is_pinned'])) $note->is_pinned = (bool) $validated['is_pinned'];
        $note->save();

        return response()->json([
            'data' => (new CustomerNoteResource($note))->resolve(),
        ]);
    }

    public function destroy(Customer $customer, CustomerNote $note): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $this->ensureBelongsToCustomer($note, $customer);
        $this->ensureCanMutate($note);

        $note->delete();
        return response()->json(['data' => ['removed' => true]]);
    }

    // ---------- internal ----------

    private function ensureClinicOwns(Customer $customer): void
    {
        $clinicId = (int) auth('clinic')->id();
        abort_unless($customer->clinic_id === $clinicId, 404);
    }

    private function ensureBelongsToCustomer(CustomerNote $note, Customer $customer): void
    {
        abort_unless($note->customer_id === $customer->id, 404);
    }

    /**
     * Author may always edit/delete/pin their own note. Otherwise the
     * actor needs the broader customers.notes.manage (owner).
     */
    private function ensureCanMutate(CustomerNote $note): void
    {
        $isAuthor = $note->created_by_type === ActingClinicUser::actorType()
            && (int) $note->created_by_id === (int) ActingClinicUser::actorId();
        if ($isAuthor) return;
        abort_unless(ActingClinicUser::can('customers.notes.manage'), 403);
    }
}
