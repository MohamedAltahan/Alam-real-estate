<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * محتوى تجريبي لتبويب «الصفحة الرئيسية» في إدارة الموقع — نصوص AR/EN
 * وصور مولّدة محلياً تُرفع عبر media library (فتُحوَّل إلى ارتفاع 1080).
 *
 * php artisan db:seed --class=DemoHomepageSeeder
 */
class DemoHomepageSeeder extends Seeder
{
    private string $tmp;

    public function run(): void
    {
        $this->tmp = storage_path('app/demo-tmp');
        File::ensureDirectoryExists($this->tmp);

        $home = Page::firstOrCreate(['slug' => 'home'], ['name' => 'الرئيسية']);

        $this->hero($home);
        $this->featured($home);
        $this->areas($home);
        $this->videos($home);
        $this->whyUs($home);
        $this->testimonialsHeader($home);
        $this->cta($home);

        File::deleteDirectory($this->tmp);
        $this->command?->info('تم ملء محتوى الصفحة الرئيسية ✔');
    }

    private function hero(Page $home): void
    {
        $s = $this->section($home, 'hero', 0);
        $s->clearMediaCollection('images');

        foreach ([[38, 52, 110], [24, 40, 95], [52, 66, 130]] as $i => $rgb) {
            $s->addMedia($this->skyline("hero-$i", $rgb))->toMediaCollection('images');
        }

        $this->fill($s,
            ['stats' => ['properties' => '500+', 'clients' => '1,200+', 'areas' => '15+'], 'rotate_seconds' => 6],
            ['badge' => 'المنصة العقارية الأولى في الكويت', 'title' => 'اعثر على العقار المثالي لك',
                'subtitle' => 'في أفضل مناطق الكويت',
                'description' => 'أكثر من 500 عقار متنوع من سكني وتجاري ومفروش، مع فريق خبراء متخصصين يرافقك في كل خطوة حتى تستلم مفتاحك.'],
            ['badge' => "Kuwait's leading real-estate platform", 'title' => 'Find your perfect property',
                'subtitle' => 'in the best areas of Kuwait',
                'description' => 'Over 500 residential, commercial and furnished listings, with a specialist team beside you at every step.'],
        );
    }

    private function featured(Page $home): void
    {
        $s = $this->section($home, 'featured', 1);
        $s->clearMediaCollection('image');
        $s->addMedia($this->badge('featured'))->toMediaCollection('image');

        $this->fill($s, [],
            ['title' => 'عروض عقارية مميزة', 'description' => 'تصفّح أحدث العروض المختارة بعناية على العقارات السكنية والتجارية والمفروشة.'],
            ['title' => 'Featured listings', 'description' => 'Browse our latest hand-picked residential, commercial and furnished offers.'],
        );
    }

    private function areas(Page $home): void
    {
        $s = $this->section($home, 'areas', 2);
        $s->media()->where('collection_name', 'like', 'area-%')->get()->each->delete();

        $picks = Area::where('is_active', true)->orderBy('sort_order')->take(6)->get();
        $items = [];

        foreach ($picks as $i => $area) {
            $collection = 'area-demo'.$i;
            $s->addMedia($this->skyline("area-$i", [46 + $i * 12, 70 + $i * 8, 120 + $i * 15]))->toMediaCollection($collection);

            $items[] = [
                'area_id' => $area->id,
                'count' => (string) $area->properties()->count(),
                'collection' => $collection,
            ];
        }

        $this->fill($s, ['items' => $items],
            ['title' => 'أفضل المناطق', 'description' => 'تغطية جغرافية واسعة تشمل أبرز مناطق الكويت السكنية والتجارية.'],
            ['title' => 'Top areas', 'description' => 'Wide coverage across Kuwait’s key residential and commercial districts.'],
        );
    }

    private function videos(Page $home): void
    {
        $this->fill($this->section($home, 'videos', 3), [],
            ['title' => 'تعريف الخدمات المقدمة', 'description' => 'جولات مصوّرة داخل عقاراتنا تعطيك تصوراً حقيقياً قبل المعاينة.'],
            ['title' => 'Guided video tours', 'description' => 'Filmed walkthroughs that give you a real feel before you visit.'],
        );
    }

