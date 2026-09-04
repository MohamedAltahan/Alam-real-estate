<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** معاينة عقار للعميل: عقار + موعد + حضوري + النتيجة */
class ClientViewing extends Model
{
    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_CHOSEN = 'chosen';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOMES = [self::OUTCOME_PENDING, self::OUTCOME_CHOSEN, self::OUTCOME_REJECTED];

    protected $fillable = [
        'client_id', 'property_id', 'scheduled_at', 'in_person',
        'outcome', 'outcome_at', 'notes', 'reminded_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'outcome_at' => 'datetime',
        'reminded_at' => 'datetime',
        'in_person' => 'boolean',
    ];

    protected $attributes = [
        'in_person' => true,
        'outcome' => self::OUTCOME_PENDING,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_PENDING);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now());
    }

    public function isDecided(): bool
    {
        return $this->outcome !== self::OUTCOME_PENDING;
    }
}
