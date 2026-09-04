<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * مدن (محافظات) الكويت ومناطقها من database/data/kuwait_governorates_areas.json.
 *
 * آمن للتشغيل أكثر من مرة وعلى قاعدة بها بيانات: يطابق المناطق الموجودة بالاسم العربي
 * ويربطها بمدينتها، ويضيف الناقص فقط، ثم يستكمل city_id في احتياجات العملاء.
 */
class KuwaitAreasSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/kuwait_governorates_areas.json');
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $cities = City::all();
        $areas = Area::all();
        $cityOrder = (int) City::max('sort_order');
        $areaOrder = (int) Area::max('sort_order');

        foreach ($data['governorates'] as $governorate) {
            $city = $cities->first(fn (City $c) => $this->same($c->getTranslation('name', 'ar', false), $governorate['name_ar']));

            if (! $city) {
                $city = City::create([
                    'name' => ['ar' => $governorate['name_ar'], 'en' => $governorate['name_en']],
                    'sort_order' => ++$cityOrder,
                ]);
                $cities->push($city);
            } elseif (! $city->getTranslation('name', 'en', false)) {
                $city->setTranslation('name', 'en', $governorate['name_en'])->save();
            }

            foreach ($governorate['areas'] as $row) {
                $area = $areas->first(fn (Area $a) => $this->same($a->getTranslation('name', 'ar', false), $row['name_ar']));

                if (! $area) {
                    $areas->push(Area::create([
                        'name' => ['ar' => $row['name_ar'], 'en' => $row['name_en']],
                        'city_id' => $city->id,
                        'sort_order' => ++$areaOrder,
                    ]));

                    continue;
                }

                $dirty = false;

                if (! $area->city_id) {
                    $area->city_id = $city->id;
                    $dirty = true;
                }

                if (! $area->getTranslation('name', 'en', false)) {
                    $area->setTranslation('name', 'en', $row['name_en']);
                    $dirty = true;
                }

                if ($dirty) {
                    $area->save();
                }
            }
        }

        $this->backfillNeedCities();

        $this->command?->info('المدن: '.City::count().' · المناطق: '.Area::count());
    }

    /** احتياجات العملاء المنقولة من الأعمدة القديمة لها منطقة بلا مدينة — تُستكمل من المنطقة */
    private function backfillNeedCities(): void
    {
        $areaIds = DB::table('client_property_needs')
            ->whereNull('city_id')
            ->whereNotNull('area_id')
            ->distinct()
            ->pluck('area_id');

        if ($areaIds->isEmpty()) {
            return;
        }

        $cityByArea = Area::whereIn('id', $areaIds)->whereNotNull('city_id')->pluck('city_id', 'id');

        foreach ($cityByArea as $areaId => $cityId) {
            DB::table('client_property_needs')
                ->where('area_id', $areaId)
                ->whereNull('city_id')
                ->update(['city_id' => $cityId]);
        }
    }

    private function same(?string $a, ?string $b): bool
    {
        return $this->normalize((string) $a) === $this->normalize((string) $b);
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $value);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
