@extends('layouts.dashboard')

@section('title', 'المهام')
@section('page-title', 'المهام')

@php
    use App\Models\Task;
    $total = collect($columns)->sum(fn ($c) => $c->count());
    $mine = ! empty($filters['mine']);
@endphp

@section('content')
<div x-data="taskBoard({
        storeUrl: @js(route('dashboard.tasks.store')),
        showBase: @js(url('dashboard/tasks')),
        reopen: @js($reopen),
        openTask: @js($openTask),
     })">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div>
            <h2 class="text-xl font-bold text-ink">لوحة المهام</h2>
            <p class="text-sm text-gray-500"><span data-task-total>{{ number_format($total) }}</span> مهمة على اللوحة</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- نموذج الفلترة الموحّد: كل عناصره تنضمّ له بالخاصية form="tasks-filters" --}}
            <form method="GET" id="tasks-filters" data-live-filters></form>
            <input type="hidden" name="mine" form="tasks-filters" x-ref="mine" value="{{ $mine ? '1' : '' }}">

            <div class="relative w-[240px] max-w-[50vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالعنوان أو رقم المهمة..." autocomplete="off" form="tasks-filters"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </div>

            {{-- مهامي: يبدّل الحقل المخفي ويطلق input ليطبّق الفلتر الحيّ --}}
            <button type="button" x-data="{ on: {{ $mine ? 'true' : 'false' }} }"
                    @click="on = ! on; $refs.mine.value = on ? '1' : ''; $refs.mine.dispatchEvent(new Event('input', { bubbles: true }))"
                    :class="on ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-2 rounded-full border px-4 h-11 text-sm font-semibold transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                مهامي
            </button>

            @can('tasks.create')
                <button type="button" @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-5 h-11 text-sm transition shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    مهمة جديدة
                </button>
            @endcan
        </div>
    </div>

    {{-- ===== الفلاتر (تُطبَّق فور التغيير) ===== --}}
    <x-filter-bar id="tasks-filters" cols="xl:grid-cols-5" :hint="false">
        <x-filter-select label="المسند إليه" name="assignee_id" placeholder="كل الموظفين"
                         :options="$users->pluck('name', 'id')" :selected="$filters['assignee_id'] ?? null" />
        <x-filter-select label="أنشأها" name="created_by" placeholder="الكل"
                         :options="$users->pluck('name', 'id')" :selected="$filters['created_by'] ?? null" />
        <x-filter-select label="الأولوية" name="priority" placeholder="كل الأولويات"
                         :options="Task::PRIORITIES" :selected="$filters['priority'] ?? null" />
        <x-filter-select label="الاستحقاق" name="due" placeholder="الكل"
                         :options="['overdue' => 'متأخرة', 'today' => 'اليوم', 'week' => 'خلال أسبوع', 'none' => 'بدون موعد']"
                         :selected="$filters['due'] ?? null" />
        <x-filter-select label="المهام المكتملة" name="all_done" :placeholder="'آخر '.Task::DONE_VISIBLE_DAYS.' يوماً'"
                         :options="['1' => 'عرض كل المكتملة']" :selected="$filters['all_done'] ?? null" />
    </x-filter-bar>

    {{-- ===== اللوحة ===== --}}
    <div data-results>
        <div data-task-board data-move-base="{{ url('dashboard/tasks') }}"
             class="grid grid-flow-col auto-cols-[minmax(272px,1fr)] gap-4 overflow-x-auto pb-3">
            @foreach (Task::STATUSES as $status => $label)
                <section data-task-lane class="flex flex-col rounded-card bg-gray-100/70 border border-gray-100 min-h-[60vh]">
                    <header class="flex items-center gap-2 px-4 py-3">
                        <span class="w-2.5 h-2.5 rounded-full {{ Task::STATUS_TONES[$status] }}"></span>
                        <h3 class="font-bold text-sm text-ink">{{ $label }}</h3>
                        <span data-task-count class="ms-auto rounded-full bg-white border border-gray-200 text-gray-600 text-[11px] font-bold px-2 py-0.5 tabular-nums">{{ $columns[$status]->count() }}</span>
                    </header>

                    <div data-task-column data-status="{{ $status }}" class="flex-1 px-3 pb-3 space-y-3">
                        @foreach ($columns[$status] as $task)
                            @include('dashboard.tasks._card', ['task' => $task])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    {{-- ===== تفاصيل المهمة (تُجلب بـ XHR) ===== --}}
    <x-modal name="task-view" maxWidth="3xl">
        <div x-show="loading" class="p-10 flex items-center justify-center">
            <span class="w-7 h-7 rounded-full border-2 border-primary-200 border-t-primary-800 animate-spin"></span>
        </div>
        <div x-show="! loading" x-html="detail"></div>
    </x-modal>

    @include('dashboard.tasks._form-modal')

    @can('tasks.delete')
        <x-modal name="task-delete" maxWidth="md">
            <div class="p-6 text-center">
                <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
                <h3 class="font-bold text-ink mb-1">حذف المهمة</h3>
                <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف المهمة <span x-text="delRef" class="font-semibold text-ink" dir="ltr"></span>؟ سيُحذف معها التعليقات والمرفقات والسجل.</p>
                <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                    @csrf @method('DELETE')
                    <button type="button" @click="$dispatch('close-modal', 'task-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
                </form>
            </div>
        </x-modal>
    @endcan
</div>
@endsection
