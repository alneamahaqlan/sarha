<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Messaging\WhatsAppGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWhatsAppSenderRequest;
use App\Http\Requests\Api\V1\Admin\UpdateWhatsAppSenderRequest;
use App\Http\Resources\Api\V1\WhatsAppSenderResource;
use App\Models\SystemSetting;
use App\Models\WhatsAppSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WhatsAppSenderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsAppSender::class);

        $senders = WhatsAppSender::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return WhatsAppSenderResource::collection($senders);
    }

    public function show(WhatsAppSender $whatsappSender): WhatsAppSenderResource
    {
        $this->authorize('view', $whatsappSender);

        return new WhatsAppSenderResource($whatsappSender);
    }

    public function store(StoreWhatsAppSenderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['provider'] ??= 'wappi';

        $sender = WhatsAppSender::create($data);

        return (new WhatsAppSenderResource($sender))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateWhatsAppSenderRequest $request, WhatsAppSender $whatsappSender): WhatsAppSenderResource
    {
        $data = $request->validated();

        // An empty / masked token means "keep the stored one" — never wipe a
        // working credential just because the dialog was saved without retyping it.
        $incoming = $data['token'] ?? null;
        if ($incoming === null || $incoming === '' || $incoming === SystemSetting::MASK) {
            unset($data['token']);
        }

        $whatsappSender->update($data);

        return new WhatsAppSenderResource($whatsappSender->fresh());
    }

    public function destroy(WhatsAppSender $whatsappSender): JsonResponse
    {
        $this->authorize('delete', $whatsappSender);

        $whatsappSender->delete();

        return response()->json(null, 204);
    }

    /**
     * Fire a one-off test message through this sender to verify the
     * profile_id + token are valid and the number is connected.
     */
    public function test(Request $request, WhatsAppSender $whatsappSender, WhatsAppGateway $gateway): JsonResponse
    {
        $this->authorize('update', $whatsappSender);

        $request->validate([
            'phone' => ['required', 'string', 'regex:/^0?5\d{8}$/'],
        ]);

        if (! $whatsappSender->hasCredentials()) {
            return response()->json(['message' => __('admin.whatsapp_senders.no_credentials')], 422);
        }

        $ok = $gateway->send(
            $whatsappSender,
            $request->string('phone')->toString(),
            __('admin.whatsapp_senders.test_message'),
        );

        $ok ? $whatsappSender->recordSuccess() : $whatsappSender->recordFailure();

        return response()->json([
            'sent'    => $ok,
            'message' => $ok
                ? __('admin.whatsapp_senders.test_sent')
                : __('admin.whatsapp_senders.test_failed'),
        ], $ok ? 200 : 502);
    }
}
