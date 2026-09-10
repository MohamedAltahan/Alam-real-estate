{{-- تفاصيل المهمة داخل نافذة اللوحة (جزء HTML يُجلب بـ XHR ويُحقن بـ x-html) --}}
@php
    use App\Models\Task;

    $done = $task->status === 'done';
    $overdue = $task->isOverdue();
    $me = auth()->user();
    $files = $task->filePayload();
@endphp

<div class="sticky top-0 z-10 bg-white flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-100">
    <div class="min-w-0">
        <p class="text-xs font-bold text-gray-400 tabular-nums"><bdi dir="ltr">#{{ $task->id }}</bdi></p>
        <h3 class="font-bold text-ink text-lg leading-snug {{ $done ? 'line-through text-gray-400' : '' }}" dir="auto">{{ $task->title }}</h3>
        <div class="flex flex-wrap items-center gap-2 mt-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-gray-700">
                <span class="w-2 h-2 rounded-full {{ Task::STATUS_TONES[$task->status] ?? 'bg-gray-400' }}"></span>{{ $task->statusLabel() }}
            </span>
            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $task->priorityTone() }}">{{ $task->priorityLabel() }}</span>
            @if ($overdue)<span class="rounded-full bg-danger/10 text-danger px-2.5 py-0.5 text-[11px] font-bold">متأخرة</span>@endif
        </div>
    </div>

    <div class="flex items-center gap-2 shrink-0">
        @if ($canTouch)
            <button type="button" @click="startEdit(@js($editPayload))" class="rounded-full bg-primary-50 text-primary-800 hover:bg-primary-100 font-semibold px-4 py-2 text-sm transition">تعديل</button>
        @endif
        @can('tasks.delete')
            <button type="button" @click="startDelete(@js(route('dashboard.tasks.destroy', $task)), '#{{ $task->id }}')" title="حذف"
                    class="grid place-items-center w-9 h-9 rounded-full text-danger hover:bg-danger/10 transition"><x-icon.trash size="17" /></button>
        @endcan
        <button type="button" @click="$dispatch('close-modal', 'task-view')" class="text-gray-400 hover:text-gray-700 ms-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
</div>

