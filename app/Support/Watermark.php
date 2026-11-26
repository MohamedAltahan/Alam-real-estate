<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\Unit;
use Spatie\MediaLibrary\Conversions\Conversion;

/**
 * العلامة المائية على صور العقارات (تبويب «واتر مارك» في لوحة التحكم).
 *
 * تُطبَّق داخل تحويل «web» نفسه بعد التصغير إلى ارتفاع ١٠٨٠، فتظهر في كل مكان
 * تُعرض فيه صورة العقار. الملف الأصلي يبقى بلا علامة حتى يمكن إعادة توليد
 * الصور بعد تغيير الشعار أو مكانه أو حجمه.
 */
final class Watermark
{
    public const GROUP = 'watermark';

    public const KEY = 'config';

    /** الشعار يُحفظ محلياً على قرص public (مكتبة الصور تحتاج مساراً على الجهاز) */
    public const DISK = 'public';

    public const DIR = 'watermark';

    /** مجموعات صور العقار التي تأخذ العلامة */
    public const COLLECTIONS = ['cover', 'gallery'];

    /** شفافية ثابتة — تظهر العلامة بوضوح دون أن تغطي تفاصيل العقار */
    public const OPACITY = 70;

    /** المسافة من حافة الصورة كنسبة من أبعادها */
    public const PADDING_PERCENT = 3;

    /** المواضع التسعة — مادية (يسار/يمين الصورة نفسها) وليست حسب اتجاه اللغة */
    public const POSITIONS = [
        'top-left' => 'أعلى اليسار',
        'top' => 'أعلى المنتصف',
        'top-right' => 'أعلى اليمين',
        'left' => 'منتصف اليسار',
        'center' => 'المنتصف',
        'right' => 'منتصف اليمين',
        'bottom-left' => 'أسفل اليسار',
        'bottom' => 'أسفل المنتصف',
        'bottom-right' => 'أسفل اليمين',
    ];

    /** الأحجام الثلاثة: عرض العلامة كنسبة من عرض الصورة */
    public const SIZES = [
        'small' => ['label' => 'صغير', 'percent' => 15],
        'medium' => ['label' => 'متوسط', 'percent' => 25],
        'large' => ['label' => 'كبير', 'percent' => 35],
    ];

    public const DEFAULTS = [
        'enabled' => false,
        'path' => null,
        'position' => 'bottom-right',
        'size' => 'medium',
    ];

    /**
     * الإعدادات الحالية — مرة واحدة في الطلب: مكتبة الصور تسجّل التحويلات
     * مع كل رابط صورة، فبدونها يصير كل كارت عقار استعلاماً إضافياً.
     *
     * @return array{enabled: bool, path: ?string, position: string, size: string}
     */
    public static function settings(): array
    {
        return once(function () {
            $saved = Setting::get(self::GROUP, self::KEY);
            $config = array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);

            if (! array_key_exists($config['position'], self::POSITIONS)) {
                $config['position'] = self::DEFAULTS['position'];
            }
            if (! array_key_exists($config['size'], self::SIZES)) {
                $config['size'] = self::DEFAULTS['size'];
            }
            $config['enabled'] = (bool) $config['enabled'];

            return $config;
        });
    }

    public static function save(array $config): void
    {
        Setting::set(self::GROUP, self::KEY, array_merge(self::DEFAULTS, $config));
        Once::flush();
    }

    /** مفعّلة وعليها شعار موجود فعلاً على القرص */
    public static function active(): bool
    {
        $config = self::settings();

        return $config['enabled'] && self::logoPath() !== null;
    }

    public static function logoPath(): ?string
    {
        $path = self::settings()['path'];

        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($path);
    }

    public static function logoUrl(): ?string
    {
        return self::logoPath() ? Storage::disk(self::DISK)->url(self::settings()['path']) : null;
    }

    public static function sizePercent(?string $size = null): int
    {
        return self::SIZES[$size ?? self::settings()['size']]['percent'] ?? self::SIZES['medium']['percent'];
    }

    /** يضيف العلامة إلى تحويل الصورة (بعد التصغير حتى تُحسب النسب على المقاس النهائي) */
    public static function apply(Conversion $conversion): Conversion
    {
        if (! self::active()) {
            return $conversion;
        }

        $config = self::settings();

        // معاملات موضعية بترتيب Spatie\Image::watermark — الارتفاع 0 فيُحسب تلقائياً بنسبة الشعار
        return $conversion->watermark(
            self::logoPath(),
            AlignPosition::from($config['position']),
            self::PADDING_PERCENT,
            self::PADDING_PERCENT,
            Unit::Percent,
            self::sizePercent($config['size']),
            Unit::Percent,
            0,
            Unit::Pixel,
            Fit::Contain,
            self::OPACITY,
        );
    }
}
