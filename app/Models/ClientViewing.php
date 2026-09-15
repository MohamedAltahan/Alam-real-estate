<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** معاينة عقار للعميل: عقار + موعد + حضوري + النتيجة */
class ClientViewing extends Model
{
    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_STUDYING = 'studying';

    /** العميل مهتم بالعقار — يحوّل العميل إلى مرحلة «ربح»؛ حالة العقار تُغيَّر يدوياً */
    public const OUTCOME_INTERESTED = 'interested';

    public const OUTCOME_NOT_INTERESTED = 'not_interested';

    public const OUTCOME_CANCELLED = 'cancelled';

    public const OUTCOMES = [
        self::OUTCOME_PENDING, self::OUTCOME_STUDYING, self::OUTCOME_INTERESTED,
        self::OUTCOME_NOT_INTERESTED, self::OUTCOME_CANCELLED,
    ];

    protected $fillable = [
        'client_id', 'property_id', 'scheduled_at', 'in_person',
        'outcome', 'outcome_at', 'notes', 'reminded_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'outcome_at' => 'datetime',
        'reminded_at' => 'datetime',
        'owner_notified_at' => 'datetime',
        'client_followed_up_at' => 'datetime',
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

    public function scopeInterested(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_INTERESTED);
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
