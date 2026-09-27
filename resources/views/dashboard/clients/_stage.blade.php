{{--
    حالة الطلب بجوار «ملفات العميل» (client-stage.js):
    «ربح» ⇒ ديالوج باختيار العقار من المعاينات (نتيجته «ربح» والباقي «غير مهتم»)،
    «خسارة» ⇒ تأكيد تحويل كل العقارات إلى «غير مهتم»، وأي حالة أخرى تُحفظ مباشرة.
--}}
@php
    use App\Models\ClientStage;
    use App\Support\ClientFields;

    $stageList = $stages;
    if ($client->stage && ! $stageList->contains('id', $client->stage->id)) {
        $stageList = $stageList->prepend($client->stage);
    }

    // كل عقار مرة واحدة (قد يُعاين العقار أكثر من مرة) — مع آخر موعد ونتيجته
    $stageProperties = $client->viewings->filter(fn ($v) => $v->property)->groupBy('property_id')->map(function ($group) {
        $latest = $group->sortByDesc('scheduled_at')->first();

        return [
            'id' => (string) $latest->property->id,
            'reference' => (string) $latest->property->reference_code,
            'title' => (string) $latest->property->title,
            'cover' => $latest->property->cover_url,
            'meta' => collect([
                $group->count() > 1 ? $group->count().' معاينات' : null,
                $latest->scheduled_at ? 'آخر معاينة '.$latest->scheduled_at->format('Y-m-d') : null,
                ClientFields::outcomeLabel($latest->outcome),
            ])->filter()->implode(' · '),
        ];
    })->values();

    $stageState = [
        'current' => (string) ($client->stage_id ?? ''),
        'won' => ClientStage::KEY_WON,
        'lost' => ClientStage::KEY_LOST,
        'stages' => $stageList->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name, 'key' => $s->key, 'color' => $s->color ?: '#6B7280'])->values(),
        'properties' => $stageProperties,
    ];
    $dialogPanel = 'relative w-full max-w-lg bg-white rounded-card shadow-2xl max-h-[90vh] overflow-y-auto';
    $cancelBtn = 'rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100';
@endphp

<form method="POST" action="{{ route('dashboard.clients.stage', $client) }}" x-ref="form" x-data="clientStage(@js($stageState))" class="contents">
    @csrf @method('PATCH')
    <input type="hidden" name="won_property_id" :value="wonProperty">

    <span class="relative inline-flex items-center rounded-full border transition"
          :style="`color: ${color}; border-color: ${color}55; background-color: ${color}14`">
        <span class="ps-4 text-xs text-gray-500 whitespace-nowrap">حالة الطلب:</span>
        <select name="stage_id" x-model="selected" @change="change()" title="تغيير حالة الطلب"
                class="appearance-none bg-transparent border-0 ps-1.5 pe-8 py-2 text-sm font-semibold cursor-pointer text-inherit focus:outline-none">
            @if (! $client->stage_id)<option value="" disabled>— بدون حالة —</option>@endif
            @foreach ($stageList as $s)
                <option value="{{ $s->id }}" @selected((int) $client->stage_id === (int) $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
        <x-select-chevron class="end-3" />
    </span>

    {{-- ===== ربح: أي عقار أُغلقت عليه الصفقة؟ ===== --}}
    <div x-show="dialog === 'won'" x-cloak @keydown.escape.window="dialog === 'won' && cancel()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-primary-950/50" @click="cancel()"></div>
        <div class="{{ $dialogPanel }}">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink">تم الربح على أي عقار؟</h3>
                <p class="text-xs text-gray-400 mt-0.5">العقار المختار تصبح نتيجته «ربح»، وباقي العقارات «غير مهتم».</p>
            </div>
            <div class="p-6 space-y-2">
                <template x-for="p in properties" :key="p.id">
                    <label class="flex items-center gap-3 rounded-2xl border px-3 py-2.5 cursor-pointer transition"
                           :class="wonProperty === p.id ? 'border-success bg-success-soft/40' : 'border-gray-100 hover:bg-gray-50'">
                        <input type="radio" :value="p.id" x-model="wonProperty" class="accent-success shrink-0">
                        <span class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden shrink-0 grid place-items-center text-gray-300">
                            <template x-if="p.cover"><img :src="p.cover" class="w-full h-full object-cover" alt=""></template>
                            <template x-if="! p.cover"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg></template>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-ink truncate"><bdi dir="ltr" x-text="p.reference"></bdi> — <span x-text="p.title"></span></span>
                            <span class="block text-xs text-gray-400 truncate" x-text="p.meta"></span>
                        </span>
                    </label>
                </template>
            </div>
            <div class="sticky bottom-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/95">
                <button type="button" @click="cancel()" class="{{ $cancelBtn }}">إلغاء</button>
                <button type="button" @click="submit()" :disabled="! wonProperty"
                        class="rounded-full bg-success hover:bg-success/90 text-white font-semibold px-5 py-2.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed">تأكيد الربح</button>
            </div>
        </div>
    </div>

    {{-- ===== خسارة: تأكيد تحويل كل العقارات إلى «غير مهتم» ===== --}}
    <div x-show="dialog === 'lost'" x-cloak @keydown.escape.window="dialog === 'lost' && cancel()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-primary-950/50" @click="cancel()"></div>
        <div class="{{ $dialogPanel }} p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
            </span>
            <h3 class="font-bold text-ink mb-1">تأكيد الخسارة</h3>
            <p class="text-sm text-gray-500 mb-6">ستتحول نتيجة كل العقارات المعاينة (<span class="font-semibold text-ink tabular-nums" x-text="properties.length"></span>) إلى «غير مهتم».</p>
            <div class="flex items-center justify-center gap-3">
                <button type="button" @click="cancel()" class="{{ $cancelBtn }}">إلغاء</button>
                <button type="button" @click="submit()" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">تأكيد الخسارة</button>
            </div>
        </div>
    </div>
</form>
