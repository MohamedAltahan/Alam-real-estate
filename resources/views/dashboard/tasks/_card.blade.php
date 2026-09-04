{{-- بطاقة مهمة على اللوحة — تُسحب بين الأعمدة، والنقر يفتح التفاصيل --}}
@php
    $done = $task->status === 'done';
    $overdue = $task->isOverdue();
    $draggable = $canTouch($task);
    $attachments = $task->media->count();
@endphp
<article data-task-card="{{ $task->id }}" data-task-status="{{ $task->status }}" @click="openTask({{ $task->id }})"
         class="rounded-2xl bg-white border shadow-sm p-3.5 transition hover:shadow-md hover:border-primary-200
                {{ $overdue ? 'border-danger/30' : 'border-gray-100' }}
                {{ $draggable ? 'cursor-grab active:cursor-grabbing' : 'no-drag cursor-pointer' }}
                {{ $done ? 'is-done' : '' }}">
    <div class="flex items-center justify-between gap-2 mb-1.5">
        <span class="text-[11px] font-bold text-gray-400 tabular-nums" dir="ltr">#{{ $task->id }}</span>
        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $task->priorityTone() }}">{{ $task->priorityLabel() }}</span>
    </div>

    <h4 class="text-sm font-bold leading-snug line-clamp-2 {{ $done ? 'text-gray-400 line-through' : 'text-ink' }}">{{ $task->title }}</h4>

    @if ($task->property)
        <a href="{{ route('dashboard.properties.show', $task->property) }}" @click.stop
           class="mt-1.5 inline-flex items-center gap-1 max-w-full text-[11px] text-primary-700 hover:underline">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="shrink-0"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg>
            <span class="truncate" dir="auto">{{ \App\Support\ClientFormData::propertyLabel($task->property) }}</span>
        </a>
    @endif

    <div class="flex items-center justify-between gap-2 mt-3">
        @if ($task->assignee)
            <span class="inline-flex items-center gap-1.5 min-w-0" title="المسند إليه: {{ $task->assignee->name }}">
                <span class="grid place-items-center w-6 h-6 shrink-0 rounded-full bg-primary-900 text-white text-[10px] font-bold">{{ mb_substr($task->assignee->name, 0, 1) }}</span>
                <span class="text-[11px] text-gray-600 truncate">{{ $task->assignee->name }}</span>
            </span>
        @else
            <span class="text-[11px] text-gray-400">غير مسندة</span>
        @endif

        <span class="flex items-center gap-2 shrink-0 text-[11px] text-gray-400">
            @if ($task->due_date)
                <span class="inline-flex items-center gap-0.5 {{ $overdue ? 'text-danger font-bold' : '' }}" title="تاريخ الاستحقاق">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <span dir="ltr">{{ $task->due_date->format('d/m') }}</span>
                </span>
            @endif
            @if ($task->comments_count)
                <span class="inline-flex items-center gap-0.5" title="التعليقات">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    {{ $task->comments_count }}
                </span>
            @endif
            @if ($attachments)
                <span class="inline-flex items-center gap-0.5" title="المرفقات">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.4 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    {{ $attachments }}
                </span>
            @endif
        </span>
    </div>
</article>
