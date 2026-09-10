<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ClientStage;
use App\Services\ClientAuditLogger;

/**
 * يلتقط إنشاء العميل وأي تعديل على أعمدته (بما فيها تغيير الحالة من تسجيل التواصل)
 * ويكتبه في سجل التعديلات. الحذف يسجَّل من ClientService قبل تنفيذه.
 * ويضبط won_at عند الانتقال إلى مرحلة «ربح» ويمسحه عند مغادرتها.
 */
class ClientObserver
{
    public function __construct(private ClientAuditLogger $audit) {}

    public function saving(Client $client): void
    {
        if (! $client->isDirty('stage_id')) {
            return;
        }

        $wonId = ClientStage::where('key', 'closed_won')->value('id');
        $isWon = $wonId !== null && (int) $client->stage_id === (int) $wonId;

        $client->won_at = $isWon ? ($client->won_at ?? now()) : null;
    }

    public function created(Client $client): void
    {
        $this->audit->record($client, 'created', null, $this->audit->snapshot(
            $client,
            ['name', 'phone_code', 'phone', 'email', 'stage_id', 'agent_id', 'preferred_contact', 'nationality', 'social_status', 'household_size', 'workplace', 'notes'],
        ));
    }

    public function updated(Client $client): void
    {
        // won_at عمود مشتق من الحالة — لا يُسجَّل كسطر مستقل
        $changes = $this->audit->changes($client, $client->getChanges(), ['created_at', 'updated_at', 'won_at']);

        if ($changes) {
            $this->audit->record($client, 'updated', null, $changes);
        }
    }
}
