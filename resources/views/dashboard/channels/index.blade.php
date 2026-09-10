@extends('layouts.dashboard')

@php
    use App\Models\PublishingChannel;

    $isWebsite = $kind === PublishingChannel::KIND_WEBSITE;
    $title = PublishingChannel::kindLabel($kind);
    $single = $isWebsite ? 'موقع' : 'قناة';
    $routes = $isWebsite
        ? ['index' => 'dashboard.websites.index', 'store' => 'dashboard.websites.store', 'destroy' => 'dashboard.websites.destroy', 'base' => url('dashboard/websites')]
        : ['index' => 'dashboard.social-channels.index', 'store' => 'dashboard.social-channels.store', 'destroy' => 'dashboard.social-channels.destroy', 'base' => url('dashboard/social-channels')];
    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
@endphp

@section('title', $title)
@section('page-title', $title)

@section('content')
<div x-data="channelCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">{{ $title }}</h2>
            <p class="text-sm text-gray-500">{{ number_format($channels->total()) }} {{ $single }}</p>
        </div>
        @can('publishing_channels.create')
            <button type="button" @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة {{ $single }}
            </button>
        @endcan
    </div>

    @include('dashboard.channels._tabs', ['current' => $kind])

    <form method="GET" id="channels-filters" data-live-filters class="mb-4">
        <div class="relative max-w-md">
            <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو الرابط..." autocomplete="off"
                   class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
    </form>

    <div data-results>
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">الأيقونة</th>
                            <th class="text-start font-medium px-4 py-3">الاسم</th>
                            <th class="text-start font-medium px-4 py-3">الرابط</th>
                            <th class="text-start font-medium px-4 py-3">العقارات المنشورة</th>
                            <th class="text-start font-medium px-4 py-3">الحالة</th>
                            <th class="text-start font-medium px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($channels as $channel)
                            @php
                                $editData = [
                                    'id' => $channel->id, 'name' => $channel->name, 'url' => $channel->url,
                                    'is_active' => $channel->is_active, 'icon_url' => $channel->icon_url,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $channels->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">
                                    <span class="grid place-items-center w-10 h-10 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden">
                                        @if ($channel->icon_url)<img src="{{ $channel->icon_url }}" class="w-full h-full object-contain" alt="">@else<span class="text-sm font-bold text-primary-800">{{ mb_substr($channel->name, 0, 1) }}</span>@endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-ink">{{ $channel->name }}</td>
                                <td class="px-4 py-3 max-w-[260px]">
                                    @if ($channel->url)
                                        <a href="{{ $channel->url }}" target="_blank" rel="noopener" class="block text-primary-700 hover:underline truncate"><bdi dir="ltr">{{ $channel->url }}</bdi></a>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($channel->properties_count)
                                        @can('properties.view')
                                            <a href="{{ route('dashboard.properties.index', [$isWebsite ? 'website_id' : 'social_id' => $channel->id]) }}" class="rounded-full bg-primary-50 text-primary-700 hover:bg-primary-100 px-2.5 py-1 text-xs">{{ $channel->properties_count }} عقار</a>
                                        @else
                                            <span class="rounded-full bg-primary-50 text-primary-700 px-2.5 py-1 text-xs">{{ $channel->properties_count }} عقار</span>
                                        @endcan
                                    @else
                                        <span class="text-gray-400 text-xs">لا يوجد</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($channel->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>نشط</span>
                                    @else
                                        <span class="inline-block rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-medium">غير نشط</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('publishing_channels.edit')
                                            <button type="button" @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50 transition" title="تعديل">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                            </button>
                                        @endcan
                                        @can('publishing_channels.delete')
                                            @if ($channel->properties_count)
                                                <span class="grid place-items-center w-8 h-8 rounded-full text-gray-300 cursor-not-allowed" title="لا يمكن حذف {{ $single }} عليه عقارات منشورة"><x-icon.trash /></span>
                                            @else
                                                <button type="button" @click="startDelete('{{ route($routes['destroy'], $channel) }}', @js($channel->name))" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400">لا توجد نتائج مطابقة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $channels->links() }}</div>
    </div>

    @canany(['publishing_channels.create', 'publishing_channels.edit'])
    <x-modal name="channel-form" maxWidth="lg">
        <form :action="action" method="POST" enctype="multipart/form-data">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل {{ $single }}' : 'إضافة {{ $single }} جديد{{ $isWebsite ? '' : 'ة' }}'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'channel-form')" class="text-gray-400 hover:text-gray-700">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم <span class="text-danger">*</span></label>
                    <input name="name" x-model="form.name" required placeholder="{{ $isWebsite ? 'مثال: OLX' : 'مثال: Facebook' }}" class="{{ $field }}">
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الرابط</label>
                    <input name="url" type="url" x-model="form.url" dir="ltr" placeholder="https://" class="{{ $field }}">
                    @error('url')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الأيقونة</label>
                    <div class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50/60 p-3">
                        <div class="grid place-items-center w-16 h-16 rounded-xl bg-white border border-gray-200 overflow-hidden shrink-0">
                            <template x-if="iconSource() && !form.icon_removed"><img :src="iconSource()" alt="" class="w-full h-full object-contain"></template>
                            <template x-if="!iconSource() || form.icon_removed"><span class="text-xl font-bold text-primary-800" x-text="form.name ? form.name.charAt(0) : '؟'"></span></template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="inline-flex cursor-pointer items-center rounded-full bg-white border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 hover:text-primary-800 transition">
                                <span x-text="mode === 'edit' && form.icon_url ? 'استبدال الأيقونة' : 'اختيار صورة'"></span>
                                <input x-ref="icon" type="file" name="icon" accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg" class="sr-only" @change="previewIcon($event)">
                            </label>
                            <p class="mt-1.5 text-xs text-gray-400">PNG أو SVG أو JPG أو WebP — بحد أقصى 2 MB</p>
                            <label x-show="mode === 'edit' && form.icon_url" class="mt-2 inline-flex items-center gap-2 text-xs text-danger cursor-pointer">
                                <input type="checkbox" name="icon_removed" value="1" x-model="form.icon_removed">
                                <span>حذف الأيقونة الحالية</span>
                            </label>
                        </div>
                    </div>
                    @error('icon')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <label class="inline-flex items-center gap-3 cursor-pointer rounded-field border border-gray-200 bg-gray-50 px-4 h-[42px] w-full">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                    <span class="text-sm font-medium text-gray-700">{{ $isWebsite ? 'موقع نشط ويظهر عند نشر العقارات' : 'قناة نشطة وتظهر عند نشر العقارات' }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'channel-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة'"></button>
            </div>
        </form>
    </x-modal>
    @endcanany

    @can('publishing_channels.delete')
    <x-modal name="channel-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف {{ $single }}</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف «<span x-text="delName" class="font-semibold text-ink"></span>»؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'channel-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
    @endcan
</div>

<script>
    function channelCrud() {
        const blank = () => ({ name: '', url: '', is_active: true, icon_url: null, icon_removed: false, icon_preview: null });

        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: blank(),
            init() {
                @if ($errors->any())
                    this.mode = @js(old('_method') === 'PUT' ? 'edit' : 'add');
                    this.action = @js(old('_method') === 'PUT' ? null : route($routes['store'])) || '{{ route($routes['store']) }}';
                    this.form = { ...blank(), name: @js(old('name', '')), url: @js(old('url', '')), is_active: @js((bool) old('is_active', true)) };
                    this.$nextTick(() => this.$dispatch('open-modal', 'channel-form'));
                @endif
            },
            iconSource() {
                return this.form.icon_preview || this.form.icon_url;
            },
            previewIcon(event) {
                const file = event.target.files?.[0];
                this.form.icon_preview = file ? URL.createObjectURL(file) : null;
                this.form.icon_removed = false;
            },
            startAdd() {
                this.mode = 'add';
                this.form = blank();
                this.action = '{{ route($routes['store']) }}';
                if (this.$refs.icon) this.$refs.icon.value = '';
                this.$dispatch('open-modal', 'channel-form');
            },
            startEdit(channel) {
                this.mode = 'edit';
                this.form = { ...blank(), name: channel.name ?? '', url: channel.url ?? '', is_active: Boolean(channel.is_active), icon_url: channel.icon_url ?? null };
                this.action = '{{ $routes['base'] }}/' + channel.id;
                if (this.$refs.icon) this.$refs.icon.value = '';
                this.$dispatch('open-modal', 'channel-form');
            },
            startDelete(action, name) {
                this.delAction = action;
                this.delName = name;
                this.$dispatch('open-modal', 'channel-delete');
            },
        };
    }
</script>
@endsection
