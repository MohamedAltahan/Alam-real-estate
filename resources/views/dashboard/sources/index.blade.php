@extends('layouts.dashboard')

@section('title', 'مصادر التسويق')
@section('page-title', 'مصادر التسويق')

@section('content')
<div x-data="sourceCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">مصادر التسويق</h2>
            <p class="text-sm text-gray-500">{{ number_format($sources->total()) }} مصدر</p>
        </div>
        @can('marketing_sources.create')
            <button @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة مصدر
            </button>
        @endcan
    </div>

    <form method="GET" id="sources-filters" data-live-filters class="mb-4">
        <div class="relative max-w-md">
            <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث باسم المصدر..." autocomplete="off"
                   class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
    </form>

    <div data-results>
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3">المصدر</th>
                        <th class="text-start font-medium px-4 py-3">النوع</th>
                        <th class="text-start font-medium px-4 py-3">عدد العملاء</th>
                        <th class="text-start font-medium px-4 py-3">التكلفة</th>
                        <th class="text-start font-medium px-4 py-3">الحالة</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($sources as $s)
                        @php $editData = $s->only(['id', 'name', 'type_id', 'cost', 'status']); @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-semibold text-ink">{{ $s->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $s->type?->name ?: '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-block rounded-full bg-primary-50 text-primary-700 px-2.5 py-1 text-xs font-semibold tabular-nums">{{ $s->clients_count }}</span></td>
                            <td class="px-4 py-3 text-gray-600 tabular-nums">{{ number_format($s->cost, 3) }} د.ك</td>
                            <td class="px-4 py-3">
                                @if ($s->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>نشط</span>
                                @else
                                    <span class="inline-block rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-medium">غير نشط</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    @can('marketing_sources.edit')
                                        <button @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                                    @endcan
                                    @can('marketing_sources.delete')
                                        <button @click="startDelete('{{ route('dashboard.sources.destroy', $s) }}', @js($s->name))" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400">لا توجد مصادر.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $sources->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    <x-modal name="source-form">
        <form :action="action" method="POST">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل المصدر' : 'إضافة مصدر جديد'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'source-form')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم المصدر <span class="text-danger">*</span></label>
                    <input name="name" x-model="form.name" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">نوع المصدر</label>
                    <select name="type_id" x-model="form.type_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="">— اختر —</option>
                        @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">التكلفة (د.ك)</label>
                    <input name="cost" type="number" step="0.001" min="0" x-model="form.cost" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الحالة</label>
                    <select name="status" x-model="form.status" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'source-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ' : 'إضافة'"></button>
            </div>
        </form>
    </x-modal>

    <x-modal name="source-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف المصدر</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'source-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>

<script>
    function sourceCrud() {
        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: { name: '', type_id: '', cost: '', status: 'active' },
            startAdd() {
                this.mode = 'add';
                this.form = { name: '', type_id: '', cost: '', status: 'active' };
                this.action = '{{ route('dashboard.sources.store') }}';
                this.$dispatch('open-modal', 'source-form');
            },
            startEdit(s) {
                this.mode = 'edit';
                this.form = { name: s.name ?? '', type_id: s.type_id ?? '', cost: s.cost ?? '', status: s.status ?? 'active' };
                this.action = '{{ url('dashboard/sources') }}/' + s.id;
                this.$dispatch('open-modal', 'source-form');
            },
            startDelete(action, name) {
                this.delAction = action; this.delName = name;
                this.$dispatch('open-modal', 'source-delete');
            },
        };
    }
</script>
@endsection
