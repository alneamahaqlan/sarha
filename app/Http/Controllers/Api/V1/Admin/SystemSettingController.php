<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSystemSettingRequest;
use App\Http\Resources\Api\V1\SystemSettingResource as SystemSettingApiResource;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class SystemSettingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SystemSetting::class);

        $query = SystemSetting::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%");
            });
        }

        if ($group = $request->input('filter.group')) {
            $query->where('group', $group);
        }

        // Filament uses paginated(false) + grouped by group; expose all rows ordered by group.
        $settings = $query->orderBy('group')->orderBy('key')->get();

        return SystemSettingApiResource::collection($settings);
    }

    public function show(SystemSetting $systemSetting): SystemSettingApiResource
    {
        $this->authorize('view', $systemSetting);

        return new SystemSettingApiResource($systemSetting);
    }

    public function update(UpdateSystemSettingRequest $request, SystemSetting $systemSetting): SystemSettingApiResource
    {
        $data = $request->validated();

        // Encrypted slots (API keys): an empty/missing value or the mask token
        // means "keep current". Only a real new string replaces the secret —
        // this prevents the React UI from accidentally overwriting a stored
        // key when the admin opens the dialog and saves without typing.
        if ($systemSetting->type === 'encrypted') {
            $incoming = $data['value'] ?? null;
            if ($incoming === null || $incoming === '' || $incoming === SystemSetting::MASK) {
                unset($data['value']);
            }
        }

        $systemSetting->update($data);

        // Mirrors Filament EditAction::after(fn => Cache::forget('setting:'.$key)).
        Cache::forget('setting:' . $systemSetting->key);

        return new SystemSettingApiResource($systemSetting->fresh());
    }
}
