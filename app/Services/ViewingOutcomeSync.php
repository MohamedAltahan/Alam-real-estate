<?php

namespace App\Services;

use App\Models\ClientStage;
use App\Models\ClientViewing;

/**
 * أثر نتيجة المعاينة على العميل — «مهتم» لأول مرة ⇒ مرحلة العميل «ربح».
 * حالة العقار لا تُمسّ أبداً (تُغيَّر يدوياً من شاشة العقارات)، ومرحلة العميل لا تُنزَّل تلقائياً.
 */
class ViewingOutcomeSync
{
    /**
     * @param  string|null  $previousOutcome  النتيجة قبل الحفظ (null لمعاينة جديدة)
     */
    public function apply(ClientViewing $viewing, ?string $previousOutcome): void
    {
        $wasInterested = $previousOutcome === ClientViewing::OUTCOME_INTERESTED;
        $isInterested = $viewing->outcome === ClientViewing::OUTCOME_INTERESTED;

        if (! $isInterested || $wasInterested) {
            return;
        }

        $viewing->loadMissing('client');
        $client = $viewing->client;
        $wonId = ClientStage::where('key', 'closed_won')->value('id');

        if ($client && $wonId && (int) $client->stage_id !== (int) $wonId) {
            // ClientObserver يسجّل تغيير الحالة في سجل التعديلات ويضبط won_at
            $client->update(['stage_id' => $wonId]);
        }
    }
}
