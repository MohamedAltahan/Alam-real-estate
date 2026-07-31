<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class PropertyStatus extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'key', 'color', 'sort_order', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = ['is_active' => 'boolean'];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'status_id');
    }
}
