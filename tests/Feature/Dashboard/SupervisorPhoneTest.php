<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** هاتف المشرف: مفتاح دولة (الكويت افتراضياً) + رقم محلي، ويُحفظ كاملاً للموقع وواتساب */
class SupervisorPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_is_saved_with_country_code_and_split_back_for_editing(): void
    {
        $admin = User::factory()->create();
        foreach (['supervisors.view', 'supervisors.create', 'supervisors.edit'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        Role::create(['name' => 'sales-agent', 'guard_name' => 'web']);

        $base = ['name' => 'مندوب', 'email' => 'agent@alam.test', 'role' => 'sales-agent', 'status' => 'active'];

        // بدون اختيار مفتاح ⇒ الكويت
        $this->actingAs($admin)->post(route('dashboard.supervisors.store'), $base + [
            'password' => 'S3cretPass!', 'phone' => '69066675',
        ])->assertSessionHasNoErrors();
        $agent = User::where('email', 'agent@alam.test')->firstOrFail();
        $this->assertSame('+965 69066675', $agent->phone);

        // تغيير الدولة
        $this->actingAs($admin)->put(route('dashboard.supervisors.update', $agent), $base + [
            'phone_code' => '+20', 'phone' => '1001234567',
        ])->assertSessionHasNoErrors();
        $this->assertSame('+20 1001234567', $agent->fresh()->phone);

        // مفتاح غير موجود في القائمة مرفوض · الرقم الفارغ يُحفظ null
        $this->actingAs($admin)->put(route('dashboard.supervisors.update', $agent), $base + ['phone_code' => '+0000', 'phone' => '5'])
            ->assertSessionHasErrors('phone_code');
        $this->actingAs($admin)->put(route('dashboard.supervisors.update', $agent), $base + ['phone_code' => '+965', 'phone' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($agent->fresh()->phone);

        // شاشة المشرفين تفكّ الرقم المحفوظ إلى مفتاح + رقم محلي داخل بيانات التعديل
        $agent->update(['phone' => '+20 1001234567']);
        $this->actingAs($admin)->get(route('dashboard.supervisors.index'))
            ->assertOk()
            ->assertSee('"phone_code":"+20","phone":"1001234567"', false);
    }
}
