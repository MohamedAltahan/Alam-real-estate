@extends('layouts.dashboard')

@section('title', 'إدارة المناطق')
@section('page-title', 'إدارة المناطق')

@section('content')
<div x-data="areaCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">إدارة المناطق</h2>
            <p class="text-sm text-gray-500">{{ number_format($areas->total()) }} منطقة مسجلة</p>
        </div>
        @can('areas.create')
            <button type="button" @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة منطقة
            </button>
        @endcan
    </div>

    @include('dashboard.areas._tabs', ['current' => 'areas'])

    <form method="GET" id="areas-filters" data-live-filters class="flex flex-wrap items-center gap-3 mb-4">
        <div class="relative flex-1 min-w-[220px] max-w-md">
            <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث باسم المنطقة..." autocomplete="off"
                   class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
        <div class="relative">
            <select name="city_id" class="appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                <option value="">كل المدن</option>
                @foreach ($cities as $city)<option value="{{ $city->id }}" @selected(($filters['city_id'] ?? '') == $city->id)>{{ $city->name }}</option>@endforeach
            </select>
            <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
        </div>
    </form>

    <div data-results>
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">اسم المنطقة</th>
                            <th class="text-start font-medium px-4 py-3">الاسم بالإنجليزية</th>
                            <th class="text-start font-medium px-4 py-3">المدينة</th>
                            <th class="text-start font-medium px-4 py-3">الاستخدام</th>
                            <th class="text-start font-medium px-4 py-3">الترتيب</th>
                            <th class="text-start font-medium px-4 py-3">الحالة</th>
                            <th class="text-start font-medium px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($areas as $area)
                            @php
                                $nameAr = $area->getTranslation('name', 'ar', false);
                                $nameEn = $area->getTranslation('name', 'en', false);
                                $onHomepage = $homepageAreaIds->contains($area->id);
                                $isUsed = $area->properties_count + $area->needs_count + $area->owners_count > 0 || $onHomepage;
                                $editData = [
                                    'id' => $area->id,
                                    'name_ar' => $nameAr,
                                    'name_en' => $nameEn,
                                    'city_id' => $area->city_id,
                                    'sort_order' => $area->sort_order,
                                    'is_active' => $area->is_active,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $areas->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 font-semibold text-ink">{{ $nameAr }}</td>
                                <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $nameEn ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $area->city?->name ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if ($area->properties_count)
                                            <span class="rounded-full bg-primary-50 text-primary-700 px-2.5 py-1 text-xs">{{ $area->properties_count }} عقار</span>
                                        @endif
                                        @if ($area->needs_count)
                                            <span class="rounded-full bg-info-soft text-info px-2.5 py-1 text-xs">{{ $area->needs_count }} طلب عميل</span>
                                        @endif
                                        @if ($area->owners_count)
                                            <span class="rounded-full bg-accent-50 text-accent-800 px-2.5 py-1 text-xs">{{ $area->owners_count }} مالك</span>
                                        @endif
                                        @if ($onHomepage)
                                            <span class="rounded-full bg-success-soft text-success px-2.5 py-1 text-xs">ظاهرة في الرئيسية</span>
                                        @endif
                                        @unless ($isUsed)
                                            <span class="text-gray-400 text-xs">غير مستخدمة</span>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 tabular-nums">{{ $area->sort_order }}</td>
                                <td class="px-4 py-3">
                                    @if ($area->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>نشطة</span>
                                    @else
                                        <span class="inline-block rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-medium">غير نشطة</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('areas.edit')
                                            <button type="button" @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50 transition" title="تعديل">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                            </button>
                                        @endcan
                                        @can('areas.delete')
                                            @if ($isUsed)
                                                <span class="grid place-items-center w-8 h-8 rounded-full text-gray-300 cursor-not-allowed" title="لا يمكن حذف منطقة مستخدمة"><x-icon.trash /></span>
                                            @else
                                                <button type="button" @click="startDelete('{{ route('dashboard.areas.destroy', $area) }}', @js($nameAr))" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">لا توجد مناطق مطابقة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $areas->links() }}</div>
    </div>

    @canany(['areas.create', 'areas.edit'])
    <x-modal name="area-form" maxWidth="lg">
        <form :action="action" method="POST">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل المنطقة' : 'إضافة منطقة جديدة'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'area-form')" class="text-gray-400 hover:text-gray-700">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم المنطقة بالعربية <span class="text-danger">*</span></label>
                    <input name="name[ar]" x-model="form.name_ar" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                    @error('name.ar')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم المنطقة بالإنجليزية</label>
                    <input name="name[en]" x-model="form.name_en" dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">المدينة</label>
                    <select name="city_id" x-model="form.city_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="">— بدون مدينة —</option>
                        @foreach ($cities as $city)<option value="{{ $city->id }}">{{ $city->name }}</option>@endforeach
                    </select>
                    @error('city_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الترتيب <span class="text-danger">*</span></label>
                    <input name="sort_order" type="number" min="0" x-model="form.sort_order" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                    <p class="text-xs text-gray-400 mt-1">الرقم الأصغر يظهر أولًا في القوائم.</p>
                </div>
                <div class="flex items-end pb-1 sm:col-span-2">
                    <label class="inline-flex items-center gap-3 cursor-pointer rounded-field border border-gray-200 bg-gray-50 px-4 h-[42px] w-full">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                        <span class="text-sm font-medium text-gray-700">منطقة نشطة ومتاحة للاختيار</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'area-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة المنطقة'"></button>
            </div>
        </form>
    </x-modal>
    @endcanany

    @can('areas.delete')
    <x-modal name="area-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف المنطقة</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف «<span x-text="delName" class="font-semibold text-ink"></span>»؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'area-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
    @endcan
</div>

<script>
    function areaCrud() {
        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: { name_ar: '', name_en: '', city_id: @js((string) ($filters['city_id'] ?? '')), sort_order: {{ $nextSortOrder }}, is_active: true },
            init() {
                @if ($errors->any())
                    this.action = '{{ route('dashboard.areas.store') }}';
                    this.form = { name_ar: @js(old('name.ar', '')), name_en: @js(old('name.en', '')), city_id: @js((string) old('city_id', '')), sort_order: @js(old('sort_order', $nextSortOrder)), is_active: true };
                    this.$nextTick(() => this.$dispatch('open-modal', 'area-form'));
                @endif
            },
            startAdd() {
                this.mode = 'add';
                this.form = { name_ar: '', name_en: '', city_id: @js((string) ($filters['city_id'] ?? '')), sort_order: {{ $nextSortOrder }}, is_active: true };
                this.action = '{{ route('dashboard.areas.store') }}';
                this.$dispatch('open-modal', 'area-form');
            },
            startEdit(area) {
                this.mode = 'edit';
                this.form = {
                    name_ar: area.name_ar ?? '',
                    name_en: area.name_en ?? '',
                    city_id: area.city_id ? String(area.city_id) : '',
                    sort_order: area.sort_order ?? 0,
                    is_active: Boolean(area.is_active),
                };
                this.action = '{{ url('dashboard/areas') }}/' + area.id;
                this.$dispatch('open-modal', 'area-form');
            },
            startDelete(action, name) {
                this.delAction = action;
                this.delName = name;
                this.$dispatch('open-modal', 'area-delete');
            },
        };
    }
</script>
@endsection
