@extends('layouts.dashboard')

@section('title', 'أنواع العقارات')
@section('page-title', 'أنواع العقارات')

@section('content')
<div x-data="unitTypeCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">أنواع العقارات</h2>
            <p class="text-sm text-gray-500">{{ number_format($unitTypes->total()) }} نوع مسجل</p>
        </div>
        @can('unit_types.create')
            <button type="button" @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة نوع
            </button>
        @endcan
    </div>

    <x-filter-bar id="unit-types-filters" cols="xl:grid-cols-4" :reset="array_filter($filters) ? route('dashboard.unit-types.index') : null">
        <x-filter-input label="بحث" name="search" :value="$filters['search'] ?? ''" type="search" search
                        placeholder="باسم النوع..." span="col-span-2 md:col-span-1" />
        <x-filter-select label="التصنيف" name="category" placeholder="كل التصنيفات"
                         :options="\App\Models\UnitType::CATEGORIES" :selected="$filters['category'] ?? null" />
    </x-filter-bar>

    <div data-results>
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                        <th class="text-start font-medium px-4 py-3">اسم النوع</th>
                            <th class="text-start font-medium px-4 py-3">الاسم بالإنجليزية</th>
                            <th class="text-start font-medium px-4 py-3">التصنيف</th>
                            <th class="text-start font-medium px-4 py-3">الاستخدام</th>
                            <th class="text-start font-medium px-4 py-3">الحالة</th>
                            <th class="text-start font-medium px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($unitTypes as $unitType)
                            @php
                                $nameAr = $unitType->getTranslation('name', 'ar', false);
                                $nameEn = $unitType->getTranslation('name', 'en', false);
                                $isUsed = $unitType->properties_count + $unitType->needs_count > 0;
                                $editData = [
                                    'id' => $unitType->id,
                                    'name_ar' => $nameAr,
                                    'name_en' => $nameEn,
                                    'category' => $unitType->category ?: 'residential',
                                    'is_active' => $unitType->is_active,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $unitTypes->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 font-semibold text-ink">{{ $nameAr }}</td>
                                <td class="px-4 py-3 text-gray-500"><bdi dir="ltr">{{ $nameEn ?: '—' }}</bdi></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $unitType->category === 'commercial' ? 'bg-accent-100 text-accent-800' : 'bg-primary-50 text-primary-700' }}">{{ $unitType->categoryLabel() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if ($unitType->properties_count)
                                            <span class="rounded-full bg-primary-50 text-primary-700 px-2.5 py-1 text-xs">{{ $unitType->properties_count }} عقار</span>
                                        @endif
                                        @if ($unitType->needs_count)
                                            <span class="rounded-full bg-info-soft text-info px-2.5 py-1 text-xs">{{ $unitType->needs_count }} طلب عميل</span>
                                        @endif
                                        @unless ($isUsed)
                                            <span class="text-gray-400 text-xs">غير مستخدم</span>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($unitType->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>نشط</span>
                                    @else
                                        <span class="inline-block rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-medium">غير نشط</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('unit_types.edit')
                                            <button type="button" @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50 transition" title="تعديل">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                            </button>
                                        @endcan
                                        @can('unit_types.delete')
                                            @if ($isUsed)
                                                <span class="grid place-items-center w-8 h-8 rounded-full text-gray-300 cursor-not-allowed" title="لا يمكن حذف نوع مستخدم"><x-icon.trash /></span>
                                            @else
                                                <button type="button" @click="startDelete('{{ route('dashboard.unit-types.destroy', $unitType) }}', @js($nameAr))" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400">لا توجد أنواع مطابقة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $unitTypes->links() }}</div>
    </div>

    @canany(['unit_types.create', 'unit_types.edit'])
    <x-modal name="unit-type-form" maxWidth="lg">
        <form :action="action" method="POST">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل نوع العقار' : 'إضافة نوع عقار جديد'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'unit-type-form')" class="text-gray-400 hover:text-gray-700">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم النوع بالعربية <span class="text-danger">*</span></label>
                    <input name="name[ar]" x-model="form.name_ar" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم النوع بالإنجليزية</label>
                    <input name="name[en]" x-model="form.name_en" dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">التصنيف <span class="text-danger">*</span></label>
                    <select name="category" x-model="form.category" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        @foreach (\App\Models\UnitType::CATEGORIES as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">يحدد أنواع الوحدات التي تظهر عند اختيار تصنيف العقار.</p>
                    @error('category')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-3 cursor-pointer rounded-field border border-gray-200 bg-gray-50 px-4 h-[42px] w-full">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                        <span class="text-sm font-medium text-gray-700">نوع نشط ومتاح في الفلاتر</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'unit-type-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة النوع'"></button>
            </div>
        </form>
    </x-modal>
    @endcanany

    @can('unit_types.delete')
    <x-modal name="unit-type-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف نوع العقار</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف «<span x-text="delName" class="font-semibold text-ink"></span>»؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'unit-type-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
    @endcan
</div>

<script>
    function unitTypeCrud() {
        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: { name_ar: '', name_en: '', category: 'residential', is_active: true },
            init() {
                @if ($errors->any())
                    this.action = '{{ route('dashboard.unit-types.store') }}';
                    this.form = { name_ar: @js(old('name.ar', '')), name_en: @js(old('name.en', '')), category: @js(old('category', 'residential')), is_active: true };
                    this.$nextTick(() => this.$dispatch('open-modal', 'unit-type-form'));
                @endif
            },
            startAdd() {
                this.mode = 'add';
                this.form = { name_ar: '', name_en: '', category: 'residential', is_active: true };
                this.action = '{{ route('dashboard.unit-types.store') }}';
                this.$dispatch('open-modal', 'unit-type-form');
            },
            startEdit(unitType) {
                this.mode = 'edit';
                this.form = {
                    name_ar: unitType.name_ar ?? '',
                    name_en: unitType.name_en ?? '',
                    category: unitType.category ?? 'residential',
                    is_active: Boolean(unitType.is_active),
                };
                this.action = '{{ url('dashboard/unit-types') }}/' + unitType.id;
                this.$dispatch('open-modal', 'unit-type-form');
            },
            startDelete(action, name) {
                this.delAction = action;
                this.delName = name;
                this.$dispatch('open-modal', 'unit-type-delete');
            },
        };
    }
</script>
@endsection
