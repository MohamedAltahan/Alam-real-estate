<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MarketingSourceType extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = ['is_active' => 'boolean'];

    public function sources(): HasMany
    {
        return $this->hasMany(MarketingSource::class, 'type_id');
    }
}
