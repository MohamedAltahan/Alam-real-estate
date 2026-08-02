<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientInteraction;
use App\Models\ClientStage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * منطق العملاء المشترك — يخدم الداشبورد (Blade) والـ API معاً (API-first).
 */
class ClientService
{
    /** استعلام العملاء بعد تطبيق الفلاتر — يخدم القائمة وعدّادات المراحل معاً */
    private function filtered(array $filters = []): Builder
    {
        return Client::query()
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['stage_id'] ?? null, fn ($q, $v) => $q->where('stage_id', $v))
            ->when($filters['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($filters['type_id'] ?? null, fn ($q, $v) => $q->where('type_id', $v));
    }

    /** قائمة العملاء مع بحث وفلاتر و pagination */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['stage', 'type', 'agent', 'area'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * عدد العملاء في كل مرحلة (لأزرار الفلتر) — بنفس الفلاتر الأخرى المفعّلة
     * لكن بدون فلتر المرحلة نفسه، حتى تظل كل الأزرار تعرض أعدادها.
     *
     * @return array{total:int, stages:array<int,int>}
     */
    public function stageCounts(array $filters = []): array
    {
        $base = $this->filtered(array_diff_key($filters, ['stage_id' => null]));

        return [
            'total' => (clone $base)->count(),
            'stages' => (clone $base)
                ->selectRaw('stage_id, COUNT(*) as aggregate')
                ->groupBy('stage_id')
                ->pluck('aggregate', 'stage_id')
                ->all(),
        ];
    }

    /** تفاصيل عميل مع كل ما يخصّه (لشاشة العميل الواحدة) */
    public function load(Client $client): Client
    {
        return $client->load([
            'stage', 'type', 'area', 'agent', 'source',
            'interactions.user', 'interactions.stage',
            'properties.status', 'properties.area',
        ]);
    }

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    /**
     * تسجيل تفاعل (مكالمة/مقابلة) — ولو معاه مرحلة جديدة، يحدّث حالة العميل كمان.
     */
    public function logInteraction(Client $client, array $data): ClientInteraction
    {
        return DB::transaction(function () use ($client, $data) {
            $interaction = $client->interactions()->create([
                'user_id' => $data['user_id'] ?? auth()->id(),
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
                'stage_id' => $data['stage_id'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            // تغيير حالة العميل لو اتحدّدت مرحلة جديدة
            if (! empty($data['stage_id'])) {
                $client->update(['stage_id' => $data['stage_id']]);
            }

            return $interaction;
        });
    }

    /** ربط عقار بالعميل (سجل عقاراته) */
    public function attachProperty(Client $client, int $propertyId, ?string $relation = null, ?string $notes = null): void
    {
        $client->properties()->syncWithoutDetaching([
            $propertyId => ['relation' => $relation, 'notes' => $notes],
        ]);
    }

    public function detachProperty(Client $client, int $propertyId): void
    {
        $client->properties()->detach($propertyId);
    }

    /** مراحل الـ Pipeline للاستخدام في الفلاتر والفورمات */
    public function stages()
    {
        return ClientStage::where('is_active', true)->orderBy('sort_order')->get();
    }
}
