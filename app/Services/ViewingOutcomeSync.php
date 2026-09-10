<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyStatus;

/**
 * أثر نتيجة المعاينة على العميل والعقار — المكان الوحيد الذي يربط الثلاثة:
 * «تم اختيار العقار» ⇒ العميل «ربح»، وعقار البيع «مباع».
 * التراجع عن الاختيار (لم يختر / قيد الانتظار / إخلاء / حذف المعاينة) ⇒ عقار البيع يعود «متاح»
 * ما لم يكن عميل آخر ما زال مختاراً له. مرحلة العميل لا تُنزَّل تلقائياً.
 * عقار الإيجار لا تُمسّ حالته: المشغولية مشتقة من المعاينة المختارة ويحرّره الإخلاء.
 */
class ViewingOutcomeSync
{
    public function __construct(private ClientAuditLogger $audit) {}

    /**
     * @param  string|null  $previousOutcome  النتيجة قبل الحفظ (null لمعاينة جديدة)
     * @param  bool  $deleted  المعاينة حُذفت فلا تُحتسب نتيجتها الحالية
     */
    public function apply(ClientViewing $viewing, ?string $previousOutcome, bool $deleted = false): void
    {
        $viewing->loadMissing(['client', 'property.status']);

        $wasChosen = $previousOutcome === ClientViewing::OUTCOME_CHOSEN;
        $isChosen = ! $deleted && $viewing->outcome === ClientViewing::OUTCOME_CHOSEN;

        if ($isChosen && ! $wasChosen) {
            $this->markChosen($viewing);
        } elseif ($wasChosen && ! $isChosen) {
            $this->release($viewing);
        }
    }

    private function markChosen(ClientViewing $viewing): void
    {
        $client = $viewing->client;
        $wonId = ClientStage::where('key', 'closed_won')->value('id');

        if ($client && $wonId && (int) $client->stage_id !== (int) $wonId) {
            // ClientObserver يسجّل تغيير الحالة في سجل التعديلات ويضبط won_at
            $client->update(['stage_id' => $wonId]);
        }

        $property = $viewing->property;
        $soldId = PropertyStatus::where('key', 'sold')->value('id');

        if ($property && $property->purpose === 'sale' && $soldId && (int) $property->status_id !== (int) $soldId) {
            $this->setStatus($client, $property, (int) $soldId);
        }
    }

    private function release(ClientViewing $viewing): void
    {
        $property = $viewing->property;

        if (! $property || $property->purpose !== 'sale' || $property->status?->key !== 'sold') {
            return;
        }

        $availableId = PropertyStatus::where('key', 'available')->value('id');

        if (! $availableId) {
            return;
        }

        // عميل آخر ما زال مختاراً للعقار ⇒ يبقى مباعاً
        if ($property->busyViewings()->whereKeyNot($viewing->id)->exists()) {
            return;
        }

        $this->setStatus($viewing->client, $property, (int) $availableId);
    }

    private function setStatus(?Client $client, Property $property, int $statusId): void
    {
        $old = $property->status?->name;

        // PropertyObserver يضبط/يمسح sold_at
        $property->update(['status_id' => $statusId]);
        $property->load('status');

        $this->audit->record($client, 'property_status_synced', $property, [
            'status' => ['old' => $old, 'new' => $property->status?->name],
        ]);
    }
}
