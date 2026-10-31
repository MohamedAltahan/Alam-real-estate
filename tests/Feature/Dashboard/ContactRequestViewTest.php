<?php

namespace Tests\Feature\Dashboard;

use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** زر العين في بطاقة الطلب: نافذة عرض الطلب كاملاً، وفتحها يجعله مقروءاً لهذا المستخدم */
class ContactRequestViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'contact_requests.view', 'guard_name' => 'web']));
    }

    public function test_index_renders_the_view_button_and_dialog(): void
    {
        ContactRequest::create(['name' => 'RedSpider', 'message' => 'Full message body']);

        $this->actingAs($this->user)->get(route('dashboard.requests.index'))
            ->assertOk()
            ->assertSee('title="عرض الطلب"', false)
            ->assertSee('تفاصيل الطلب');
    }

    public function test_opening_a_request_marks_it_read_for_that_user_only(): void
    {
        $request = ContactRequest::create(['name' => 'RedSpider']);
        $other = User::factory()->create();

        $this->actingAs($this->user)->put(route('dashboard.requests.read', $request))->assertNoContent();
        $this->actingAs($this->user)->put(route('dashboard.requests.read', $request))->assertNoContent();

        $this->assertTrue($request->isReadBy($this->user));
        $this->assertFalse($request->isReadBy($other));
        $this->assertSame(1, $request->reads()->count());
    }

    public function test_marking_read_requires_the_view_permission(): void
    {
        $request = ContactRequest::create(['name' => 'RedSpider']);

        $this->actingAs(User::factory()->create())->put(route('dashboard.requests.read', $request))->assertForbidden();
    }
}
