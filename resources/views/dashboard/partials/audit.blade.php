{{-- سجل التعديلات: من عدّل، متى، القيمة القديمة ← الجديدة --}}
<section class="rounded-card bg-white border border-gray-100 shadow-sm p-6" x-data="{ showAll: false }">
    <h3 class="font-bold text-ink mb-4">سجل التعديلات <span class="text-gray-400 font-normal text-sm">({{ $auditLogs->count() }})</span></h3>

    <div class="space-y-3">
        @forelse ($auditLogs as $index => $log)
            <article class="flex gap-3" @if ($index >= 8) x-show="showAll" x-cloak @endif>
                <span class="w-2 h-2 rounded-full mt-2 shrink-0 {{ $log['action'] === 'deleted' || str_ends_with($log['action'], '_removed') ? 'bg-danger' : 'bg-primary-300' }}"></span>
                <div class="flex-1 min-w-0 pb-3 border-b border-gray-50">
                    <div class="flex flex-wrap items-center gap-2">
                        <strong class="text-sm text-ink">{{ $log['label'] }}</strong>
                        @if ($log['subject'])<span class="text-[11px] rounded-full bg-gray-100 text-gray-600 px-2 py-0.5" dir="auto">{{ $log['subject'] }}</span>@endif
                        <span class="text-xs text-gray-400 ms-auto" dir="ltr">{{ $log['at']?->format('Y-m-d H:i') }}</span>
                    </div>

                    @if ($log['lines'])
                        <ul class="mt-1.5 space-y-1 text-xs">
                            @foreach ($log['lines'] as $line)
                                <li class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-gray-400">{{ $line['field'] }}:</span>
                                    @if ($line['old'] !== null)
                                        <span class="line-through text-gray-400 break-all">{{ $line['old'] }}</span>
                                    @endif
                                    @if ($line['old'] !== null && $line['new'] !== null)
                                        <span class="text-gray-300">←</span>
                                    @endif
                                    @if ($line['new'] !== null)
                                        <span class="font-semibold text-ink break-all">{{ $line['new'] }}</span>
                                    @elseif ($line['old'] !== null)
                                        <span class="text-danger text-[11px]">(حُذف)</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="text-[11px] text-gray-400 mt-1">بواسطة {{ $log['user'] ?? 'النظام' }}</p>
                </div>
            </article>
        @empty
            <p class="text-center text-sm text-gray-400 py-6">لا توجد تعديلات مسجلة بعد.</p>
        @endforelse
    </div>

    @if ($auditLogs->count() > 8)
        <button type="button" @click="showAll = ! showAll" class="mt-3 text-sm text-primary-700 font-semibold hover:underline"
                x-text="showAll ? 'عرض أقل' : 'عرض كل التعديلات ({{ $auditLogs->count() }})'"></button>
    @endif
</section>
