<?php

namespace App\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * قاعدة الصور الموحّدة في النظام كله (spatie/laravel-medialibrary):
 *   · الحد الأقصى للرفع 6 ميجابايت للصورة
 *   · كل صورة تُحوَّل إلى ارتفاع 1080px مع الحفاظ على نسبة العرض/الارتفاع
 *   · الصيغ المسموحة: jpg · jpeg · png · webp
 *
 * أي موديل عليه صور: implements HasMedia + use InteractsWithWebImages.
 */
trait InteractsWithWebImages
{
    use InteractsWithMedia;

    /** اسم التحويل المعروض في الواجهات */
    public const WEB_CONVERSION = 'web';

    /** أقصى حجم للرفع بالكيلوبايت (٦ ميجا) */
    public const MAX_IMAGE_KB = 6144;

    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** قواعد التحقق الجاهزة لأي حقل صورة */
    public static function imageRules(bool $required = false): array
    {
        return array_filter([
            $required ? 'required' : 'nullable',
            'image',
            'mimetypes:'.implode(',', self::IMAGE_MIMES),
            'max:'.self::MAX_IMAGE_KB,
        ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::WEB_CONVERSION)
            // Fit::Max = لا يتجاوز الارتفاع 1080 ولا يقصّ الصورة — النسبة محفوظة
            ->fit(Fit::Max, 4096, 1080)
            ->keepOriginalImageFormat()
            ->quality(85)
            ->nonQueued();
    }

    /** رابط الصورة المحوَّلة مع رجوع آمن للأصل */
    public function imageUrl(string $collection, ?string $fallback = null): ?string
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return $fallback;
        }

        return $media->hasGeneratedConversion(self::WEB_CONVERSION)
            ? $media->getUrl(self::WEB_CONVERSION)
            : $media->getUrl();
    }
}