    private function whyUs(Page $home): void
    {
        $items = [
            ['15+', 'ShieldCheck', 'خبرة موثوقة', 'Trusted expertise', 'أكثر من ١٥ سنة في السوق العقاري الكويتي بسجل حافل من الصفقات الناجحة.', 'Over 15 years in the Kuwaiti market with a solid track record.'],
            ['500+', 'Buildings', 'محفظة واسعة', 'Wide portfolio', 'تشكيلة كبيرة من العقارات السكنية والتجارية والمفروشة في كل المناطق.', 'A large mix of residential, commercial and furnished units everywhere.'],
            ['98%', 'Star', 'رضا العملاء', 'Client satisfaction', 'نسبة رضا عالية بشهادة عملائنا وتقييماتهم بعد إتمام الصفقات.', 'A high satisfaction rate backed by our clients’ own reviews.'],
            ['24/7', 'Headset', 'دعم متواصل', 'Always-on support', 'فريق خدمة العملاء متاح على مدار الساعة للرد على استفساراتك.', 'Our support team answers your questions around the clock.'],
            ['100%', 'FileText', 'شفافية كاملة', 'Full transparency', 'كل التفاصيل والأسعار معلنة بوضوح بلا رسوم مخفية.', 'Every detail and price is stated up front, with no hidden fees.'],
        ];

        $this->fill($this->section($home, 'why_us', 4),
            ['items' => collect($items)->map(fn ($i) => [
                'number' => $i[0], 'icon' => $i[1],
                'title' => ['ar' => $i[2], 'en' => $i[3]],
                'description' => ['ar' => $i[4], 'en' => $i[5]],
            ])->all()],
            ['title' => 'لماذا علم العقارية؟', 'description' => 'نجمع بين الخبرة الطويلة والتقنية الحديثة لنقدّم لك تجربة عقارية مريحة وواضحة.'],
            ['title' => 'Why Alam Realestate?', 'description' => 'Long experience and modern tooling combined into a clear, comfortable experience.'],
        );
    }

    private function testimonialsHeader(Page $home): void
    {
        $this->fill($this->section($home, 'testimonials', 5), [],
            ['title' => 'ماذا يقولون عنا', 'description' => 'آراء عملاء أتمّوا صفقاتهم معنا وشاركونا تجربتهم بصدق.'],
            ['title' => 'What they say about us', 'description' => 'Honest words from clients who closed their deals with us.'],
        );
    }

    private function cta(Page $home): void
    {
        $s = $this->section($home, 'cta', 6);
        $s->clearMediaCollection('image');
        $s->addMedia($this->skyline('cta', [30, 44, 100]))->toMediaCollection('image');

        $this->fill($s, [],
            ['badge' => 'هل أنت مستعد للبدء؟', 'title' => 'ابدأ رحلتك العقارية الآن',
                'description' => 'تواصل مع فريقنا اليوم ودعنا نساعدك في العثور على العقار المناسب أو تسويق عقارك بأفضل سعر.'],
            ['badge' => 'Ready to start?', 'title' => 'Begin your property journey today',
                'description' => 'Talk to our team and let us help you find the right property — or market yours at the best price.'],
        );
    }

    // ===== أدوات =====

    private function section(Page $page, string $key, int $order): PageSection
    {
        return $page->sections()->firstOrCreate(['key' => $key], ['sort_order' => $order]);
    }

    private function fill(PageSection $s, array $shared, array $ar, array $en): void
    {
        $s->setTranslation('content', 'ar', array_merge($shared, $ar));
        $s->setTranslation('content', 'en', array_merge($shared, $en));
        $s->save();
    }

    /** مشهد أفق مدينة بسيط بـ GD — بديل عن صور حقيقية في البيئة التجريبية */
    private function skyline(string $name, array $rgb): string
    {
        [$w, $h] = [1920, 1200];
        $im = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($im,
                (int) ($rgb[0] + (235 - $rgb[0]) * $t * 0.65),
                (int) ($rgb[1] + (225 - $rgb[1]) * $t * 0.6),
                (int) ($rgb[2] + (215 - $rgb[2]) * $t * 0.45));
            imageline($im, 0, $y, $w, $y, $c);
        }

        mt_srand(crc32($name));
        for ($x = 0; $x < $w; $x += 70) {
            $bh = mt_rand(220, 640);
            $shade = imagecolorallocatealpha($im, $rgb[0] - 12, $rgb[1] - 10, $rgb[2] - 8, mt_rand(20, 55));
            imagefilledrectangle($im, $x, $h - $bh, $x + mt_rand(46, 64), $h, $shade);
        }

        return $this->save($im, $name);
    }

    /** أيقونة/شارة توضيحية بسيطة */
    private function badge(string $name): string
    {
        $im = imagecreatetruecolor(600, 600);
        imagefill($im, 0, 0, imagecolorallocate($im, 17, 16, 51));
        imagefilledellipse($im, 300, 300, 380, 380, imagecolorallocate($im, 251, 211, 0));
        imagefilledrectangle($im, 230, 250, 370, 400, imagecolorallocate($im, 17, 16, 51));

        return $this->save($im, $name);
    }

    private function save($im, string $name): string
    {
        $path = $this->tmp.DIRECTORY_SEPARATOR.$name.'.jpg';
        imagejpeg($im, $path, 88);
        imagedestroy($im);

        return $path;
    }
}
