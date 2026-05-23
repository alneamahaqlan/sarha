<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SystemSetting;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SystemSetting
 */
class SystemSettingResource extends JsonResource
{
    public function toArray($request): array
    {
        $isEncrypted = $this->type === 'encrypted';
        $isSet       = $isEncrypted ? $this->hasEncryptedValue() : ($this->value !== null && $this->value !== '');

        return [
            'id'          => $this->id,
            'key'         => $this->key,
            'label'       => $this->label,
            'type'        => $this->type,
            'group'       => $this->group,
            // Encrypted slots never leak ciphertext or plaintext — admin sees a mask.
            'value'       => $isEncrypted ? ($isSet ? SystemSetting::MASK : null) : $this->value,
            'value_set'   => $isSet,
            'description' => $this->description,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
