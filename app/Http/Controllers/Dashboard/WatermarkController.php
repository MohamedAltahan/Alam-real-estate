<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Support\Watermark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/** تبويب «واتر مارك»: شعار العلامة المائية ومكانه وحجمه على صور العقارات */
class WatermarkController extends Controller
{
    /** أقصى حجم لملف الشعار بالكيلوبايت (٢ ميجا) */
    private const MAX_LOGO_KB = 2048;

    /** أقل عرض للشعار — أصغر من كده يتكبّر ويطلع مغبّش على الصور العريضة */
    private const MIN_LOGO_WIDTH = 400;

    /** عدد الصور في كل دفعة تحديث — صغير حتى لا يتجاوز الطلب مهلة السيرفر */
    private const APPLY_BATCH = 5;

    public function index(): View
    {
        $sample = Property::whereHas('media', fn ($q) => $q->where('collection_name', 'cover'))
            ->latest('id')
            ->first();

        return view('dashboard.watermark.index', [
            'config' => Watermark::settings(),
            'logoUrl' => Watermark::logoUrl(),
            'positions' => Watermark::POSITIONS,
            'sizes' => Watermark::SIZES,
            'padding' => Watermark::PADDING_PERCENT,
            'opacity' => Watermark::OPACITY,
            // المعاينة على صورة عقار حقيقية بدون علامة (الأصل) — وإلا صورة الموقع العامة
            'sampleUrl' => $sample?->getFirstMediaUrl('cover') ?: asset('images/page-hero.jpg'),
            'imagesCount' => $this->propertyImages()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('watermark.edit'), 403);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'position' => ['required', Rule::in(array_keys(Watermark::POSITIONS))],
            'size' => ['required', Rule::in(array_keys(Watermark::SIZES))],
            'logo' => [
                'nullable', 'image', 'mimetypes:image/png,image/webp',
                'max:'.self::MAX_LOGO_KB,
                'dimensions:min_width='.self::MIN_LOGO_WIDTH,
            ],
            'logo_remove' => ['nullable', 'boolean'],
        ], [
            'logo.mimetypes' => 'الشعار لازم يكون PNG أو WebP بخلفية شفافة.',
            'logo.dimensions' => 'عرض الشعار لازم يكون '.self::MIN_LOGO_WIDTH.' بكسل على الأقل (المقترح ٨٠٠–١٠٠٠ بكسل).',
            'logo.max' => 'حجم الشعار لازم يكون أقل من ٢ ميجابايت.',
        ], [
            'logo' => 'الشعار',
            'position' => 'مكان العلامة',
            'size' => 'حجم العلامة',
        ]);

        $config = Watermark::settings();
        $disk = Storage::disk(Watermark::DISK);
        $oldPath = $config['path'];

        if ($request->hasFile('logo')) {
            $config['path'] = $request->file('logo')->store(Watermark::DIR, Watermark::DISK);
        } elseif ($request->boolean('logo_remove')) {
            $config['path'] = null;
        }

        $enabled = $request->boolean('enabled');
        if ($enabled && (! $config['path'] || ! $disk->exists($config['path']))) {
            throw ValidationException::withMessages(['logo' => 'ارفع شعار العلامة المائية أولاً قبل التفعيل.']);
        }

        Watermark::save([
            'enabled' => $enabled,
            'path' => $config['path'],
            'position' => $data['position'],
            'size' => $data['size'],
        ]);

        // الشعار القديم لم يعد مستخدماً — نحذفه بعد حفظ الجديد بنجاح
        if ($oldPath && $oldPath !== $config['path']) {
            $disk->delete($oldPath);
        }

        return redirect()->route('dashboard.watermark.index')->with('success', $enabled
            ? 'تم حفظ العلامة المائية — تُطبَّق على صور العقارات التي تُرفع من الآن. لتحديث الصور القديمة اضغط «تحديث الصور الحالية».'
            : 'تم حفظ الإعدادات — العلامة المائية متوقفة، والصور الجديدة تُرفع بدونها.');
    }

    /**
     * يعيد توليد صور العقارات الحالية بالإعدادات المحفوظة، دفعةً دفعة
     * (المتصفح يستدعيه بالتتابع بمؤشر after حتى done) — المشروع بلا queue worker.
     */
    public function apply(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('watermark.edit'), 403);

        $after = max(0, $request->integer('after'));
        @set_time_limit(120);

        $batch = $this->propertyImages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(self::APPLY_BATCH)
            ->get();

        $failed = 0;
        foreach ($batch as $media) {
            try {
                app(FileManipulator::class)->createDerivedFiles($media, [Property::WEB_CONVERSION]);
            } catch (Throwable $e) {
                // صورة أصلها مفقود أو تالف — نكمل الباقي ولا نوقف التحديث كله
                $failed++;
                Log::warning('Watermark regenerate failed', ['media_id' => $media->id, 'error' => $e->getMessage()]);
            }
        }

        $next = $batch->last()?->id ?? $after;
        $remaining = $this->propertyImages()->where('id', '>', $next)->count();

        return response()->json([
            'processed' => $batch->count(),
            'failed' => $failed,
            'next' => $next,
            'remaining' => $remaining,
            'done' => $remaining === 0,
        ]);
    }

    /** صور العقارات التي تأخذ العلامة (الغلاف والمعرض) */
    private function propertyImages()
    {
        return Media::query()
            ->where('model_type', (new Property)->getMorphClass())
            ->whereIn('collection_name', Watermark::COLLECTIONS);
    }
}
