@extends('layouts.dashboard')

@section('title', 'ملاك العقارات')
@section('page-title', 'ملاك العقارات')

@php
    // ٢٨٠٠٠٠٠ → 2.8M · ٤٥٠٠٠٠ → 450K
    $money = function ($v) {
        $v = (float) $v;
        if ($v >= 1000000) return rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.').'M';
        if ($v >= 1000) return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'K';
        return number_format($v);
    };
    $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
@endphp

@section('content')
<div x-data="ownerCrud()">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">ملاك العقارات</h2>
            <p class="text-sm text-gray-500">{{ number_format($owners->total()) }} مالك</p>
        </div>

        <div class="flex items-center gap-3">
            <form method="GET" id="owners-filters" data-live-filters class="relative w-[260px] max-w-[55vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث..." autocomplete="off"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </form>

            @can('property_owners.create')
                <button @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-5 h-11 text-sm transition shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    إضافة مالك
                </button>
            @endcan
        </div>
    </div>

    <div data-results>
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3">المالك</th>
                        <th class="text-start font-medium px-4 py-3">الهاتف</th>
                        <th class="text-start font-medium px-4 py-3">العقار</th>
                        <th class="text-start font-medium px-4 py-3">القيمة الاجمالية</th>
                        <th class="text-start font-medium px-4 py-3">الوكيل</th>
                        <th class="text-start font-medium px-4 py-3">الحالة</th>
                        <th class="text-start font-medium px-4 py-3">تاريخ الانضمام</th>
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
                                        <span class="block text-xs text-gray-400 truncate"><span dir="ltr">{{ $o->email ?: '—' }}</span></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><span dir="ltr">{{ $o->phone }}</span></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-gray-600">
                                    <span class="font-bold text-ink tabular-nums">{{ $o->properties_count }}</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-gray-400"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/></svg>
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-accent-700 tabular-nums">{{ $money($o->properties_sum_price) }} KD</td>
                            <td class="px-4 py-3 text-gray-600">{{ $o->latestProperty?->agent?->name ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($o->status === 'active')
                                    <span class="inline-flex items-center rounded-md bg-success-soft text-success px-2.5 py-1 text-xs font-bold">نشط</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-bold">غير نشط</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $months[(int) $o->created_at->format('n')] }} {{ $o->created_at->format('Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    @can('property_owners.edit')
                                        <button @click='startEdit(@json($editData))'
                                                class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                        </button>
                                    @endcan
                                    @can('property_owners.delete')
                                        <button @click="startDelete('{{ route('dashboard.owners.destroy', $o) }}', @js($o->name))"
                                                class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف">
                                            <x-icon.trash />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">لا يوجد ملّاك.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $owners->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

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
                <button type="button" @click="$dispatch('close-modal', 'owner-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة المالك'"></button>
            </div>
        </form>
    </x-modal>

    {{-- مودال الحذف --}}
    <x-modal name="owner-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف المالك</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'owner-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
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
