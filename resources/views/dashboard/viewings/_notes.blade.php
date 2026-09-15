{{-- خلية ملاحظة المعاينة: زر يفتح نافذة التعديل (لمن يملك clients.edit) أو نص مختصر --}}
@php
    $notesPayload = json_encode([
        'action' => route('dashboard.viewings.notes', $viewing),
        'notes' => (string) ($viewing->notes ?? ''),
        'title' => 'ملاحظة المعاينة',
        'subtitle' => collect([$viewing->client?->name, $viewing->property?->reference_code ? '#'.$viewing->property->reference_code : null, $viewing->scheduled_at?->format('Y-m-d')])->filter()->implode(' · '),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
@can('clients.edit')
    <button type="button" data-viewing-notes="{{ $notesPayload }}" title="{{ $viewing->notes ?: 'إضافة ملاحظة' }}"
            class="group inline-flex items-center gap-1.5 max-w-[220px] text-start text-xs rounded-full px-2.5 py-1 -ms-2.5 hover:bg-primary-50 transition {{ $viewing->notes ? 'text-gray-600' : 'text-gray-400' }}">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-primary-600 opacity-60 group-hover:opacity-100"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        <span class="truncate">{{ $viewing->notes ?: 'إضافة ملاحظة' }}</span>
    </button>
@else
    <span class="block truncate max-w-[200px] text-xs text-gray-500" title="{{ $viewing->notes }}">{{ $viewing->notes ?: '—' }}</span>
@endcan
