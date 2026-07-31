<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل JSON للعميل — للـ API (تطبيق موبايل مستقبلاً). نفس مصدر بيانات الـ Blade (الـ Service).
 */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'rating' => $this->rating,
            'area' => $this->whenLoaded('area', fn () => $this->area?->name),
            'type' => $this->whenLoaded('type', fn () => $this->type?->name),
            'stage' => $this->whenLoaded('stage', fn () => [
                'id' => $this->stage?->id,
                'name' => $this->stage?->name,
                'color' => $this->stage?->color,
                'is_final' => (bool) $this->stage?->is_final,
            ]),
            'agent' => $this->whenLoaded('agent', fn () => $this->agent?->only('id', 'name')),
            'interactions_count' => $this->whenCounted('interactions'),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
