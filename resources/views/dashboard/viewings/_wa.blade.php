{{--
    علامتا واتساب للمعاينة: (1) أُبلغ مسؤول العقار ببيانات العميل · (2) أُرسلت نتيجة المعاينة للمسؤول.
    زر الإرسال يحمل الحمولة في data-wa-send وتفتحه نافذة _wa-modal (whatsapp.js).
    زر النتيجة قبل تسجيلها يحمل data-wa-blocked فيعرض «يجب اختيار النتيجة أولاً» فوراً عند التمرير وعند النقر.
    كل التلميحات هنا بـ data-hint لا title: تظهر فور الاقتراب بلا تأخير المتصفح (whatsapp.js).
    يتوقع $viewing مع property.contacts و client.
--}}
@inject('whatsapp', 'App\Services\WhatsApp\WhatsAppService')
@php
    use App\Models\ClientViewing;
    use App\Support\WhatsAppTemplates;

    $canSend = auth()->user()?->can('clients.edit') ?? false;
    $decided = $viewing->outcome !== ClientViewing::OUTCOME_PENDING;
    $steps = [
        [
            'kind' => WhatsAppTemplates::KIND_OWNER,
            'at' => $viewing->owner_notified_at,
            'done' => 'أُبلغ المسؤول',
            'todo' => 'إبلاغ المسؤول',
            'missing' => 'لم يُبلَّغ المسؤول',
            'enabled' => true,
            'hint' => 'إرسال بيانات العميل وموعد المعاينة لأحد المسؤولين عن العقار',
        ],
        [
            'kind' => WhatsAppTemplates::KIND_CLIENT,
            'at' => $viewing->client_followed_up_at,
            'done' => 'أُرسلت النتيجة',
            'todo' => 'إرسال النتيجة',
            'missing' => 'لم تُرسل النتيجة',
            'enabled' => $decided,
            'hint' => $decided ? 'إرسال نتيجة المعاينة لأحد المسؤولين عن العقار' : 'يجب اختيار النتيجة أولاً',
        ],
    ];
    $chip = 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap';
    $waIcon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
@endphp

<div class="flex gap-1.5 {{ ($wide ?? false) ? 'flex-wrap items-center' : 'flex-col items-start' }}">
    @foreach ($steps as $step)
        @if ($step['at'])
            <span class="{{ $chip }} bg-success-soft text-success" data-hint="أُرسلت {{ $step['at']->format('Y-m-d H:i') }}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                {{ $step['done'] }}
                @if ($canSend)
                    <button type="button" data-wa-send="{{ json_encode($whatsapp->sendPayload($viewing, $step['kind']), JSON_UNESCAPED_UNICODE) }}"
                            data-hint="إعادة الإرسال" aria-label="إعادة الإرسال" class="ms-0.5 text-success/70 hover:text-success cursor-pointer">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>
                    </button>
                @endif
            </span>
        @elseif ($canSend && ! $step['enabled'])
            <button type="button" data-wa-blocked="{{ $step['hint'] }}" aria-label="{{ $step['hint'] }}" aria-disabled="true"
                    class="{{ $chip }} border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed">
                {!! $waIcon !!}{{ $step['todo'] }}
            </button>
        @elseif ($canSend)
            <button type="button" data-wa-send="{{ json_encode($whatsapp->sendPayload($viewing, $step['kind']), JSON_UNESCAPED_UNICODE) }}"
                    data-hint="{{ $step['hint'] }}"
                    class="{{ $chip }} border border-success/40 text-success bg-white hover:bg-success-soft transition cursor-pointer">
                {!! $waIcon !!}{{ $step['todo'] }}
            </button>
        @else
            <span class="{{ $chip }} bg-gray-100 text-gray-500">{{ $step['missing'] }}</span>
        @endif
    @endforeach
</div>
