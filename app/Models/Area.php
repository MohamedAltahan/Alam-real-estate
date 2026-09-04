<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Area extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'city_id', 'sort_order', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = ['is_active' => 'boolean'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** احتياجات العملاء التي تطلب هذه المنطقة */
    public function needs(): HasMany
    {
        return $this->hasMany(ClientPropertyNeed::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(PropertyOwner::class);
    }
}
