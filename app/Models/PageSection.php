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

    /** مفاتيح أقسام الصفحة التي أخفاها المدير من «إدارة الموقع» (القسم غير المحفوظ بعد يُعدّ ظاهراً) */
    public static function hiddenKeys(string $page): array
    {
        return static::where('is_visible', false)
            ->whereHas('page', fn ($q) => $q->where('slug', $page))
            ->pluck('key')->all();
    }
}
