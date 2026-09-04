<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** سطر في سجل تعديلات العميل: من عدّل، متى، ماذا (القيمة القديمة والجديدة) */
class ClientAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['client_id', 'user_id', 'action', 'subject_type', 'subject_id', 'changes', 'created_at'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
