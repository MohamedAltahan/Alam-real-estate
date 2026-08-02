<?php

namespace App\Models;

use App\Concerns\InteractsWithWebImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

class PageSection extends Model implements HasMedia
{
    use HasTranslations;
    use InteractsWithWebImages;

    protected $fillable = ['page_id', 'key', 'sort_order', 'is_visible', 'content'];

    /** المحتوى المرن قابل للترجمة — لكل لغة كائن محتوى كامل */
    public array $translatable = ['content'];

    protected $casts = ['is_visible' => 'boolean'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
