{{--
    نتيجة المعاينة داخل الجداول: تغييرها يُحفظ فوراً.
    «تم اختيار العقار» لعقار إيجار يُظهر حقل تاريخ انتهاء العقد ويُحفظ عند اختياره،
    والفحص اليومي يحوّل النتيجة بعد التاريخ إلى «إخلاء العقار».
    $ajax = true داخل نافذة «العقارات المستهدفة» (الحفظ عبر saveOutcome بدون إغلاقها).
--}}
@php
    use App\Support\ClientFields;

    $rent = $viewing->property?->purpose === 'rent';
    $ends = $viewing->contract_ends_at?->format('Y-m-d');
    $showEnds = $ends && in_array($viewing->outcome, ['chosen', 'vacated'], true);
    $rowError = $errors->has('contract_ends_at') && (int) old('viewing_id') === $viewing->id ? $errors->first('contract_ends_at') : null;
@endphp
@can('clients.edit')
    <form method="POST" action="{{ route('dashboard.viewings.outcome', $viewing) }}"
          x-data="viewingOutcome({ outcome: @js($viewing->outcome), ends: @js($ends ?? ''), rent: @js($rent) })"
          @if ($ajax ?? false) @submit.prevent="saveOutcome($event)" @endif
          class="flex items-center gap-1.5 flex-wrap">
        @csrf @method('PATCH')
        <input type="hidden" name="viewing_id" value="{{ $viewing->id }}">
        <select name="outcome" x-model="outcome" @change="onOutcomeChange()"
                class="appearance-none rounded-full border-0 ps-3 pe-8 py-1 text-xs font-semibold cursor-pointer {{ ClientFields::outcomeTone($viewing->outcome) }}">
            @foreach (ClientFields::OUTCOMES as $value => $text)<option value="{{ $value }}" @selected($viewing->outcome === $value)>{{ $text }}</option>@endforeach
        </select>
        <span x-show="askDate" x-cloak class="inline-flex items-center gap-1">
            <input x-datetime.date x-model="ends" name="contract_ends_at" @change="onDateChange($event)" placeholder="تاريخ انتهاء العقد"
                   class="w-44 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs" dir="ltr">
        </span>
        @if ($showEnds && $viewing->outcome === 'vacated')<span class="text-[11px] text-gray-400">انتهى العقد <bdi dir="ltr">{{ $ends }}</bdi></span>@endif
        @if ($rowError)<span class="block w-full text-[11px] text-danger font-semibold">{{ $rowError }}</span>@endif
    </form>
@else
    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ClientFields::outcomeTone($viewing->outcome) }}">{{ ClientFields::outcomeLabel($viewing->outcome) }}</span>
    @if ($showEnds)<span class="block text-[11px] text-gray-400 mt-1">{{ $viewing->outcome === 'vacated' ? 'انتهى العقد' : 'ينتهي العقد' }} <bdi dir="ltr">{{ $ends }}</bdi></span>@endif
@endcan
