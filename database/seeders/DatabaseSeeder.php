<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * بيانات البداية النظيفة: قوائم النظام، الصلاحيات، وحساب المدير فقط.
     * البيانات التجريبية لها Seeders مستقلة ولا تُشغّل تلقائيًا.
     */
    public function run(): void
    {
        $this->call([
            LookupSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@alam.com'],
            [
                'name' => 'محمد الإداري',
                'password' => 'password',
                'phone' => '+96599000021',
                'job_title' => 'مدير النظام',
                'status' => 'active',
                'is_agent' => false,
                'languages' => ['ar', 'en'],
            ],
        );

        $admin->assignRole('super-admin');
    }
}
