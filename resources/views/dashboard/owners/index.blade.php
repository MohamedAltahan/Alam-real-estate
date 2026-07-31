@extends('layouts.dashboard')

@section('title', 'ملّاك العقارات')
@section('page-title', 'ملّاك العقارات')

@section('content')
<div x-data="ownerCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">ملّاك العقارات</h2>
            <p class="text-sm text-gray-500">{{ number_format($owners->total()) }} مالك مسجّل</p>
        </div>
        @can('property_owners.create')
            <button @click="startAdd()" class="inline-flex items-center gap-2 rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة مالك
            </button>
        @endcan
    </div>

    <form method="GET" class="mb-4">
        <div class="relative max-w-md">
            <svg class="absolute inset-y-0 start-3 my-auto text-gray-400" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو الهاتف أو البريد..."
                   class="w-full rounded-field bg-white border border-gray-200 ps-10 pe-4 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
    </form>

    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3">المالك</th>
                        <th class="text-start font-medium px-4 py-3">الهاتف</th>
                        <th class="text-start font-medium px-4 py-3">المنطقة</th>
                        <th class="text-start font-medium px-4 py-3">الجنسية</th>
                        <th class="text-start font-medium px-4 py-3">العقارات</th>
                        <th class="text-start font-medium px-4 py-3">الحالة</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($owners as $o)
                        @php $editData = $o->only(['id', 'name', 'phone', 'email', 'area_id', 'nationality', 'registered_address', 'status']); @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center w-9 h-9 rounded-full bg-accent-100 text-accent-700 font-bold">{{ mb_substr($o->name, 0, 1) }}</span>
                                    <div class="min-w-0">
                                        <span class="block font-semibold text-ink truncate">{{ $o->name }}</span>
                                        <span class="block text-xs text-gray-400 truncate" dir="ltr">{{ $o->email ?: '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $o->phone }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $o->area?->name ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $o->nationality ?: '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-block rounded-full bg-primary-50 text-primary-700 px-2.5 py-1 text-xs font-semibold tabular-nums">{{ $o->properties_count }}</span></td>
                            <td class="px-4 py-3">
                                @if ($o->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>نشط</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-medium">غير نشط</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    @can('property_owners.edit')
                                        <button @click='startEdit(@json($editData))'
                                                class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                        </button>
                                    @endcan
                                    @can('property_owners.delete')
                                        <button @click="startDelete('{{ route('dashboard.owners.destroy', $o) }}', @js($o->name))"
                                                class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger hover:bg-danger/10" title="حذف">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400">لا يوجد ملّاك.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $owners->links() }}</div>

    {{-- مودال الإضافة/التعديل --}}
    <x-modal name="owner-form">
        <form :action="action" method="POST">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل المالك' : 'إضافة مالك جديد'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'owner-form')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم الكامل <span class="text-danger">*</span></label>
                    <input name="name" x-model="form.name" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">رقم الهاتف <span class="text-danger">*</span></label>
                    <input name="phone" x-model="form.phone" required dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label>
                    <input name="email" type="email" x-model="form.email" dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">المنطقة</label>
                    <select name="area_id" x-model="form.area_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="">— اختر —</option>
                        @foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الجنسية</label>
                    <input name="nationality" x-model="form.nationality" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">العنوان المسجّل</label>
                    <input name="registered_address" x-model="form.registered_address" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
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
                <button type="button" @click="$dispatch('close-modal', 'owner-form')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة المالك'"></button>
            </div>
        </form>
    </x-modal>

    {{-- مودال الحذف --}}
    <x-modal name="owner-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></span>
            <h3 class="font-bold text-ink mb-1">حذف المالك</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'owner-delete')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-field bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>

<script>
    function ownerCrud() {
        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: { name: '', phone: '', email: '', area_id: '', nationality: '', registered_address: '', status: 'active' },
            startAdd() {
                this.mode = 'add';
                this.form = { name: '', phone: '', email: '', area_id: '', nationality: '', registered_address: '', status: 'active' };
                this.action = '{{ route('dashboard.owners.store') }}';
                this.$dispatch('open-modal', 'owner-form');
            },
            startEdit(o) {
                this.mode = 'edit';
                this.form = { name: o.name ?? '', phone: o.phone ?? '', email: o.email ?? '', area_id: o.area_id ?? '', nationality: o.nationality ?? '', registered_address: o.registered_address ?? '', status: o.status ?? 'active' };
                this.action = '{{ url('dashboard/owners') }}/' + o.id;
                this.$dispatch('open-modal', 'owner-form');
            },
            startDelete(action, name) {
                this.delAction = action; this.delName = name;
                this.$dispatch('open-modal', 'owner-delete');
            },
        };
    }
</script>
@endsection
