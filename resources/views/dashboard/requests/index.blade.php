@extends('layouts.dashboard')

@section('title', 'طلبات التواصل')
@section('page-title', 'طلبات التواصل')

@section('content')
<div x-data="requestsScreen()">
    <x-flash />

    {{-- العنوان يميناً والفلترين في أقصى الشمال على نفس السطر --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">صندوق الوارد</h2>
            <p class="text-sm text-gray-500">{{ number_format($requests->total()) }} طلب · <span class="text-danger">{{ $unreadCount }} غير مقروء</span></p>
        </div>

        {{-- الفلاتر تُطبَّق فور الاختيار --}}
        <form method="GET" id="requests-filters" data-live-filters class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <select name="type_id" class="appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                    <option value="">كل الأنواع</option>
                    @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div class="relative">
                <select name="status" class="appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                    <option value="">كل الحالات</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>لم يتم التواصل</option>
                    <option value="contacted" @selected(($filters['status'] ?? '') === 'contacted')>تم التواصل</option>
                </select>
                <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </form>
    </div>

    <div data-results>
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
                {{-- القيمة وحدها ltr داخل صندوق عربي، فتُحاذى يميناً وتُقرأ بترتيب صحيح --}}
                <div class="text-xs text-gray-500 space-y-0.5 mt-1">
                    @if ($req->email)<p class="truncate"><span dir="ltr">{{ $req->email }}</span></p>@endif
                    @if ($req->phone)<p><span dir="ltr">{{ $req->phone }}</span></p>@endif
                </div>

                @if ($req->subject)<p class="text-sm font-medium text-ink mt-3">{{ $req->subject }}</p>@endif
                @if ($req->message)<p class="text-sm text-gray-600 mt-1 line-clamp-3">{{ $req->message }}</p>@endif
                @if ($req->property)<p class="text-xs text-primary-600 mt-2">📍 <span dir="ltr">{{ $req->property->reference_code }}</span></p>@endif

                {{-- من عالج الطلب + حالة التحويل لعميل --}}
                @if ($req->handledBy || $req->convertedClient)
                    <div class="mt-3 space-y-1.5 text-xs">
                        @if ($req->handledBy)
                            <p class="flex items-center gap-1.5 text-gray-500">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-gray-400"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                                عالجه: <span class="font-bold text-ink">{{ $req->handledBy->name }}</span>
                            </p>
                        @endif
                        @if ($req->convertedClient)
                            <a href="{{ route('dashboard.clients.show', $req->convertedClient) }}"
                               class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 font-bold hover:bg-success/20 transition">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                عميل في الـ CRM: {{ $req->convertedClient->name }}
                            </a>
                        @endif
                    </div>
                @endif

                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-50">
                    @can('contact_requests.edit')
                        @if ($req->status !== 'contacted')
                            <form method="POST" action="{{ route('dashboard.requests.contacted', $req) }}" class="flex-1">
                                @csrf @method('PUT')
                                <button class="w-full rounded-full bg-primary-900 hover:bg-primary-800 text-white text-xs font-semibold py-2">تم التواصل</button>
                            </form>
                        @endif
                    @endcan

                    @can('clients.create')
                        @unless ($req->isConverted())
                            <button type="button"
                                    @click="startConvert({{ $req->id }}, @js($req->name), @js($req->phone), @js($req->email), @js(optional($duplicates[$req->id] ?? null)->only('id', 'name', 'phone')))"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-full bg-accent-500 hover:bg-accent-400 text-primary-900 text-xs font-bold py-2 transition">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                                تحويل لعميل
                            </button>
                        @endunless
                    @endcan

                    @if ($req->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $req->phone) }}" target="_blank" class="rounded-full bg-success/10 text-success hover:bg-success/20 text-xs font-medium px-3 py-2">واتساب</a>
                    @endif
                    @can('contact_requests.delete')
                        <button @click="delAction='{{ route('dashboard.requests.destroy', $req) }}'; delName=@js($req->name); $dispatch('open-modal','req-delete')" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition"><x-icon.trash /></button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-card bg-white border border-gray-100 py-16 text-center text-gray-400">لا توجد طلبات.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    <x-modal name="req-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف الطلب</h3>
            <p class="text-sm text-gray-500 mb-6">حذف طلب "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal','req-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>

    {{-- ===== تحويل الطلب إلى عميل في الـ CRM ===== --}}
    @php
        $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
        $lbl = 'block text-sm font-medium text-gray-700 mb-1.5';
    @endphp

    <x-modal name="req-convert">
        <form :action="convertAction" method="POST">
            @csrf
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink">تحويل الطلب إلى عميل</h3>
                <button type="button" @click="$dispatch('close-modal','req-convert')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>

            <div class="p-6 space-y-5">
                {{-- بيانات مأخوذة من الطلب --}}
                <div class="rounded-field bg-gray-50 border border-gray-100 p-4 space-y-1.5">
                    <p class="font-bold text-ink" x-text="convert.name"></p>
                    <p class="text-xs text-gray-500" x-show="convert.phone"><span dir="ltr" x-text="convert.phone"></span></p>
                    <p class="text-xs text-gray-500" x-show="convert.email"><span dir="ltr" x-text="convert.email"></span></p>
                </div>

                {{-- تحذير التكرار --}}
                <template x-if="convert.duplicate">
                    <div class="rounded-field bg-warning-soft border border-warning/20 p-4">
                        <p class="text-sm font-bold text-warning mb-1">يوجد عميل مسجَّل بنفس الهاتف أو البريد</p>
                        <p class="text-xs text-gray-600 mb-3">
                            <span x-text="convert.duplicate?.name"></span> —
                            <span dir="ltr" x-text="convert.duplicate?.phone"></span>
                        </p>
                        <label class="flex items-center gap-2 text-sm text-ink cursor-pointer select-none">
                            <input type="checkbox" x-model="convert.useExisting">
                            اربط الطلب بالعميل الموجود بدل إنشاء عميل جديد
                        </label>
                        <input type="hidden" name="existing_client_id" :value="convert.useExisting ? convert.duplicate?.id : ''">
                    </div>
                </template>

                {{-- حقول العميل الجديد --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="! convert.useExisting">
                    <div>
                        <label class="{{ $lbl }}">الوكيل المسؤول</label>
                        <select name="agent_id" class="{{ $field }}">
                            <option value="">— اختر —</option>
                            @foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">مصدر التسويق</label>
                        <select name="source_id" class="{{ $field }}">
                            <option value="">— اختر —</option>
                            @foreach ($sources as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">المرحلة</label>
                        <select name="stage_id" class="{{ $field }}">
                            @foreach ($stages as $s)<option value="{{ $s->id }}" @selected($s->key === 'new')>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">نوع العميل</label>
                        <select name="type_id" class="{{ $field }}">
                            <option value="">— اختر —</option>
                            @foreach ($clientTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">ملاحظات</label>
                        <textarea name="notes" rows="2" x-model="convert.notes" class="{{ $field }}"></textarea>
                    </div>
                </div>

                <p class="text-xs text-gray-500 leading-relaxed">
                    سيُنشأ سجل عميل بهذه البيانات، ويُربط به العقار محل الاستفسار إن وُجد،
                    ويُسجَّل في سجل تواصله من أين جاء — ثم يُعلَّم الطلب كمتواصَل معه.
                </p>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal','req-convert')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm"
                        x-text="convert.useExisting ? 'اربط بالعميل الموجود' : 'إنشاء العميل'"></button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
    function requestsScreen() {
        return {
            delAction: '', delName: '',
            convertAction: '',
            convert: { name: '', phone: '', email: '', notes: '', duplicate: null, useExisting: false },

            startConvert(id, name, phone, email, duplicate) {
                this.convertAction = '{{ url('dashboard/requests') }}/' + id + '/convert';
                this.convert = { name, phone, email, notes: '', duplicate: duplicate || null, useExisting: false };
                this.$dispatch('open-modal', 'req-convert');
            },
        };
    }
</script>
@endpush
@endsection
