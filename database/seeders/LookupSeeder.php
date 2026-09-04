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
        // KuwaitAreasSeeder يضيف القائمة الكاملة ويربطها بالمدن — هنا بذرة أولية فقط لو الجدول فارغ
        if (Area::count() === 0) {
            foreach ($areas as $i => $a) {
                Area::create(['name' => $a, 'sort_order' => $i]);
            }
        }

        // تصنيف العقار: سكني وتجاري فقط (بمفتاح ثابت)
        foreach ([
            ['key' => 'residential', 'ar' => 'سكني', 'en' => 'Residential'],
            ['key' => 'commercial', 'ar' => 'تجاري', 'en' => 'Commercial'],
        ] as $i => $c) {
            PropertyCategory::updateOrCreate(['key' => $c['key']], [
                'name' => ['ar' => $c['ar'], 'en' => $c['en']], 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        // نوع الوحدة — بتصنيفه (سكني/تجاري)
        if (UnitType::count() === 0) {
            foreach ([
                ['ar' => 'شقة', 'en' => 'Apartment', 'category' => 'residential'],
                ['ar' => 'فيلا', 'en' => 'Villa', 'category' => 'residential'],
                ['ar' => 'منزل', 'en' => 'House', 'category' => 'residential'],
                ['ar' => 'دور', 'en' => 'Floor', 'category' => 'residential'],
                ['ar' => 'أرض', 'en' => 'Land', 'category' => 'residential'],
                ['ar' => 'مكتب', 'en' => 'Office', 'category' => 'commercial'],
                ['ar' => 'محل', 'en' => 'Shop', 'category' => 'commercial'],
            ] as $i => $u) {
                UnitType::create(['name' => ['ar' => $u['ar'], 'en' => $u['en']], 'category' => $u['category'], 'sort_order' => $i]);
            }
        }

        // حالة العقار (بمفتاح ولون للبادج) — أربع حالات فقط
        foreach ([
            ['key' => 'pending', 'color' => '#6B7280', 'ar' => 'قيد الإنتظار', 'en' => 'Pending'],
            ['key' => 'review', 'color' => '#B5842A', 'ar' => 'قيد التدقيق', 'en' => 'Under Review'],
            ['key' => 'available', 'color' => '#2E7D5B', 'ar' => 'متاح', 'en' => 'Available'],
            ['key' => 'sold', 'color' => '#C0392B', 'ar' => 'مباع', 'en' => 'Sold'],
        ] as $i => $s) {
            PropertyStatus::updateOrCreate(['key' => $s['key']], [
                'name' => ['ar' => $s['ar'], 'en' => $s['en']],
                'color' => $s['color'], 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        // مراحل العميل (Pipeline) — is_final للتقارير
        foreach ([
            ['key' => 'new', 'color' => '#3B5BA5', 'final' => false, 'ar' => 'طلب جديد', 'en' => 'New Request'],
            ['key' => 'potential', 'color' => '#7481E0', 'final' => false, 'ar' => 'عميل محتمل', 'en' => 'Potential Client'],
            ['key' => 'viewing', 'color' => '#B5842A', 'final' => false, 'ar' => 'معاينة العقار', 'en' => 'Property Viewing'],
            ['key' => 'closed_won', 'color' => '#2E7D5B', 'final' => true, 'ar' => 'ربح', 'en' => 'Won'],
            ['key' => 'closed_lost', 'color' => '#C0392B', 'final' => true, 'ar' => 'خسارة', 'en' => 'Lost'],
        ] as $i => $s) {
            ClientStage::updateOrCreate(
                ['key' => $s['key']],
                [
                    'name' => ['ar' => $s['ar'], 'en' => $s['en']],
                    'color' => $s['color'], 'is_final' => $s['final'],
                    'is_active' => true, 'sort_order' => $i,
                ],
            );
        }

        // نوع العميل — «مستأجر» هو الافتراضي لكل العملاء الجدد
        foreach ([
            ['key' => 'buyer', 'ar' => 'مشترٍ', 'en' => 'Buyer'],
            ['key' => 'seller', 'ar' => 'بائع', 'en' => 'Seller'],
            ['key' => 'tenant', 'ar' => 'مستأجر', 'en' => 'Tenant'],
            ['key' => 'landlord', 'ar' => 'مؤجّر', 'en' => 'Landlord'],
        ] as $t) {
            ClientType::updateOrCreate(['key' => $t['key']], ['name' => ['ar' => $t['ar'], 'en' => $t['en']]]);
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
