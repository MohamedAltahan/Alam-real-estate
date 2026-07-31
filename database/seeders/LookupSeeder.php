<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Area;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\MarketingSourceType;
use App\Models\PropertyCategory;
use App\Models\PropertyStatus;
use App\Models\RequestType;
use App\Models\UnitType;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // المناطق (محافظات ومناطق الكويت)
        $areas = [
            ['ar' => 'مدينة الكويت', 'en' => 'Kuwait City'],
            ['ar' => 'حولي', 'en' => 'Hawally'],
            ['ar' => 'السالمية', 'en' => 'Salmiya'],
            ['ar' => 'الفروانية', 'en' => 'Farwaniya'],
            ['ar' => 'الأحمدي', 'en' => 'Ahmadi'],
            ['ar' => 'الجهراء', 'en' => 'Jahra'],
            ['ar' => 'مبارك الكبير', 'en' => 'Mubarak Al-Kabeer'],
            ['ar' => 'الجابرية', 'en' => 'Jabriya'],
            ['ar' => 'الفنطاس', 'en' => 'Fintas'],
            ['ar' => 'بيان', 'en' => 'Bayan'],
        ];
        foreach ($areas as $i => $a) {
            Area::create(['name' => $a, 'sort_order' => $i]);
        }

        // تصنيف العقار
        foreach ([
            ['ar' => 'سكني', 'en' => 'Residential'],
            ['ar' => 'تجاري', 'en' => 'Commercial'],
            ['ar' => 'مفروش', 'en' => 'Furnished'],
        ] as $i => $c) {
            PropertyCategory::create(['name' => $c, 'sort_order' => $i]);
        }

        // نوع الوحدة
        foreach ([
            ['ar' => 'شقة', 'en' => 'Apartment'],
            ['ar' => 'فيلا', 'en' => 'Villa'],
            ['ar' => 'منزل', 'en' => 'House'],
            ['ar' => 'دور', 'en' => 'Floor'],
            ['ar' => 'أرض', 'en' => 'Land'],
            ['ar' => 'مكتب', 'en' => 'Office'],
            ['ar' => 'محل', 'en' => 'Shop'],
        ] as $i => $u) {
            UnitType::create(['name' => $u, 'sort_order' => $i]);
        }

        // حالة العقار (بمفتاح ولون للبادج)
        foreach ([
            ['key' => 'available', 'color' => '#2E7D5B', 'ar' => 'متاح', 'en' => 'Available'],
            ['key' => 'reserved', 'color' => '#B5842A', 'ar' => 'محجوز', 'en' => 'Reserved'],
            ['key' => 'sold', 'color' => '#C0392B', 'ar' => 'مباع', 'en' => 'Sold'],
        ] as $i => $s) {
            PropertyStatus::create([
                'name' => ['ar' => $s['ar'], 'en' => $s['en']],
                'key' => $s['key'], 'color' => $s['color'], 'sort_order' => $i,
            ]);
        }

        // مراحل العميل (Pipeline) — is_final للتقارير
        foreach ([
            ['key' => 'new', 'color' => '#3B5BA5', 'final' => false, 'ar' => 'جديد', 'en' => 'New'],
            ['key' => 'contacted', 'color' => '#7481E0', 'final' => false, 'ar' => 'تم التواصل', 'en' => 'Contacted'],
            ['key' => 'interested', 'color' => '#B5842A', 'final' => false, 'ar' => 'مهتم', 'en' => 'Interested'],
            ['key' => 'negotiating', 'color' => '#E0B450', 'final' => false, 'ar' => 'تفاوض', 'en' => 'Negotiating'],
            ['key' => 'closed_won', 'color' => '#2E7D5B', 'final' => true, 'ar' => 'صفقة ناجحة', 'en' => 'Closed Won'],
            ['key' => 'closed_lost', 'color' => '#C0392B', 'final' => false, 'ar' => 'صفقة خاسرة', 'en' => 'Closed Lost'],
        ] as $i => $s) {
            ClientStage::create([
                'name' => ['ar' => $s['ar'], 'en' => $s['en']],
                'key' => $s['key'], 'color' => $s['color'],
                'is_final' => $s['final'], 'sort_order' => $i,
            ]);
        }

        // نوع العميل
        foreach ([
            ['ar' => 'مشترٍ', 'en' => 'Buyer'],
            ['ar' => 'بائع', 'en' => 'Seller'],
            ['ar' => 'مستأجر', 'en' => 'Tenant'],
            ['ar' => 'مؤجّر', 'en' => 'Landlord'],
        ] as $i => $t) {
            ClientType::create(['name' => $t]);
        }

        // المرافق والخدمات (اسم + أيقونة بنمط Phosphor)
        foreach ([
            ['icon' => 'WifiHigh', 'ar' => 'واي فاي مجاني', 'en' => 'Free WiFi'],
            ['icon' => 'Snowflake', 'ar' => 'تكييف', 'en' => 'Air Conditioning'],
            ['icon' => 'Car', 'ar' => 'مواقف سيارات', 'en' => 'Parking'],
            ['icon' => 'CookingPot', 'ar' => 'مطبخ مجهز', 'en' => 'Equipped Kitchen'],
            ['icon' => 'Television', 'ar' => 'تلفزيون', 'en' => 'Television'],
            ['icon' => 'Coffee', 'ar' => 'ماكينة قهوة', 'en' => 'Coffee Machine'],
            ['icon' => 'ShieldCheck', 'ar' => 'أمن وحراسة', 'en' => 'Security'],
            ['icon' => 'Barbell', 'ar' => 'صالة رياضية', 'en' => 'Gym'],
            ['icon' => 'SwimmingPool', 'ar' => 'مسبح', 'en' => 'Swimming Pool'],
            ['icon' => 'Elevator', 'ar' => 'مصعد', 'en' => 'Elevator'],
        ] as $i => $a) {
            Amenity::create([
                'name' => ['ar' => $a['ar'], 'en' => $a['en']],
                'icon' => $a['icon'], 'sort_order' => $i,
            ]);
        }

        // نوع الطلب (يفرّق طلبات الموقع)
        foreach ([
            ['key' => 'general', 'ar' => 'تواصل عام', 'en' => 'General Contact'],
            ['key' => 'property_inquiry', 'ar' => 'استفسار عن عقار', 'en' => 'Property Inquiry'],
            ['key' => 'list_property', 'ar' => 'طلب عرض عقار', 'en' => 'List a Property'],
        ] as $s) {
            RequestType::create(['name' => ['ar' => $s['ar'], 'en' => $s['en']], 'key' => $s['key']]);
        }

        // نوع مصدر التسويق
        foreach ([
            ['ar' => 'سوشيال ميديا', 'en' => 'Social Media'],
            ['ar' => 'إعلانات مدفوعة', 'en' => 'Paid Ads'],
            ['ar' => 'إحالة', 'en' => 'Referral'],
            ['ar' => 'زيارة مباشرة', 'en' => 'Direct'],
            ['ar' => 'محرك بحث', 'en' => 'Search Engine'],
        ] as $t) {
            MarketingSourceType::create(['name' => $t]);
        }
    }
}
