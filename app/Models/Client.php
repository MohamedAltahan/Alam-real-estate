<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'area_id', 'type_id',
        'stage_id', 'agent_id', 'source_id', 'rating', 'notes',
    ];

    protected $casts = ['rating' => 'integer'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ClientType::class, 'type_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ClientStage::class, 'stage_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class, 'source_id');
    }

    /** سجل التواصل — كل مكالمة/مقابلة */
    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class)->latest('occurred_at');
    }

    /** عقارات العميل (تظهر في شاشته) */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'client_property')
            ->withPivot('relation', 'notes')
            ->withTimestamps();
    }
}
