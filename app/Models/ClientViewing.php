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

    /** انتهى عقد الإيجار وترك العميل العقار — يحرّر العقار لعملاء آخرين ويبقى الاختيار محسوباً في التقرير */
    public const OUTCOME_VACATED = 'vacated';

    public const OUTCOMES = [self::OUTCOME_PENDING, self::OUTCOME_CHOSEN, self::OUTCOME_REJECTED, self::OUTCOME_VACATED];

    protected $fillable = [
        'client_id', 'property_id', 'scheduled_at', 'in_person',
        'outcome', 'outcome_at', 'contract_ends_at', 'notes', 'reminded_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'outcome_at' => 'datetime',
        'contract_ends_at' => 'date',
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

    /** معاينات اختار فيها العميل العقار (يسكنه الآن) — تجعل العقار «مشغولاً» */
    public function scopeChosen(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_CHOSEN);
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
