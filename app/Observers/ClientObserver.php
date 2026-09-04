<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\ClientAuditLogger;

/**
 * يلتقط إنشاء العميل وأي تعديل على أعمدته (بما فيها تغيير الحالة من تسجيل التواصل)
 * ويكتبه في سجل التعديلات. الحذف يسجَّل من ClientService قبل تنفيذه.
 */
class ClientObserver
{
    public function __construct(private ClientAuditLogger $audit) {}

    public function created(Client $client): void
    {
        $this->audit->record($client, 'created', null, $this->audit->snapshot(
            $client,
            ['name', 'phone_code', 'phone', 'email', 'stage_id', 'agent_id', 'preferred_contact', 'nationality', 'social_status', 'household_size', 'workplace', 'notes'],
        ));
    }

    public function updated(Client $client): void
    {
        $changes = $this->audit->changes($client, $client->getChanges());

        if ($changes) {
            $this->audit->record($client, 'updated', null, $changes);
        }
    }
}
