@props(['message', 'compact' => false])

@php
    // شارة حالة الرسالة كما في واتساب: ساعة (قيد الإرسال) · ✓ أُرسلت · ✓✓ وصلت · ✓✓ زرقاء قُرئت · ✕ فشلت
    $status = $message->status;

    [$cls, $icon] = match ($status) {
        \App\Models\WhatsappMessage::SENT => ['bg-gray-100 text-gray-600', 'check'],
        \App\Models\WhatsappMessage::DELIVERED => ['bg-success-soft text-success', 'double'],
        \App\Models\WhatsappMessage::READ => ['bg-info-soft text-info', 'double'],
        \App\Models\WhatsappMessage::FAILED => ['bg-danger/10 text-danger', 'x'],
        default => ['bg-gray-100 text-gray-500', 'clock'],
    };

    $at = $message->statusAt();
    $failed = $status === \App\Models\WhatsappMessage::FAILED;

    $title = collect([
        $message->sent_at ? 'أُرسلت: '.$message->sent_at->format('Y-m-d H:i') : null,
        $message->delivered_at ? 'وصلت: '.$message->delivered_at->format('Y-m-d H:i') : null,
        $message->read_at ? 'قُرئت: '.$message->read_at->format('Y-m-d H:i') : null,
        $failed && $message->error ? 'الخطأ: '.$message->error : null,
    ])->filter()->implode("\n");
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap '.$cls]) }} title="{{ $title }}">
    @if ($icon === 'check')
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m4 12.5 5 5L20 7"/></svg>
    @elseif ($icon === 'double')
        <svg width="16" height="14" viewBox="0 0 28 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m2 12.5 5 5L17 7"/><path d="m11 17.5 1.5 1.5L26 7"/></svg>
    @elseif ($icon === 'x')
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    @else
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
    @endif
    {{ $message->statusLabel() }}
</span>

@unless ($compact)
    @if ($failed && $message->error)
        <span class="block text-[11px] text-danger/80 mt-1 max-w-[220px] truncate" title="{{ $message->error }}">{{ $message->error }}</span>
    @elseif ($at)
        <span class="block text-[11px] text-gray-400 mt-1 tabular-nums"><bdi dir="ltr">{{ $at->format('Y-m-d H:i') }}</bdi></span>
    @endif
@endunless
