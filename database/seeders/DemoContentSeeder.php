<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\Review;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبية غنية لعرض شكل الموقع (صور مولّدة محلياً بـ GD).
 * التشغيل: php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    /**
     * فيديوهات تجريبية — أفلام Blender المفتوحة، مسموح تضمينها (embed).
     * لا تستخدم dQw4w9WgXcQ: التضمين معطّل عليه فيظهر "Video unavailable".
     */
    private const DEMO_VIDEOS = ['aqz-KE-bpKQ', 'eRsGyueVLvQ', 'R6MlUcmOul8', 'Y-rmzh0PI3c'];

    private string $disk;

    public function run(): void
    {
        $this->disk = storage_path('app/public');
        File::ensureDirectoryExists($this->disk.'/properties');
        File::ensureDirectoryExists($this->disk.'/agents');

        $agents = $this->agents();
        $owner = PropertyOwner::first() ?? PropertyOwner::create(['name' => 'مالك تجريبي', 'phone' => '+96590000000']);
        $this->properties($agents, $owner);
        $this->testimonialAvatars();
        $this->aboutPage();

        $this->command->info('تم إنشاء البيانات التجريبية.');
    }

    // ===================== الوكلاء =====================
    private function agents(): array
    {
        $data = [
            ['name' => 'أحمد العبدالله', 'email' => 'ahmad@alam.com', 'job' => 'مستشار عقاري أول', 'phone' => '+965 9797 1001', 'c' => [17, 16, 51],
                'bio' => 'خبرة واسعة في السوق العقاري الكويتي، متخصص في العقارات الفاخرة والتجارية. أتم أكثر من 200 صفقة ناجحة خلال مسيرته المهنية في علم العقارية.'],
            ['name' => 'نورة الرشيد', 'email' => 'noura@alam.com', 'job' => 'مستشارة عقارية', 'phone' => '+965 9797 1002', 'c' => [37, 36, 99],
                'bio' => 'متخصصة في الشقق السكنية والمفروشة داخل مدينة الكويت والسالمية، تهتم بتفاصيل العميل وتوفير أنسب الخيارات.'],
            ['name' => 'خالد البدر', 'email' => 'khaled@alam.com', 'job' => 'مدير مبيعات', 'phone' => '+965 9797 1003', 'c' => [70, 66, 160],
                'bio' => 'يقود فريق المبيعات ويشرف على الصفقات الكبرى والاستثمارات العقارية في مختلف مناطق الكويت.'],
            ['name' => 'فاطمة الحربي', 'email' => 'fatma@alam.com', 'job' => 'مستشارة استثمار عقاري', 'phone' => '+965 9797 1004', 'c' => [184, 151, 0],
                'bio' => 'تساعد المستثمرين على اختيار الفرص العقارية ذات العائد الأفضل، مع دراسة دقيقة للسوق.'],
        ];

        $agents = [];
        foreach ($data as $i => $d) {
            $avatar = $this->tmpAvatar('agent-'.($i + 1), mb_substr($d['name'], 0, 1), $d['c']);

            $u = User::updateOrCreate(
                ['email' => $d['email']],
                [
                    'name' => $d['name'],
                    'password' => Hash::make('password'),
                    'phone' => $d['phone'],
                    'job_title' => $d['job'],
                    'is_agent' => true,
                    'status' => 'active',
                    'bio' => ['ar' => $d['bio'], 'en' => $d['bio']],
                    'languages' => ['ar', 'en'],
                    'response_time' => 'أقل من ساعة',
                ]
            );
            $u->clearMediaCollection('avatar');
            $u->addMedia($avatar)->toMediaCollection('avatar');

            if (! $u->hasRole('sales-agent')) {
                $u->assignRole('sales-agent');
            }

            // تقييمات الوكيل
            if ($u->reviews()->count() === 0) {
                foreach ([['محمد الصباح', 5, 'تعامل راقٍ ومتابعة ممتازة حتى إتمام الصفقة.'], ['ريم العجمي', 5, 'ساعدني في إيجاد الوحدة المناسبة بسرعة.'], ['عبدالله الفهد', 4, 'خبرة واضحة بالسوق وردود سريعة.']] as $rv) {
                    Review::create([
                        'reviewable_type' => User::class, 'reviewable_id' => $u->id,
                        'reviewer_name' => $rv[0], 'rating' => $rv[1], 'comment' => $rv[2], 'is_published' => true,
                    ]);
                }
            }
            $agents[] = $u;
        }

        return $agents;
    }

    // ===================== العقارات =====================
    private function properties(array $agents, PropertyOwner $owner): void
    {
        $amenities = Amenity::pluck('id')->all();

        // [عنوان, منطقة, تصنيف, نوع وحدة, غرض, سعر, فترة, غرف, حمامات, مساحة, مميز, تقييم, ألوان الصورة]
        $rows = [
            ['فيلا فاخرة مع مسبح خاص', 8, 1, 2, 'sale', 285000, null, 6, 5, 450, true, 4.9, [[126, 178, 222], [238, 226, 205]]],
            ['شقة عصرية في السالمية', 3, 1, 1, 'rent', 850, 'monthly', 3, 2, 180, true, 4.6, [[150, 196, 230], [242, 236, 222]]],
            ['دور أرضي واسع في الجابرية', 8, 1, 4, 'rent', 700, 'monthly', 4, 3, 260, false, 4.4, [[118, 160, 205], [232, 222, 206]]],
            ['شقة مفروشة بإطلالة بحرية', 1, 3, 1, 'rent', 1200, 'monthly', 2, 2, 130, true, 4.8, [[141, 199, 235], [246, 240, 224]]],
            ['مكتب تجاري في برج راقٍ', 1, 2, 6, 'rent', 2500, 'monthly', 0, 2, 320, false, 4.5, [[104, 140, 190], [226, 220, 212]]],
            ['محل تجاري على شارع رئيسي', 2, 2, 7, 'rent', 1800, 'monthly', 0, 1, 95, false, 4.2, [[132, 172, 214], [236, 228, 210]]],
            ['فيلا عائلية في بيان', 10, 1, 2, 'sale', 420000, null, 7, 6, 600, true, 4.9, [[122, 182, 226], [240, 230, 210]]],
            ['منزل مستقل في الفنطاس', 9, 1, 3, 'sale', 310000, null, 5, 4, 500, false, 4.3, [[136, 186, 220], [234, 226, 208]]],
            ['شقة استوديو مفروشة', 3, 3, 1, 'rent', 450, 'monthly', 1, 1, 65, false, 4.1, [[158, 202, 232], [244, 238, 226]]],
            ['أرض استثمارية في الأحمدي', 5, 2, 5, 'sale', 195000, null, 0, 0, 1000, false, 4.0, [[148, 190, 226], [230, 224, 210]]],
        ];

        $desc = 'عقار مميز يقع في موقع استراتيجي قريب من الخدمات والمرافق الحيوية. يوفر تجربة سكنية استثنائية بتشطيبات عالية الجودة ومساحات مدروسة بعناية. العقار مجهز بالكامل بأحدث التقنيات والأثاث العصري، مثالي للعائلات الباحثة عن الراحة والخصوصية.';
        $specs = "المساحة الإجمالية: مدروسة بعناية لتناسب احتياجات العائلة.\nالتشطيبات: أرضيات رخام، دهانات حديثة، ونوافذ عازلة للصوت.\nالخدمات: قريب من المدارس والمجمعات التجارية والمستشفيات.\nالتسليم: جاهز للسكن الفوري.";

        foreach ($rows as $i => $r) {
            [$title, $areaId, $catId, $unitId, $purpose, $price, $period, $bed, $bath, $size, $featured, $rating, $colors] = $r;

            $ref = 'ALM-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $agent = $agents[$i % count($agents)];

            $p = Property::updateOrCreate(['reference_code' => $ref], [
                'title' => ['ar' => $title, 'en' => $title],
                'short_description' => ['ar' => 'فرصة مميزة بموقع حيوي وتشطيب راقٍ.', 'en' => 'A great opportunity in a prime location.'],
                'description' => ['ar' => $desc, 'en' => $desc],
                'specifications' => ['ar' => $specs, 'en' => $specs],
                'area_id' => $areaId, 'category_id' => $catId, 'unit_type_id' => $unitId,
                'purpose' => $purpose, 'price' => $price, 'price_period' => $period,
                'status_id' => 1, 'owner_id' => $owner->id, 'agent_id' => $agent->id,
                'bedrooms' => $bed, 'bathrooms' => $bath, 'area_size' => $size,
                'block' => (string) (($i % 9) + 1), 'street' => (string) (600 + $i * 7), 'building' => (string) (($i % 12) + 1),
                'video_url' => $i % 3 === 0 ? 'https://www.youtube.com/watch?v='.self::DEMO_VIDEOS[intdiv($i, 3) % count(self::DEMO_VIDEOS)] : null,
                'is_featured' => $featured, 'rating' => $rating, 'reviews_count' => 5 + $i,
            ]);

            // صور: غلاف + 4 صور معرض (media library)
            $p->clearMediaCollection('cover');
            $p->clearMediaCollection('gallery');
            $p->addMedia($this->tmpPhoto($ref.'-cover', $colors[0], $colors[1], $i * 13 + 1))->toMediaCollection('cover');

            for ($k = 1; $k <= 4; $k++) {
                $p->addMedia($this->tmpPhoto($ref.'-'.$k, $colors[0], $colors[1], $i * 13 + $k + 1))->toMediaCollection('gallery');
            }

            // مرافق
            if ($amenities) {
                $p->amenities()->sync(collect($amenities)->shuffle()->take(6)->all());
            }

            // تقييمات العقار
            if ($p->reviews()->count() === 0) {
                foreach ([['سالم المطيري', 5, 'الموقع ممتاز والتشطيب أفضل مما توقعت.'], ['هدى الخالد', 4, 'مساحات مريحة وخدمات قريبة.']] as $rv) {
                    Review::create([
                        'reviewable_type' => Property::class, 'reviewable_id' => $p->id,
                        'reviewer_name' => $rv[0], 'rating' => $rv[1], 'comment' => $rv[2], 'is_published' => true,
                    ]);
                }
            }
        }
    }

    // ===================== صور آراء العملاء =====================
    private function testimonialAvatars(): void
    {
        $palette = [[17, 16, 51], [70, 66, 160], [184, 151, 0], [31, 157, 87], [37, 36, 99]];
        foreach (Testimonial::all()->values() as $i => $ts) {
            $ts->clearMediaCollection('avatar');
            $ts->addMedia($this->tmpAvatar('client-'.($i + 1), mb_substr($ts->name, 0, 1), $palette[$i % count($palette)]))->toMediaCollection('avatar');
        }
    }

    // ===================== محتوى صفحة «من نحن» =====================
    private function aboutPage(): void
    {
        $page = \App\Models\Page::firstOrCreate(['slug' => 'about'], ['name' => 'من نحن']);

        // صورة القصة
        File::ensureDirectoryExists($this->disk.'/about');
        $storyImg = 'about/story.png';
        $this->illustration($this->disk.'/'.$storyImg);

        $storyDesc = 'في عالم العقارية نؤمن بأن العثور على العقار المناسب لا يقتصر على البحث عن منزل أو مقر عمل، بل هو خطوة مهمة نحو بناء مستقبل أفضل وتحقيق تطلعات عملائنا. ومن هذا المنطلق، كانت رؤيتنا إنشاء منصة عقارية حديثة تجمع بين سهولة الاستخدام، والمصداقية، والابتكار، لتقديم تجربة استثنائية تلبي احتياجات الأفراد والمستثمرين على حد سواء.'."\n\n".
            'نسعى إلى توفير مجموعة متنوعة من العقارات السكنية، والتجارية، والمفروشة في مختلف المناطق، مع عرض معلومات دقيقة، وصور واضحة، وتفاصيل متكاملة تساعد عملاءنا على اتخاذ قراراتهم بثقة. كما نحرص على تطوير خدماتنا باستمرار من خلال تبنّي أحدث التقنيات الرقمية التي تجعل رحلة البحث عن العقار أكثر سرعة وسهولة وفعالية.';

        $this->section($page, 'hero', 0, [],
            ['badge' => 'من نحن', 'title' => 'شريكك الموثوق في عالم العقارات',
                'description' => 'نُعد علم العقارية وجهتك الموثوقة لاستكشاف أفضل الفرص العقارية في الكويت. نلتزم بتقديم خدمات عقارية عالية الجودة، مدعومة بفريق متخصص وخبرة واسعة، لتوفير تجربة سلسة تلبي احتياجات الأفراد والمستثمرين على حد سواء.'],
            ['badge' => 'About Us', 'title' => 'Your trusted partner in real estate',
                'description' => 'Alam Realestate is your trusted destination for the best property opportunities in Kuwait, delivering high-quality services backed by a specialized team.']
        );

        $stats = [
            ['number' => '500', 'title' => ['ar' => 'عميل سعيد', 'en' => 'Happy clients'],
                'description' => ['ar' => 'سعادتكم كانت هدفنا، ونجاحنا الحقيقي يُقاس بابتسامة وصلت إلى 500 عميل.', 'en' => 'Your satisfaction is our goal — measured by 500 smiles.']],
            ['number' => '+100', 'title' => ['ar' => 'رضا العملاء', 'en' => 'Client satisfaction'],
                'description' => ['ar' => 'ثقة أكثر من 100 عميل كانت دافعنا للاستمرار في تقديم الأفضل.', 'en' => 'The trust of 100+ clients drives us to keep delivering our best.']],
        ];
        $this->section($page, 'story', 1, ['image' => $storyImg, 'stats' => $stats],
            ['title' => 'بدأت رحلتنا بشغف نحو عالم العقارات', 'description' => $storyDesc],
            ['title' => 'Our journey began with a passion for real estate', 'description' => $storyDesc]
        );

        $values = [
            ['icon' => 'ShieldCheck', 'title' => ['ar' => 'قيمنا', 'en' => 'Our values'],
                'description' => ['ar' => 'الأمانة والشفافية والتميّز في الخدمة أسس ثابتة نلتزم بها في كل تعاملاتنا مع عملائنا وشركائنا.', 'en' => 'Integrity, transparency and service excellence guide every interaction.']],
            ['icon' => 'Medal', 'title' => ['ar' => 'رسالتنا', 'en' => 'Our mission'],
                'description' => ['ar' => 'تقديم خدمة عقارية متكاملة تعتمد على الشفافية والمصداقية، وتمكين عملائنا من اتخاذ قراراتهم بثقة.', 'en' => 'Deliver complete, transparent property services that empower confident decisions.']],
            ['icon' => 'TrendUp', 'title' => ['ar' => 'رؤيتنا', 'en' => 'Our vision'],
                'description' => ['ar' => 'أن نكون المنصة العقارية الأولى في منطقة الخليج، نربط الباحثين بأفضل الفرص بشفافية وثقة.', 'en' => 'To be the Gulf’s leading property platform connecting seekers to the best opportunities.']],
        ];
        $this->section($page, 'values', 2, ['items' => $values],
            ['title' => 'قيمنا التي نعتز بها', 'subtitle' => 'نؤمن بأن الثقة والشفافية والاحترافية هي الأساس في كل ما نقدمه، ونسعى دائماً لتوفير تجربة عقارية موثوقة تلبي احتياجات عملائنا بأعلى معايير الجودة.'],
            ['title' => 'Values we are proud of', 'subtitle' => 'Trust, transparency and professionalism are the foundation of everything we deliver.']
        );

        $this->section($page, 'team', 3, [],
            ['title' => 'عائلة علم العقارية', 'subtitle' => 'وراء كل نجاح فريق يعمل بشغف واحترافية. نفخر بامتلاك نخبة من المستشارين والمتخصصين الذين يكرّسون خبراتهم لتقديم تجربة عقارية موثوقة، ومساعدة عملائنا في الوصول إلى أفضل الفرص بكل ثقة.'],
            ['title' => 'The Alam family', 'subtitle' => 'Behind every success is a passionate, professional team of expert advisors.']
        );
    }

    /** كتابة قسم CMS بنفس أسلوب WebsiteController::put */
    private function section(\App\Models\Page $page, string $key, int $order, array $shared, array $ar, array $en): void
    {
        $s = $page->sections()->firstOrCreate(['key' => $key], ['sort_order' => $order]);
        $s->setTranslation('content', 'ar', array_merge($shared, $ar));
        $s->setTranslation('content', 'en', array_merge($shared, $en));
        $s->save();
    }

    // ===================== توليد الصور =====================
    /** مشهد عقاري مبسّط: سماء متدرجة + مبانٍ + أرضية */
    private function photo(string $full, array $sky, array $ground, int $seed): void
    {
        $w = 900;
        $h = 650;
        $im = imagecreatetruecolor($w, $h);
        $horizon = (int) ($h * 0.72);

        // سماء متدرجة
        for ($y = 0; $y < $horizon; $y++) {
            $ratio = $y / $horizon;
            $c = imagecolorallocate($im,
                (int) ($sky[0] + (255 - $sky[0]) * $ratio * 0.75),
                (int) ($sky[1] + (255 - $sky[1]) * $ratio * 0.75),
                (int) ($sky[2] + (255 - $sky[2]) * $ratio * 0.6));
            imagefilledrectangle($im, 0, $y, $w, $y, $c);
        }
        // أرضية
        for ($y = $horizon; $y < $h; $y++) {
            $ratio = ($y - $horizon) / max(1, $h - $horizon);
            $c = imagecolorallocate($im,
                (int) ($ground[0] * (1 - $ratio * 0.25)),
                (int) ($ground[1] * (1 - $ratio * 0.25)),
                (int) ($ground[2] * (1 - $ratio * 0.25)));
            imagefilledrectangle($im, 0, $y, $w, $y, $c);
        }
        // شمس
        $sun = imagecolorallocatealpha($im, 255, 244, 214, 60);
        imagefilledellipse($im, (int) ($w * 0.78), (int) ($horizon * 0.32), 190, 190, $sun);

        // مبانٍ
        mt_srand($seed);
        $x = -20;
        while ($x < $w) {
            $bw = mt_rand(85, 165);
            $bh = mt_rand((int) ($h * 0.18), (int) ($h * 0.5));
            $top = $horizon - $bh;
            $shade = mt_rand(0, 40);
            $body = imagecolorallocate($im, 232 - $shade, 228 - $shade, 222 - $shade);
            imagefilledrectangle($im, $x, $top, $x + $bw, $horizon + 12, $body);
            // نوافذ
            $win = imagecolorallocate($im, 251, 211, 0);
            $dark = imagecolorallocate($im, 120, 126, 140);
            for ($wy = $top + 16; $wy < $horizon - 18; $wy += 30) {
                for ($wx = $x + 14; $wx < $x + $bw - 20; $wx += 28) {
                    imagefilledrectangle($im, $wx, $wy, $wx + 14, $wy + 18, mt_rand(0, 3) ? $win : $dark);
                }
            }
            $x += $bw + mt_rand(6, 20);
        }

        imagepng($im, $full, 8);
        imagedestroy($im);
    }

    /** رسمة توضيحية: خلفية فاتحة + تجمّع أبراج بنوافذ ذهبية */
    private function illustration(string $full): void
    {
        $w = 900;
        $h = 700;
        $im = imagecreatetruecolor($w, $h);

        // خلفية متدرجة فاتحة
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / $h;
            $c = imagecolorallocate($im,
                (int) (239 + 12 * $ratio),
                (int) (240 + 11 * $ratio),
                (int) (250 - 4 * $ratio));
            imagefilledrectangle($im, 0, $y, $w, $y, $c);
        }
        // هالة ناعمة
        imagefilledellipse($im, (int) ($w / 2), (int) ($h * 0.45), 620, 520, imagecolorallocatealpha($im, 255, 255, 255, 75));

        $ground = (int) ($h * 0.82);
        // ظل أرضي
        imagefilledellipse($im, (int) ($w / 2), $ground + 26, 640, 90, imagecolorallocatealpha($im, 29, 32, 38, 108));

        // أبراج: [عرض, ارتفاع, إزاحة]
        $towers = [[150, 300, -250], [190, 430, -60], [140, 250, 150], [110, 200, 285]];
        foreach ($towers as $i => [$bw, $bh, $dx]) {
            $x = (int) ($w / 2 + $dx - $bw / 2);
            $top = $ground - $bh;
            $shade = $i * 8;
            imagefilledrectangle($im, $x, $top, $x + $bw, $ground, imagecolorallocate($im, 226 - $shade, 224 - $shade, 234 - $shade));
            // واجهة أفتح
            imagefilledrectangle($im, $x, $top, $x + (int) ($bw * 0.32), $ground, imagecolorallocate($im, 240 - $shade, 239 - $shade, 246 - $shade));
            // نوافذ
            $gold = imagecolorallocate($im, 251, 211, 0);
            $navy = imagecolorallocate($im, 150, 154, 175);
            for ($wy = $top + 20; $wy < $ground - 24; $wy += 34) {
                for ($wx = $x + 16; $wx < $x + $bw - 22; $wx += 32) {
                    imagefilledrectangle($im, $wx, $wy, $wx + 16, $wy + 20, mt_rand(0, 2) ? $gold : $navy);
                }
            }
            // سطح
            imagefilledrectangle($im, $x - 6, $top - 10, $x + $bw + 6, $top, imagecolorallocate($im, 17, 16, 51));
        }

        imagepng($im, $full, 8);
        imagedestroy($im);
    }

    /** صورة رمزية دائرية بحرف الاسم */
    private function avatar(string $full, string $letter, array $rgb): void
    {
        $s = 240;
        $im = imagecreatetruecolor($s, $s);
        for ($y = 0; $y < $s; $y++) {
            $ratio = $y / $s;
            $c = imagecolorallocate($im,
                (int) min(255, $rgb[0] + 70 * $ratio),
                (int) min(255, $rgb[1] + 70 * $ratio),
                (int) min(255, $rgb[2] + 70 * $ratio));
            imagefilledrectangle($im, 0, $y, $s, $y, $c);
        }
        // كتلة "كتف" لإيحاء الشخص
        $light = imagecolorallocatealpha($im, 255, 255, 255, 100);
        imagefilledellipse($im, (int) ($s / 2), (int) ($s * 0.92), (int) ($s * 0.85), (int) ($s * 0.6), $light);
        imagefilledellipse($im, (int) ($s / 2), (int) ($s * 0.42), (int) ($s * 0.34), (int) ($s * 0.34), $light);

        imagepng($im, $full, 8);
        imagedestroy($im);
    }
    /** يولّد صورة مؤقّتة ويعيد مسارها — media library ينسخها ويحذف المؤقّت لاحقاً */
    private function tmpPath(string $name): string
    {
        $dir = storage_path('app/demo-tmp');
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.$name.'.png';
    }

    private function tmpPhoto(string $name, array $a, array $b, int $seed): string
    {
        $path = $this->tmpPath($name);
        $this->photo($path, $a, $b, $seed);

        return $path;
    }

    private function tmpAvatar(string $name, string $letter, array $rgb): string
    {
        $path = $this->tmpPath($name);
        $this->avatar($path, $letter, $rgb);

        return $path;
    }
}
