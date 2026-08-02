<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PropertyOwner extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'area_id',
        'nationality', 'registered_address', 'status',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /** أحدث عقار للمالك — منه نعرف الوكيل المسؤول عنه في القائمة */
    public function latestProperty(): HasOne
    {
        return $this->hasOne(Property::class, 'owner_id')->latestOfMany();
    }
}
