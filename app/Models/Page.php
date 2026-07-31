<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasTranslations;

    protected $fillable = ['slug', 'name', 'seo_title', 'seo_description', 'seo_keywords', 'is_active'];

    public array $translatable = ['seo_title', 'seo_description', 'seo_keywords'];

    protected $casts = ['is_active' => 'boolean'];

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }
}
