@extends('layouts.dashboard')

@section('title', 'طلبات التواصل')
@section('page-title', 'طلبات التواصل')

@section('content')
<div x-data="{ delAction: '', delName: '' }">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">صندوق الوارد</h2>
            <p class="text-sm text-gray-500">{{ number_format($requests->total()) }} طلب · <span class="text-danger">{{ $unreadCount }} غير مقروء</span></p>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-3 mb-5">
        <select name="type_id" class="rounded-field bg-white border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-primary-500">
            <option value="">كل الأنواع</option>
            @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <select name="status" class="rounded-field bg-white border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-primary-500">
            <option value="">كل الحالات</option>
            <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>لم يتم التواصل</option>
            <option value="contacted" @selected(($filters['status'] ?? '') === 'contacted')>تم التواصل</option>
        </select>
        <button class="rounded-field bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-2.5 text-sm">تصفية</button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse ($requests as $req)
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-5 {{ $req->is_read ? '' : 'ring-1 ring-primary-200' }}">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        @unless ($req->is_read)<span class="w-2 h-2 rounded-full bg-primary-600"></span>@endunless
                        <span class="rounded-full bg-info-soft text-info px-2.5 py-0.5 text-xs font-medium">{{ $req->requestType?->name ?? 'طلب' }}</span>
                    </div>
                    @if ($req->status === 'contacted')
                        <span class="rounded-full bg-success-soft text-success px-2.5 py-0.5 text-xs font-medium">تم التواصل</span>
                    @else
                        <span class="rounded-full bg-warning-soft text-warning px-2.5 py-0.5 text-xs font-medium">بانتظار</span>
                    @endif
                </div>

                <h3 class="font-semibold text-ink">{{ $req->name }}</h3>
                <div class="text-xs text-gray-500 space-y-0.5 mt-1" dir="ltr">
                    @if ($req->email)<p class="truncate">{{ $req->email }}</p>@endif
                    @if ($req->phone)<p>{{ $req->phone }}</p>@endif
                </div>

                @if ($req->subject)<p class="text-sm font-medium text-ink mt-3">{{ $req->subject }}</p>@endif
                @if ($req->message)<p class="text-sm text-gray-600 mt-1 line-clamp-3">{{ $req->message }}</p>@endif
                @if ($req->property)<p class="text-xs text-primary-600 mt-2" dir="ltr">📍 {{ $req->property->reference_code }}</p>@endif

                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-50">
                    @can('contact_requests.edit')
                        @if ($req->status !== 'contacted')
                            <form method="POST" action="{{ route('dashboard.requests.contacted', $req) }}" class="flex-1">
                                @csrf @method('PUT')
                                <button class="w-full rounded-field bg-primary-900 hover:bg-primary-800 text-white text-xs font-semibold py-2">تم التواصل</button>
                            </form>
                        @endif
                    @endcan
                    @if ($req->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $req->phone) }}" target="_blank" class="rounded-field bg-success/10 text-success hover:bg-success/20 text-xs font-medium px-3 py-2">واتساب</a>
                    @endif
                    @can('contact_requests.delete')
                        <button @click="delAction='{{ route('dashboard.requests.destroy', $req) }}'; delName=@js($req->name); $dispatch('open-modal','req-delete')" class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger hover:bg-danger/10"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-card bg-white border border-gray-100 py-16 text-center text-gray-400">لا توجد طلبات.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>

    <x-modal name="req-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></span>
            <h3 class="font-bold text-ink mb-1">حذف الطلب</h3>
            <p class="text-sm text-gray-500 mb-6">حذف طلب "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal','req-delete')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-field bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>
@endsection
