{{--
    نتيجة المعاينة داخل الجداول: تغييرها يُحفظ فوراً.
    $ajax = true داخل نافذة «العقارات المستهدفة» (الحفظ عبر saveOutcome بدون إغلاقها).
--}}
@php
    use App\Support\ClientFields;
@endphp
@can('clients.edit')
    <form method="POST" action="{{ route('dashboard.viewings.outcome', $viewing) }}"
          x-data="viewingOutcome({ outcome: @js($viewing->outcome) })"
          @if ($ajax ?? false) @submit.prevent="saveOutcome($event)" @endif
          class="flex items-center gap-1.5 flex-wrap">
        @csrf @method('PATCH')
        <input type="hidden" name="viewing_id" value="{{ $viewing->id }}">
        <select name="outcome" x-model="outcome" @change="submit()"
                class="appearance-none rounded-full border-0 ps-3 pe-8 py-1 text-xs font-semibold cursor-pointer {{ ClientFields::outcomeTone($viewing->outcome) }}">
            @foreach (ClientFields::OUTCOMES as $value => $text)<option value="{{ $value }}" @selected($viewing->outcome === $value)>{{ $text }}</option>@endforeach
        </select>
    </form>
@else
    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ClientFields::outcomeTone($viewing->outcome) }}">{{ ClientFields::outcomeLabel($viewing->outcome) }}</span>
@endcan
