<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\ContactRequest;
use App\Models\MarketingSource;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\RequestType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية للوحة التحكم فقط: عملاء + طلبات تواصل موزّعة على آخر ٩ شهور،
 * وتعليم بعض العقارات كـ«مباع» حتى تظهر الإيرادات والصفقات المغلقة في الرسوم.
 *
 * php artisan db:seed --class=DemoDashboardSeeder
 */
class DemoDashboardSeeder extends Seeder
{
    private const FIRST = [
        'عبدالله', 'حصة', 'بندر', 'مريم', 'طارق', 'نوف', 'سعود', 'دلال', 'فيصل', 'شيخة',
        'ناصر', 'العنود', 'مشعل', 'أسماء', 'يوسف', 'لطيفة', 'خالد', 'منيرة', 'أحمد', 'جواهر',
    ];

    private const LAST = [
        'الرشيد', 'المزيدي', 'السبيعي', 'الشمري', 'الغانم', 'العنزي', 'المطيري', 'العجمي',
        'الخرافي', 'البدر', 'الصباح', 'الهاجري', 'الدوسري', 'الفهد',
    ];

    private const SUBJECTS = [
        'استفسار عن فيلا الزهراء',
        'طلب معاينة شقة السالمية',
        'عقارات تجارية بالشرق',
        'تقييم فيلا جدة',
        'شقق مفروشة للإيجار',
        'استفسار عن أسعار المنطقة العاشرة',
        'طلب عرض عقار للبيع',
        'متابعة عرض سابق',
        'استفسار عن التمويل العقاري',
        'حجز موعد معاينة',
    ];

    public function run(): void
    {
        $now = CarbonImmutable::now();

        $areas = Area::pluck('id')->all();
        $types = ClientType::pluck('id')->all();
        $stages = ClientStage::pluck('id')->all();
        $wonId = ClientStage::where('key', 'closed_won')->value('id');
        $sources = MarketingSource::pluck('id')->all();
        $agents = User::where('is_agent', true)->pluck('id')->all();
        $requestTypes = RequestType::pluck('id')->all();

        // ===== عملاء موزّعين على آخر ٨ شهور =====
        $this->command?->info('إنشاء العملاء...');

        for ($i = 0; $i < 45; $i++) {
            $at = $now->subMonths(random_int(0, 7))->subDays(random_int(0, 27))->subHours(random_int(0, 23));

            $client = new Client([
                'name' => $this->name(),
                'phone' => '9'.random_int(1000000, 9999999),
                'email' => 'client'.$i.'@example.com',
                'area_id' => $areas ? $areas[array_rand($areas)] : null,
                'type_id' => $types ? $types[array_rand($types)] : null,
                'stage_id' => $stages ? $stages[array_rand($stages)] : null,
                'agent_id' => $agents ? $agents[array_rand($agents)] : null,
                'source_id' => $sources ? $sources[array_rand($sources)] : null,
                'rating' => random_int(3, 5),
            ]);
            $client->created_at = $at;
            $client->updated_at = $at;
            $client->save();
        }

        // ===== طلبات تواصل موزّعة على آخر ٩ شهور =====
        $this->command?->info('إنشاء طلبات التواصل...');

        $propertyIds = Property::pluck('id')->all();

        for ($i = 0; $i < 60; $i++) {
            $at = $now->subMonths(random_int(0, 8))->subDays(random_int(0, 27))->subHours(random_int(0, 23));
            $contacted = random_int(1, 100) <= 55;

            $request = new ContactRequest([
                'name' => $this->name(),
                'phone' => '9'.random_int(1000000, 9999999),
                'email' => 'lead'.$i.'@example.com',
                'request_type_id' => $requestTypes ? $requestTypes[array_rand($requestTypes)] : null,
                'subject' => self::SUBJECTS[array_rand(self::SUBJECTS)],
                'message' => 'أرجو التواصل معي لتحديد موعد مناسب للمعاينة، وشكراً.',
                'property_id' => $propertyIds && random_int(0, 1) ? $propertyIds[array_rand($propertyIds)] : null,
            ]);
            $request->status = $contacted ? 'contacted' : 'pending';
            $request->is_read = true;
            $request->created_at = $at;
            $request->updated_at = $at;
            $request->save();
        }

        // أحدث ٣ طلبات فقط تبقى "جديد/غير مقروء" — عدّاد الإشعارات
        ContactRequest::query()->update(['is_read' => true]);

        ContactRequest::latest()->take(3)->get()->each(function (ContactRequest $r) {
            $r->status = 'pending';
            $r->is_read = false;
            $r->saveQuietly();
        });

        // ===== توزيع تواريخ إضافة العقارات + تعليم بعضها كمباع =====
        $this->command?->info('توزيع تواريخ العقارات...');

        $soldId = PropertyStatus::where('key', 'sold')->value('id');
        $properties = Property::orderBy('id')->get();

        foreach ($properties->values() as $index => $property) {
            $created = $now->subMonths(random_int(0, 7))->subDays(random_int(0, 27));
            $property->created_at = $created;

            // ٤ عقارات تُعلَّم كمباعة في شهور مختلفة (مصدر الإيرادات والصفقات المغلقة)
            if ($soldId && $index % 3 === 1) {
                $property->status_id = $soldId;
                $property->updated_at = $now->subMonths($index % 8)->subDays(random_int(0, 20));
            } else {
                $property->updated_at = $created;
            }

            $property->saveQuietly();
        }

        // ===== ملّاك عقارات + توزيع العقارات عليهم =====
        $this->command?->info('إنشاء ملّاك العقارات...');

        $ownerNames = ['محمد الصباح', 'ريم العجمي', 'خالد البدر', 'فاطمة الرشيد', 'نواف الهاجري', 'منى القحطاني'];

        foreach ($ownerNames as $index => $ownerName) {
            $at = $now->subMonths(random_int(2, 20))->subDays(random_int(0, 27));

            $owner = PropertyOwner::firstOrNew(['name' => $ownerName]);
            $owner->fill([
                'phone' => '9'.random_int(1000000, 9999999),
                'email' => 'owner'.($index + 1).'@example.com',
                'area_id' => $areas ? $areas[array_rand($areas)] : null,
                'nationality' => 'كويتي',
                'status' => $index % 5 === 4 ? 'inactive' : 'active',
            ]);
            $owner->created_at = $at;
            $owner->updated_at = $at;
            $owner->save();
        }

        // توزيع العقارات على الملّاك حتى تختلف الأعداد والقيم الإجمالية
        $ownerIds = PropertyOwner::orderBy('id')->pluck('id')->all();

        if ($ownerIds) {
            foreach (Property::orderBy('id')->get() as $index => $property) {
                $property->owner_id = $ownerIds[$index % count($ownerIds)];
                $property->saveQuietly();
            }
        }

        // ===== بعض العملاء في مرحلة "صفقة ناجحة" =====
        if ($wonId) {
            Client::inRandomOrder()->take(12)->get()->each(function (Client $c) use ($wonId) {
                $c->stage_id = $wonId;
                $c->saveQuietly();
            });
        }

        $this->command?->info('تم ✔');
    }

    private function name(): string
    {
        return self::FIRST[array_rand(self::FIRST)].' '.self::LAST[array_rand(self::LAST)];
    }
}
