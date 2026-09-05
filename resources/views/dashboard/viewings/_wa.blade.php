{{--
    علامتا واتساب للمعاينة: (1) أُبلغ المالك ببيانات العميل · (2) أُرسلت المتابعة للعميل.
    زر الإرسال يحمل الحمولة في data-wa-send وتفتحه نافذة _wa-modal (whatsapp.js).
    يتوقع $viewing مع property.owner.contacts و client.
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
            'done' => 'أُبلغ المالك',
            'todo' => 'إبلاغ المالك',
            'missing' => 'لم يُبلَّغ المالك',
            'enabled' => true,
            'hint' => 'إرسال بيانات العميل وموعد المعاينة لأحد أرقام المالك',
        ],
        [
            'kind' => WhatsAppTemplates::KIND_CLIENT,
            'at' => $viewing->client_followed_up_at,
            'done' => 'أُرسلت المتابعة',
            'todo' => 'متابعة العميل',
            'missing' => 'لم تُرسل المتابعة',
            'enabled' => $decided,
            'hint' => $decided ? 'إرسال نتيجة المعاينة للعميل' : 'سجّل نتيجة المعاينة أولاً',
        ],
    ];
    $chip = 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap';
    $waIcon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
@endphp

<div class="flex gap-1.5 {{ ($wide ?? false) ? 'flex-wrap items-center' : 'flex-col items-start' }}">
    @foreach ($steps as $step)
        @if ($step['at'])
            <span class="{{ $chip }} bg-success-soft text-success" title="أُرسلت {{ $step['at']->format('Y-m-d H:i') }}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                {{ $step['done'] }}
                @if ($canSend)
                    <button type="button" data-wa-send="{{ json_encode($whatsapp->sendPayload($viewing, $step['kind']), JSON_UNESCAPED_UNICODE) }}" title="إعادة الإرسال" class="ms-0.5 text-success/70 hover:text-success">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>
                    </button>
                @endif
            </span>
        @elseif ($canSend)
            <button type="button" data-wa-send="{{ json_encode($whatsapp->sendPayload($viewing, $step['kind']), JSON_UNESCAPED_UNICODE) }}"
                    @disabled(! $step['enabled']) title="{{ $step['hint'] }}"
                    class="{{ $chip }} border border-success/40 text-success bg-white hover:bg-success-soft transition disabled:border-gray-200 disabled:text-gray-400 disabled:bg-gray-50 disabled:cursor-not-allowed">
                {!! $waIcon !!}{{ $step['todo'] }}
            </button>
        @else
            <span class="{{ $chip }} bg-gray-100 text-gray-500">{{ $step['missing'] }}</span>
        @endif
    @endforeach
</div>
