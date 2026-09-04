<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskFormRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use App\Support\ClientFormData;
use App\Support\PropertyLookup;
use App\Support\TaskAuditPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** لوحة المهام (Kanban): أعمدة بالسحب والإفلات، تفاصيل المهمة في نافذة، تعليقات ومرفقات وسجل */
class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    public function index(Request $request): View
    {
        $filters = $request->only(TaskService::FILTER_KEYS);
        $me = $request->user();

        return view('dashboard.tasks.index', [
            'columns' => $this->tasks->board($filters, $me),
            'filters' => $filters,
            'users' => $this->users(),
            'openTask' => (int) $request->query('task') ?: null,
            'reopen' => $this->reopen(),
            'canTouch' => fn (Task $task) => $this->tasks->canTouch($task, $me),
        ]);
    }

    public function store(TaskFormRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('tasks.create'), 403);

        $task = $this->tasks->create($request->taskData(), $request, $request->user());

        return redirect()->route('dashboard.tasks.index', ['task' => $task->id])
            ->with('success', 'تمت إضافة المهمة #'.$task->id.'.');
    }

    /** تفاصيل المهمة: جزء HTML يُحقن في النافذة (XHR) — وإلا نعيد التوجيه للوحة مع فتح المهمة */
    public function show(Request $request, Task $task): View|RedirectResponse
    {
        if (! $request->ajax()) {
            return redirect()->route('dashboard.tasks.index', ['task' => $task->id]);
        }

        $task->load(['assignee', 'creator', 'property', 'comments.user', 'media', 'auditLogs']);

        return view('dashboard.tasks._detail', [
            'task' => $task,
            'auditLogs' => TaskAuditPresenter::present($task->auditLogs),
            'canTouch' => $this->tasks->canTouch($task, $request->user()),
            'users' => $this->users(),
            'editPayload' => $this->editPayload($task),
        ]);
    }

    public function update(TaskFormRequest $request, Task $task): RedirectResponse
    {
        abort_unless($this->tasks->canTouch($task, $request->user()), 403);

        $this->tasks->update($task, $request->taskData(), $request, $request->user());

        return redirect()->route('dashboard.tasks.index', ['task' => $task->id])
            ->with('success', 'تم حفظ المهمة #'.$task->id.'.');
    }

    /** نقل بالسحب: الحالة الجديدة وترتيب بطاقات العمود الهدف */
    public function move(Request $request, Task $task): JsonResponse
    {
        abort_unless($this->tasks->canTouch($task, $request->user()), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'order' => ['nullable', 'array'],
            'order.*' => ['integer'],
        ]);

        $counts = $this->tasks->move($task, $data['status'], $data['order'] ?? [$task->id], $request->user());

        return response()->json(['ok' => true, 'status' => $task->status, 'counts' => $counts]);
    }

    /** إعادة الإسناد من نافذة التفاصيل بدون فتح فورم التعديل */
    public function assign(Request $request, Task $task): JsonResponse
    {
        abort_unless($this->tasks->canTouch($task, $request->user()), 403);

        $data = $request->validate(['assignee_id' => ['nullable', 'integer', 'exists:users,id']], [], ['assignee_id' => 'المسند إليه']);

        $task = $this->tasks->assign($task, isset($data['assignee_id']) ? (int) $data['assignee_id'] : null, $request->user());

        return response()->json(['ok' => true, 'assignee_id' => $task->assignee_id, 'assignee' => $task->assignee?->name]);
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']], [], ['body' => 'التعليق']);

        $this->tasks->comment($task, $data['body'], $request->user());

        return redirect()->route('dashboard.tasks.index', ['task' => $task->id])->with('success', 'تمت إضافة التعليق.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()->can('tasks.delete'), 403);

        $this->tasks->delete($task);

        return redirect()->route('dashboard.tasks.index')->with('success', 'تم حذف المهمة #'.$task->id.'.');
    }

    public function propertyLookup(Request $request): JsonResponse
    {
        return response()->json(PropertyLookup::search((string) $request->query('q', ''), false));
    }

    /** الموظفون القابلون للإسناد والفلترة */
    private function users()
    {
        return User::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /** بعد فشل التحقق: نعيد فتح الفورم بالقيم المُدخلة (والمرفقات المحفوظة في وضع التعديل) */
    private function reopen(): ?array
    {
        if (! session('errors')?->any() || old('title') === null) {
            return null;
        }

        $task = old('_method') === 'PUT' && old('task_id') ? Task::find((int) old('task_id')) : null;

        return [
            'mode' => $task ? 'edit' : 'add',
            'form' => [
                'id' => $task?->id,
                'action' => $task ? route('dashboard.tasks.update', $task) : route('dashboard.tasks.store'),
                'title' => (string) old('title', ''),
                'description' => (string) old('description', ''),
                'status' => (string) old('status', $task?->status ?? 'new'),
                'priority' => (string) old('priority', 'medium'),
                'due_date' => (string) old('due_date', ''),
                'assignee_id' => (string) old('assignee_id', ''),
                'property_id' => (string) old('property_id', ''),
                'property_label' => $task?->property_id == old('property_id') && $task?->property
                    ? ClientFormData::propertyLabel($task->property)
                    : (old('property_id') ? '#'.old('property_id') : ''),
                'files' => $task?->filePayload() ?? [],
            ],
        ];
    }

    /** بيانات فورم التعديل (Alpine) */
    private function editPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'action' => route('dashboard.tasks.update', $task),
            'title' => $task->title,
            'description' => (string) $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('Y-m-d') ?? '',
            'assignee_id' => (string) ($task->assignee_id ?? ''),
            'property_id' => (string) ($task->property_id ?? ''),
            'property_label' => $task->property ? ClientFormData::propertyLabel($task->property) : '',
            'files' => $task->filePayload(),
        ];
    }
}
