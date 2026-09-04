<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Support\AuditDiff;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل كل تعديل يخص العميل: من عدّل، متى، القيمة القديمة والجديدة.
 * حساب الفرق نفسه في AuditDiff (مشترك مع سجل المهام).
 */
class ClientAuditLogger
{
    /**
     * @param  array<string, array{old:mixed,new:mixed}>  $changes
     */
    public function record(?Client $client, string $action, ?Model $subject = null, array $changes = []): ClientAuditLog
    {
        return ClientAuditLog::create([
            'client_id' => $client?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $newValues
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function changes(Model $model, array $newValues, array $ignore = ['created_at', 'updated_at']): array
    {
        return AuditDiff::changes($model, $newValues, $ignore);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function snapshot(Model $model, array $fields, bool $removed = false): array
    {
        return AuditDiff::snapshot($model, $fields, $removed);
    }
}
