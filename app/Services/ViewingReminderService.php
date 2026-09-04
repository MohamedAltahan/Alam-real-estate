<?php

namespace App\Services;

use App\Models\ClientViewing;
use App\Models\User;
use App\Notifications\ViewingReminder;
use Illuminate\Support\Facades\DB;

/**
 * إرسال تذكير المعاينة للمسؤول قبل الموعد بالمدة المضبوطة في إعداداته.
 *
 * يُستدعى من نقطة الاستطلاع كل دقيقة (بدون الحاجة إلى cron) ومن الأمر viewings:remind.
 * reminded_at يضمن إرسال التذكير مرة واحدة فقط لكل معاينة.
 */
class ViewingReminderService
{
    /** أقصى مدة تذكير يمكن ضبطها (يوم) */
    public const MAX_LEAD_MINUTES = 1440;

    /** المعاينات الأقدم من هذا لا تُذكّر (فات وقتها) */
    public const STALE_HOURS = 24;

    public function dispatchDue(int $limit = 50): int
    {
        $now = now();

        $candidates = ClientViewing::query()
            ->with(['client:id,name,agent_id,recorded_by', 'property:id,reference_code,agent_id'])
            ->where('outcome', ClientViewing::OUTCOME_PENDING)
            ->whereNull('reminded_at')
            ->where('scheduled_at', '<=', $now->copy()->addMinutes(self::MAX_LEAD_MINUTES))
            ->where('scheduled_at', '>=', $now->copy()->subHours(self::STALE_HOURS))
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        $users = [];

        foreach ($candidates as $viewing) {
            $user = $this->responsibleUser($viewing, $users);

            if (! $user || ! $user->viewingRemindersEnabled()) {
                continue;
            }

            if ($viewing->scheduled_at->gt($now->copy()->addMinutes($user->viewingLeadMinutes()))) {
                continue;
            }

            $sent += DB::transaction(function () use ($viewing, $user) {
                $fresh = ClientViewing::query()->lockForUpdate()->find($viewing->id);

                if (! $fresh || $fresh->reminded_at || $fresh->outcome !== ClientViewing::OUTCOME_PENDING) {
                    return 0;
                }

                $fresh->setRelation('client', $viewing->client)->setRelation('property', $viewing->property);

                $user->notify(new ViewingReminder($fresh));
                $fresh->forceFill(['reminded_at' => now()])->saveQuietly();

                return 1;
            });
        }

        return $sent;
    }

    /** المسؤول: مسؤول العميل، وإلا مسؤول العقار، وإلا من سجّل العميل */
    public function responsibleUser(ClientViewing $viewing, array &$cache = []): ?User
    {
        $id = $viewing->client?->agent_id
            ?: $viewing->property?->agent_id
            ?: $viewing->client?->recorded_by;

        if (! $id) {
            return null;
        }

        return $cache[$id] ??= User::find($id);
    }
}
