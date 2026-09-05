{{--
    محتوى نافذة «العقارات المستهدفة» في قائمة العملاء — يُجلب بـ XHR عند فتح النافذة
    (clients.viewings) حتى لا تُحمَّل الصفحة بحمولات كل العملاء دفعة واحدة.
    يعرض احتياج العميل ثم كل معاينة: العقار، الموعد، تغيير النتيجة، وعلامتا واتساب.
--}}
@php
    use App\Support\ClientFields;
@endphp

@if ($client->needs->isNotEmpty())
    <div class="rounded-2xl bg-gray-50 px-4 py-3 mb-5">
        <p class="text-xs text-gray-400 mb-1.5">احتياج العقار</p>
        <ul class="text-sm text-ink space-y-1">
            @foreach ($client->needs as $need)<li>{{ $need->describe() }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="space-y-3">
    @forelse ($client->viewings as $viewing)
        <article class="flex gap-3 rounded-2xl border border-gray-100 p-3">
            <span class="w-16 h-14 rounded-xl bg-gray-100 overflow-hidden shrink-0">
                @if ($viewing->property?->cover_url)<img src="{{ $viewing->property->cover_url }}" class="w-full h-full object-cover" alt="">@endif
            </span>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    @can('properties.view')
                        <a href="{{ $viewing->property ? route('dashboard.properties.show', $viewing->property) : '#' }}"
                           class="font-semibold text-sm text-ink hover:text-primary-700" dir="ltr">{{ $viewing->property?->reference_code ?: '—' }}</a>
                    @else
                        <span class="font-semibold text-sm text-ink" dir="ltr">{{ $viewing->property?->reference_code ?: '—' }}</span>
                    @endcan

                    {{-- تغيير حالة المعاينة بدون إغلاق النافذة --}}
                    @can('clients.edit')
                        <form method="POST" action="{{ route('dashboard.viewings.outcome', $viewing) }}" @change="saveOutcome($event)">
                            @csrf @method('PATCH')
                            <select name="outcome" class="appearance-none rounded-full border-0 ps-3 pe-8 py-1 text-xs font-semibold cursor-pointer {{ ClientFields::outcomeTone($viewing->outcome) }}">
                                @foreach (ClientFields::OUTCOMES as $value => $text)<option value="{{ $value }}" @selected($viewing->outcome === $value)>{{ $text }}</option>@endforeach
                            </select>
                        </form>
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ ClientFields::outcomeTone($viewing->outcome) }}">{{ ClientFields::outcomeLabel($viewing->outcome) }}</span>
                    @endcan
                </div>

                <p class="text-xs text-gray-500 truncate">{{ collect([$viewing->property?->title, $viewing->property?->area?->name])->filter()->implode(' · ') }}</p>
                <p class="text-xs text-gray-600 mt-1">
                    <span class="text-gray-400">الموعد:</span> <span dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d H:i') ?: '—' }}</span>
                    · {{ $viewing->in_person ? 'حضوري' : 'غير حضوري' }}
                </p>
                @if ($viewing->notes)<p class="text-xs text-gray-600 mt-1 whitespace-pre-line">{{ $viewing->notes }}</p>@endif

                <div class="mt-2 pt-2 border-t border-gray-50 flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] text-gray-400">واتساب:</span>
                    @include('dashboard.viewings._wa', ['viewing' => $viewing, 'wide' => true])
                </div>
            </div>
        </article>
    @empty
        <p class="py-8 text-center text-sm text-gray-400">لا توجد معاينات مسجلة لهذا العميل حتى الآن.</p>
    @endforelse
</div>