<div class="p-6 space-y-6">
    {{-- نقل سريع بين الحالات --}}
    @if ($canTouch)
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-gray-500">نقل إلى:</span>
            @foreach (Task::STATUSES as $key => $label)
                <button type="button" @click="quickMove({{ $task->id }}, '{{ $key }}')" @disabled($key === $task->status)
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold transition disabled:opacity-100 disabled:cursor-default
                               {{ $key === $task->status ? 'bg-primary-900 border-primary-900 text-white' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $key === $task->status ? 'bg-accent-500' : Task::STATUS_TONES[$key] }}"></span>{{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-4 text-sm">
        <div>
            <dt class="text-[11px] font-semibold text-gray-400 mb-1">المسند إليه</dt>
            @if ($canTouch)
                {{-- تغيير الإسناد مباشرة بعد الحفظ — يُحفظ فور الاختيار --}}
                <dd>
                    <select @change="quickAssign({{ $task->id }}, $event.target.value)" title="تغيير المسند إليه"
                            class="w-full max-w-[220px] appearance-none rounded-field bg-white border border-gray-200 ps-3 pe-8 h-9 text-sm font-semibold text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                        <option value="" @selected(! $task->assignee_id)>— غير مسندة —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}" @selected((int) $task->assignee_id === (int) $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </dd>
            @else
                <dd class="font-semibold text-ink">{{ $task->assignee?->name ?? '— غير مسندة —' }}</dd>
            @endif
        </div>
        <div>
            <dt class="text-[11px] font-semibold text-gray-400 mb-1">أنشأها</dt>
            <dd class="font-semibold text-ink">{{ $task->creator?->name ?? 'النظام' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] font-semibold text-gray-400 mb-1">تاريخ الاستحقاق</dt>
            <dd class="font-semibold {{ $overdue ? 'text-danger' : 'text-ink' }}"><bdi dir="ltr">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</bdi></dd>
        </div>
        <div>
            <dt class="text-[11px] font-semibold text-gray-400 mb-1">العقار المرتبط</dt>
            <dd class="font-semibold">
                @if ($task->property)
                    <a href="{{ route('dashboard.properties.show', $task->property) }}" class="text-primary-700 hover:underline" dir="auto">{{ \App\Support\ClientFormData::propertyLabel($task->property) }}</a>
                @else
                    <span class="text-gray-400">—</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-[11px] font-semibold text-gray-400 mb-1">تاريخ الإنشاء</dt>
            <dd class="font-semibold text-ink"><bdi dir="ltr">{{ $task->created_at?->format('Y-m-d H:i') }}</bdi></dd>
        </div>
        <div>
            <dt class="text-[11px] font-semibold text-gray-400 mb-1">تاريخ الإنجاز</dt>
            <dd class="font-semibold text-ink"><bdi dir="ltr">{{ $task->completed_at?->format('Y-m-d H:i') ?? '—' }}</bdi></dd>
        </div>
    </dl>

    @if ($task->description)
        <section>
            <h4 class="text-[11px] font-semibold text-gray-400 mb-1.5">الوصف</h4>
            <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed" dir="auto">{{ $task->description }}</p>
        </section>
    @endif

    {{-- المرفقات --}}
    <section>
        <h4 class="font-bold text-sm text-ink mb-2">المرفقات <span class="text-gray-400 font-normal">({{ count($files) }})</span></h4>
        @if ($files)
            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($files as $file)
                    @php $kind = match (true) { in_array($file['ext'], ['jpg', 'jpeg', 'png', 'webp']) => 'image', $file['ext'] === 'pdf' => 'pdf', in_array($file['ext'], ['doc', 'docx']) => 'word', in_array($file['ext'], ['xls', 'xlsx']) => 'excel', default => 'file' }; @endphp
                    <li>
                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-3 py-2 text-sm hover:border-primary-200 transition">
                            @if ($kind === 'image')
                                <img src="{{ $file['url'] }}" alt="" class="w-9 h-9 shrink-0 rounded-lg object-cover bg-gray-100">
                            @else
                                <span class="grid place-items-center w-9 h-9 shrink-0 rounded-lg text-[10px] font-bold uppercase {{ match ($kind) { 'pdf' => 'bg-danger/10 text-danger', 'word' => 'bg-primary-50 text-primary-700', 'excel' => 'bg-success-soft text-success', default => 'bg-gray-100 text-gray-500' } }}">{{ $file['ext'] ?: 'file' }}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink truncate" dir="auto">{{ $file['name'] }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $file['size'] > 1048576 ? number_format($file['size'] / 1048576, 1).' MB' : max(1, round($file['size'] / 1024)).' KB' }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400">لا توجد مرفقات.</p>
        @endif
    </section>

    {{-- التعليقات --}}
    <section>
        <h4 class="font-bold text-sm text-ink mb-3">التعليقات <span class="text-gray-400 font-normal">({{ $task->comments->count() }})</span></h4>

        <div class="space-y-3">
            @forelse ($task->comments as $comment)
                <article class="flex gap-3">
                    <span class="grid place-items-center w-8 h-8 shrink-0 rounded-full bg-primary-900 text-white text-xs font-bold">{{ mb_substr($comment->user?->name ?? 'ن', 0, 1) }}</span>
                    <div class="flex-1 min-w-0 rounded-2xl bg-gray-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <strong class="text-sm text-ink">{{ $comment->user?->name ?? 'النظام' }}</strong>
                            <span class="text-[11px] text-gray-400" dir="ltr">{{ $comment->created_at?->format('Y-m-d H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line mt-1" dir="auto">{{ $comment->body }}</p>
                    </div>
                </article>
            @empty
                <p class="text-sm text-gray-400">لا توجد تعليقات بعد.</p>
            @endforelse
        </div>

        <form action="{{ route('dashboard.tasks.comments.store', $task) }}" method="POST" class="mt-4 flex items-start gap-2">
            @csrf
            <textarea name="body" rows="2" required maxlength="3000" placeholder="اكتب تعليقاً..."
                      class="flex-1 rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></textarea>
            <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm transition shrink-0">إرسال</button>
        </form>
    </section>

    {{-- السجل --}}
    @include('dashboard.partials.audit', ['auditLogs' => $auditLogs])
</div>
