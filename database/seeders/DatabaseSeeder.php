<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\Page;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LookupSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // ===== مستخدمون =====
        $admin = User::create([
            'name' => 'محمد الإداري',
            'email' => 'admin@alam.com',
            'password' => 'password',
            'phone' => '+96599000021',
            'civil_id' => '290010112233',
            'job_title' => 'مدير النظام',
            'status' => 'active',
            'is_agent' => false,
            'languages' => ['ar', 'en'],
        ]);
        $admin->assignRole('super-admin');

        $agent = User::create([
            'name' => 'أحمد العبدالله',
            'email' => 'ahmad@alam.com',
            'password' => 'password',
            'phone' => '+96599112233',
            'job_title' => 'مستشار عقاري أول',
            'status' => 'active',
            'is_agent' => true,
            'bio' => ['ar' => 'مستشار عقاري بخبرة واسعة في السوق الكويتي.', 'en' => 'Senior real-estate advisor with wide Kuwait-market experience.'],
            'languages' => ['ar', 'en'],
            'response_time' => 'خلال ساعة',
        ]);
        $agent->assignRole('sales-agent');

        // ===== بيانات تجريبية للتحقق من الطبقة كلها =====
        $owner = PropertyOwner::create([
            'name' => 'عبدالله الصباح',
            'phone' => '+96599887766',
            'email' => 'owner@example.com',
            'area_id' => Area::first()->id,
            'nationality' => 'كويتي',
            'registered_address' => 'مدينة الكويت - قطعة 3',
            'status' => 'active',
        ]);

        // فلترة PHP-side لتجنّب استعلامات JSON على Oracle
        $jabriya = Area::all()->first(fn ($a) => $a->getTranslation('name', 'en') === 'Jabriya') ?? Area::first();
        $villa = UnitType::all()->first(fn ($u) => $u->getTranslation('name', 'en') === 'Villa') ?? UnitType::first();

        $property = Property::create([
            'reference_code' => 'ALM-001',
            'title' => ['ar' => 'فيلا فاخرة مع مسبح خاص', 'en' => 'Luxury villa with private pool'],
            'short_description' => ['ar' => 'فيلا عائلية في منطقة راقية', 'en' => 'Family villa in a prime area'],
            'description' => ['ar' => 'فيلا فاخرة تقع في قلب الجابرية، تجربة سكنية استثنائية.', 'en' => 'A luxury villa in the heart of Jabriya, an exceptional living experience.'],
            'specifications' => ['ar' => '6 غرف نوم، 5 حمامات، مساحة 450 م².', 'en' => '6 bedrooms, 5 bathrooms, 450 sqm.'],
            'area_id' => $jabriya->id,
            'category_id' => PropertyCategory::first()->id,
            'unit_type_id' => $villa->id,
            'purpose' => 'rent',
            'price' => 1000,
            'price_period' => 'monthly',
            'status_id' => PropertyStatus::where('key', 'available')->value('id'),
            'owner_id' => $owner->id,
            'agent_id' => $agent->id,
            'bedrooms' => 6,
            'bathrooms' => 5,
            'area_size' => 450,
            'block' => '6', 'street' => '671', 'building' => '6',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_featured' => true,
        ]);

        // مرافق
        $property->amenities()->attach(Amenity::take(5)->pluck('id')->all());

        // صورة
        $property->images()->create(['path' => 'properties/demo-1.jpg', 'sort_order' => 0, 'is_cover' => true]);

        // تقييم للعقار + تقييم للوكيل (polymorphic)
        $property->reviews()->create([
            'reviewer_name' => 'فاطمة الحربي', 'rating' => 5,
            'comment' => 'عقار رائع وخدمة ممتازة.', 'created_by' => $admin->id,
        ]);
        $agent->reviews()->create([
            'reviewer_name' => 'خالد البدر', 'rating' => 5,
            'comment' => 'وكيل محترف وسريع الإنجاز.', 'created_by' => $admin->id,
        ]);

        // عميل + سجل تواصل + ربط بعقار
        $client = Client::create([
            'name' => 'محمد الصباح',
            'phone' => '+96599554433',
            'email' => 'client@example.com',
            'area_id' => Area::first()->id,
            'type_id' => ClientType::first()->id,
            'stage_id' => ClientStage::where('key', 'new')->value('id'),
            'agent_id' => $agent->id,
            'rating' => 80,
        ]);
        $client->interactions()->create([
            'user_id' => $agent->id,
            'type' => 'call',
            'notes' => 'مكالمة أولى — مهتم بفيلا للإيجار في الجابرية.',
            'stage_id' => ClientStage::where('key', 'contacted')->value('id'),
            'occurred_at' => now(),
        ]);
        $client->properties()->attach($property->id, ['relation' => 'interested']);

        // ===== صفحة رئيسية مع سكشن هيرو (محتوى مترجم مرن) =====
        $home = Page::create([
            'slug' => 'home',
            'name' => 'الرئيسية',
            'seo_title' => ['ar' => 'علم العقارية — عقارات الكويت', 'en' => 'Alam Real Estate — Kuwait Properties'],
            'seo_description' => ['ar' => 'اعثر على العقار المثالي لك في أفضل مناطق الكويت.', 'en' => 'Find your ideal property in the best areas of Kuwait.'],
        ]);
        $home->sections()->create([
            'key' => 'hero',
            'sort_order' => 0,
            'content' => [
                'ar' => [
                    'title' => 'اعثر على العقار المثالي لك',
                    'subtitle' => 'في أفضل مناطق الكويت',
                    'images' => ['hero/1.jpg', 'hero/2.jpg', 'hero/3.jpg'],
                    'rotate_seconds' => 5,
                ],
                'en' => [
                    'title' => 'Find your ideal property',
                    'subtitle' => 'In the best areas of Kuwait',
                    'images' => ['hero/1.jpg', 'hero/2.jpg', 'hero/3.jpg'],
                    'rotate_seconds' => 5,
                ],
            ],
        ]);
    }
}
