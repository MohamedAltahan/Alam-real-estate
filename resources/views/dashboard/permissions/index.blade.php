@extends('layouts.dashboard')

@section('title', 'إدارة الصلاحيات')
@section('page-title', 'إدارة الصلاحيات')

@section('content')
<div>
    <x-flash />

    <div class="mb-5">
        <h2 class="text-xl font-bold text-ink">مصفوفة الصلاحيات</h2>
        <p class="text-sm text-gray-500">تحكّم في صلاحيات كل دور لكل وحدة</p>
    </div>

    {{-- تبويبات الأدوار --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($roles as $r)
            <a href="{{ route('dashboard.permissions.index', ['role' => $r->id]) }}"
               class="rounded-full px-4 py-2 text-sm font-medium transition {{ $current && $current->id === $r->id ? 'bg-primary-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                {{ $r->description ?: $r->name }}
            </a>
        @endforeach
    </div>

    @if ($current)
        @if ($current->name === 'super-admin')
            <div class="mb-4 rounded-field bg-accent-100 text-accent-800 text-sm px-4 py-3">مدير النظام يملك جميع الصلاحيات تلقائياً.</div>
        @endif

        <form method="POST" action="{{ route('dashboard.permissions.update') }}">
            @csrf
            <input type="hidden" name="role_id" value="{{ $current->id }}">

            <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                                <th class="text-start font-medium px-4 py-3">الوحدة</th>
                                @foreach ($actions as $al)
                                    <th class="font-medium px-3 py-3 text-center">{{ $al }}</th>
                                @endforeach
                                <th class="font-medium px-3 py-3 text-center">تحديد الكل</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($modules as $mkey => $mlabel)
                                <tr class="hover:bg-gray-50/40" x-data="{ all: false }">
                                    <td class="px-4 py-3 font-medium text-ink whitespace-nowrap">{{ $mlabel }}</td>
                                    @foreach ($actions as $akey => $alabel)
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox" name="permissions[]" value="{{ $mkey }}.{{ $akey }}"
                                                   @checked($assigned->has("{$mkey}.{$akey}"))
                                                   class="perm-{{ $mkey }} rounded border-gray-300 text-primary-900 focus:ring-primary-500/30 w-4 h-4">
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-3 text-center">
                                        <input type="checkbox" x-model="all"
                                               @change="$el.closest('tr').querySelectorAll('.perm-{{ $mkey }}').forEach(c => c.checked = all)"
                                               class="rounded border-gray-300 text-accent-600 focus:ring-accent-500/30 w-4 h-4">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @can('permissions.edit')
                <div class="flex justify-end mt-4">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                        حفظ التغييرات
                    </button>
                </div>
            @endcan
        </form>
    @endif
</div>
@endsection
