<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSource extends Model
{
    protected $fillable = ['name', 'type_id', 'cost', 'status'];

    protected $casts = ['cost' => 'decimal:3'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(MarketingSourceType::class, 'type_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'source_id');
    }
}
