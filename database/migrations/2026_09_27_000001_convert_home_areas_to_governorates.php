<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قسم «أفضل المناطق» في الصفحة الرئيسية صار «المحافظات» (مفتاحه areas كما هو):
 * كل بند منطقة يتحوّل لمحافظتها بلا تكرار — ويبقى لأول بند من كل محافظة صورته.
 * العنوان يتغيّر دائماً، والوصف يُستبدل فقط إن كان ما زال الافتراضي القديم أو فارغاً.
 * ويُنشأ قسم «العقارات المميزة» بترويسته الافتراضية إن لم يكن موجوداً.
 */
return new class extends Migration
{
    private const OLD_DESCRIPTION = [
        'ar' => 'استعرض العقارات حسب أكثر المناطق طلبًا في الكويت.',
        'en' => 'Browse properties in the most in-demand areas of Kuwait.',
    ];

    private const GOVERNORATES = [
        'ar' => ['title' => 'المحافظات', 'description' => 'استعرض العقارات المتاحة في كل محافظة من محافظات الكويت.'],
        'en' => ['title' => 'Governorates', 'description' => "Browse available properties in each of Kuwait's governorates."],
    ];

    private const FEATURED = [
        'ar' => ['title' => 'العقارات المميزة', 'description' => 'مجموعة مختارة من أفضل العقارات المتاحة لدينا حالياً.'],
        'en' => ['title' => 'Featured Properties', 'description' => 'A hand-picked selection of our best available properties.'],
    ];

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'home')->first();

        if (! $page) {
            return;
        }

        $pageId = array_change_key_case((array) $page)['id'];

        $this->convertAreas($pageId);
        $this->addFeaturedSection($pageId);
    }

    public function down(): void
    {
        // تحويل باتجاه واحد: المحافظة لا تدل على منطقة بعينها
    }

    private function convertAreas(int|string $pageId): void
    {
        $row = DB::table('page_sections')->where('page_id', $pageId)->where('key', 'areas')->first();

        if (! $row) {
            return;
        }

        $row = array_change_key_case((array) $row);
        $content = json_decode((string) $row['content'], true) ?: [];
        $cityOf = DB::table('areas')->pluck('city_id', 'id')->all();

        // البنود مشتركة بين اللغتين (نفس القائمة في ar و en)
        $items = [];
        foreach ($content['ar']['items'] ?? $content['en']['items'] ?? [] as $item) {
            $cityId = (string) ($item['city_id'] ?? $cityOf[(int) ($item['area_id'] ?? 0)] ?? '');

            if ($cityId === '' || in_array($cityId, array_column($items, 'city_id'), true)) {
                continue;
            }

            $items[] = ['city_id' => $cityId, 'collection' => $item['collection'] ?? null];
        }

        foreach (['ar', 'en'] as $loc) {
            $section = $content[$loc] ?? [];
            $section['items'] = $items;
            $section['title'] = self::GOVERNORATES[$loc]['title'];

            $description = trim((string) ($section['description'] ?? ''));
            if ($description === '' || $description === self::OLD_DESCRIPTION[$loc]) {
                $section['description'] = self::GOVERNORATES[$loc]['description'];
            }

            $content[$loc] = $section;
        }

        DB::table('page_sections')->where('id', $row['id'])->update([
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    private function addFeaturedSection(int|string $pageId): void
    {
        $exists = DB::table('page_sections')->where('page_id', $pageId)->where('key', 'featured_properties')->exists();

        if ($exists) {
            return;
        }

        // is_visible يأخذ الافتراضي من الجدول (ظاهر)
        DB::table('page_sections')->insert([
            'page_id' => $pageId,
            'key' => 'featured_properties',
            'sort_order' => 7,
            'content' => json_encode(self::FEATURED, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
