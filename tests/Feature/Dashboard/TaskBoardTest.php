<?php

namespace Tests\Feature\Dashboard;

use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_requires_the_tasks_permission_and_hides_create_for_viewers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.tasks.index'))
            ->assertForbidden();

        $viewer = $this->userWith(['tasks.view']);

        $this->actingAs($viewer)
            ->get(route('dashboard.tasks.index'))
            ->assertOk()
            ->assertSee('لوحة المهام')
            ->assertSee('تم الاستلام')
            ->assertDontSee('@click="startAdd()"', false);

        $this->actingAs($viewer)
            ->post(route('dashboard.tasks.store'), ['title' => 'x', 'priority' => 'low'])
            ->assertForbidden();
    }

    public function test_creating_a_task_stores_attachments_logs_and_notifies_the_assignee(): void
    {
        Storage::fake('public');
        $creator = $this->userWith(['tasks.view', 'tasks.create', 'tasks.edit']);
        $assignee = User::factory()->create();
        $property = Property::create(['reference_code' => '12', 'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat']]);

        $this->actingAs($creator)->post(route('dashboard.tasks.store'), [
            'title' => 'تصوير الشقة',
            'description' => 'تصوير كامل قبل النشر',
            'priority' => 'high',
            'due_date' => now()->addDays(2)->toDateString(),
            'assignee_id' => $assignee->id,
            'property_id' => $property->id,
            'files' => [UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('new', $task->status);
        $this->assertSame('high', $task->priority);
        $this->assertSame($creator->id, $task->created_by);
        $this->assertSame($assignee->id, $task->assignee_id);
        $this->assertSame($property->id, $task->property_id);
        $this->assertCount(1, $task->getMedia(Task::ATTACHMENTS));

        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'created', 'user_id' => $creator->id]);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'attachment_added']);

        $notification = $assignee->notifications()->firstOrFail();
        $this->assertSame('task', $notification->data['kind']);
        $this->assertSame('assigned', $notification->data['event']);
        $this->assertStringContainsString('#'.$task->id, $notification->data['title']);
        $this->assertSame(0, $creator->notifications()->count());

        // الرقم والعنوان يظهران على اللوحة، والتفاصيل تُجلب كجزء HTML
        $this->actingAs($creator)->get(route('dashboard.tasks.index'))
            ->assertOk()->assertSee('تصوير الشقة')->assertSee('#'.$task->id);

        $this->actingAs($creator)->get(route('dashboard.tasks.show', $task), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('brief.pdf')->assertSee('إنشاء المهمة')->assertSee('12 — شقة السالمية');

        $this->actingAs($creator)->get(route('dashboard.tasks.show', $task))
            ->assertRedirect(route('dashboard.tasks.index', ['task' => $task->id]));
    }

    public function test_reassigning_logs_and_notifies_only_the_new_assignee(): void
    {
        $editor = $this->userWith(['tasks.view', 'tasks.edit']);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $task = Task::create(['title' => 'مهمة', 'assignee_id' => $first->id, 'created_by' => $editor->id]);

        $this->actingAs($editor)->put(route('dashboard.tasks.update', $task), [
            'title' => 'مهمة معدّلة',
            'priority' => 'urgent',
            'assignee_id' => $second->id,
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame($second->id, $task->assignee_id);
        $this->assertSame('urgent', $task->priority);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'assigned']);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'updated']);

        $this->assertSame(1, $second->notifications()->count());
        $this->assertSame(0, $first->notifications()->count());
        $this->assertSame(0, $editor->notifications()->count());
    }

    public function test_moving_reorders_the_column_sets_completed_at_and_notifies_the_creator(): void
    {
        $creator = User::factory()->create();
        $assignee = User::factory()->create(); // بلا صلاحية تعديل — يحرّك مهمته لأنها مسندة له
        $assignee->givePermissionTo(Permission::firstOrCreate(['name' => 'tasks.view', 'guard_name' => 'web']));

        $a = Task::create(['title' => 'أ', 'status' => 'in_progress', 'position' => 0, 'created_by' => $creator->id]);
        $b = Task::create(['title' => 'ب', 'status' => 'in_progress', 'position' => 1, 'created_by' => $creator->id]);
        $mine = Task::create(['title' => 'ج', 'status' => 'new', 'position' => 0, 'assignee_id' => $assignee->id, 'created_by' => $creator->id]);

        $this->actingAs($assignee)->patchJson(route('dashboard.tasks.move', $mine), [
            'status' => 'done',
            'order' => [$mine->id],
        ])->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('counts.done', 1);

        $mine->refresh();
        $this->assertSame('done', $mine->status);
        $this->assertNotNull($mine->completed_at);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $mine->id, 'action' => 'moved', 'user_id' => $assignee->id]);
        $this->assertSame(1, $creator->notifications()->count());
        $this->assertSame('moved', $creator->notifications()->first()->data['event']);
        $this->assertSame(0, $assignee->notifications()->count());

        // ترتيب داخل عمود: ب قبل أ — ومعرّف من عمود آخر يُتجاهل
        $editor = $this->userWith(['tasks.view', 'tasks.edit']);
        $this->actingAs($editor)->patchJson(route('dashboard.tasks.move', $b), [
            'status' => 'in_progress',
            'order' => [$b->id, $mine->id, $a->id],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame('done', $mine->fresh()->status);

        // غريب عن المهمة بلا صلاحية تعديل: ممنوع
        $stranger = $this->userWith(['tasks.view']);
        $this->actingAs($stranger)->patchJson(route('dashboard.tasks.move', $a), ['status' => 'done'])->assertForbidden();

        // الرجوع من «تمت» يمسح تاريخ الإنجاز
        $this->actingAs($editor)->patchJson(route('dashboard.tasks.move', $mine), ['status' => 'received'])->assertOk();
        $this->assertNull($mine->fresh()->completed_at);
    }

    public function test_assignee_can_be_changed_from_the_detail_modal_after_saving(): void
    {
        $editor = $this->userWith(['tasks.view', 'tasks.edit']);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $task = Task::create(['title' => 'مهمة', 'assignee_id' => $first->id, 'created_by' => $editor->id]);

        // نافذة التفاصيل تعرض قائمة الإسناد لمن يملك التعديل
        $this->actingAs($editor)->get(route('dashboard.tasks.show', $task), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('quickAssign('.$task->id, false);

        $this->actingAs($editor)->patchJson(route('dashboard.tasks.assign', $task), ['assignee_id' => $second->id])
            ->assertOk()->assertJsonPath('assignee_id', $second->id);

        $this->assertSame($second->id, $task->fresh()->assignee_id);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'assigned', 'user_id' => $editor->id]);
        $this->assertSame(1, $second->notifications()->count());
        $this->assertSame(0, $first->notifications()->count());

        // نفس الشخص مرة أخرى: لا سجل ولا إشعار جديد
        $this->actingAs($editor)->patchJson(route('dashboard.tasks.assign', $task), ['assignee_id' => $second->id])->assertOk();
        $this->assertSame(1, $second->notifications()->count());

        // رفع الإسناد
        $this->actingAs($editor)->patchJson(route('dashboard.tasks.assign', $task), ['assignee_id' => null])->assertOk();
        $this->assertNull($task->fresh()->assignee_id);

        // بلا صلاحية تعديل وليس المسند إليه: ممنوع
        $this->actingAs($this->userWith(['tasks.view']))
            ->patchJson(route('dashboard.tasks.assign', $task), ['assignee_id' => $first->id])->assertForbidden();
    }

    public function test_filters_narrow_the_board(): void
    {
        $me = $this->userWith(['tasks.view']);
        $other = User::factory()->create();

        Task::create(['title' => 'مهمتي المتأخرة', 'assignee_id' => $me->id, 'priority' => 'urgent', 'due_date' => now()->subDay()]);
        Task::create(['title' => 'مهمة زميلي', 'assignee_id' => $other->id, 'priority' => 'low', 'due_date' => now()->addDays(3)]);
        Task::create(['title' => 'قديمة مكتملة', 'status' => 'done', 'completed_at' => now()->subDays(40)]);

        $this->actingAs($me)->get(route('dashboard.tasks.index', ['mine' => 1]))
            ->assertOk()->assertSee('مهمتي المتأخرة')->assertDontSee('مهمة زميلي');

        $this->actingAs($me)->get(route('dashboard.tasks.index', ['priority' => 'low']))
            ->assertOk()->assertSee('مهمة زميلي')->assertDontSee('مهمتي المتأخرة');

        $this->actingAs($me)->get(route('dashboard.tasks.index', ['due' => 'overdue']))
            ->assertOk()->assertSee('مهمتي المتأخرة')->assertDontSee('مهمة زميلي');

        $this->actingAs($me)->get(route('dashboard.tasks.index', ['search' => '#2']))
            ->assertOk()->assertSee('مهمة زميلي')->assertDontSee('مهمتي المتأخرة');

        // المكتملة القديمة مخفية إلا مع «عرض كل المكتملة»
        $this->actingAs($me)->get(route('dashboard.tasks.index'))->assertOk()->assertDontSee('قديمة مكتملة');
        $this->actingAs($me)->get(route('dashboard.tasks.index', ['all_done' => 1]))->assertOk()->assertSee('قديمة مكتملة');
    }

    public function test_comments_notify_the_other_side_and_delete_cascades(): void
    {
        $creator = $this->userWith(['tasks.view', 'tasks.create', 'tasks.delete']);
        $assignee = $this->userWith(['tasks.view']);
        $task = Task::create(['title' => 'مهمة', 'assignee_id' => $assignee->id, 'created_by' => $creator->id]);

        $this->actingAs($assignee)->post(route('dashboard.tasks.comments.store', $task), ['body' => 'تم البدء'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.tasks.index', ['task' => $task->id]));

        $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'user_id' => $assignee->id, 'body' => 'تم البدء']);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'comment_added']);
        $this->assertSame(1, $creator->notifications()->count());
        $this->assertSame('commented', $creator->notifications()->first()->data['event']);
        $this->assertSame(0, $assignee->notifications()->count());

        $this->actingAs($assignee)->delete(route('dashboard.tasks.destroy', $task))->assertForbidden();

        $this->actingAs($creator)->delete(route('dashboard.tasks.destroy', $task))->assertRedirect(route('dashboard.tasks.index'));
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_comments', ['task_id' => $task->id]);
        $this->assertDatabaseMissing('task_audit_logs', ['task_id' => $task->id]);
    }

    public function test_task_notification_opens_the_board_on_that_task(): void
    {
        $creator = $this->userWith(['tasks.view', 'tasks.create']);
        $assignee = $this->userWith(['tasks.view', 'notifications.view']);

        $this->actingAs($creator)->post(route('dashboard.tasks.store'), [
            'title' => 'مهمة', 'priority' => 'medium', 'assignee_id' => $assignee->id,
        ])->assertSessionHasNoErrors();

        $task = Task::firstOrFail();
        $notification = $assignee->notifications()->firstOrFail();

        $this->actingAs($assignee)->get(route('dashboard.notifications.open', $notification->id))
            ->assertRedirect(route('dashboard.tasks.index', ['task' => $task->id]));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($assignee)->get(route('dashboard.notifications.poll'))
            ->assertOk()->assertJsonPath('items.0.kind', 'task');
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $user;
    }
}
