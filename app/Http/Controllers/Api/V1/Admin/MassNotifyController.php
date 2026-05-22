<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MassNotifyRequest;
use App\Services\MassNotifyService;
use Illuminate\Http\JsonResponse;

class MassNotifyController extends Controller
{
    public function __construct(private readonly MassNotifyService $massNotify) {}

    public function send(MassNotifyRequest $request): JsonResponse
    {
        $sent = $this->massNotify->send($request->validated());

        return response()->json([
            'data'    => ['sent' => $sent],
            'message' => __('admin.mass_notify_sent', ['count' => $sent]),
        ]);
    }
}
