<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInteraction extends Model
{
    protected $fillable = [
        'client_id', 'user_id', 'type', 'notes', 'stage_id', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** الموظف اللي سجّل التفاعل */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** الحالة اللي اتحطّت بعد التفاعل */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ClientStage::class, 'stage_id');
    }
}
