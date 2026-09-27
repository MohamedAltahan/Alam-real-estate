{{--
| مفتاح إظهار/إخفاء قسم في الموقع — يُرسَل 1/0 مع فورم الصفحة.
| القسم المخفي يبقى محتواه محفوظاً ويعود كما هو عند إظهاره.
| القسم الذي لم يُحفظ بعد (null) يُعدّ ظاهراً.
--}}
@props(['name', 'visible' => true])

@php $on = (bool) ($visible ?? true); @endphp

<div x-data="{ on: @js($on) }" class="flex items-center gap-2.5 shrink-0">
    <span class="text-xs font-bold" :class="on ? 'text-success' : 'text-gray-400'"
          x-text="on ? 'ظاهر في الموقع' : 'مخفي من الموقع'">{{ $on ? 'ظاهر في الموقع' : 'مخفي من الموقع' }}</span>
    <input type="hidden" name="{{ $name }}" value="{{ $on ? 1 : 0 }}" :value="on ? 1 : 0">
    <button type="button" role="switch" :aria-checked="on.toString()" @click="on = ! on" aria-label="إظهار القسم في الموقع"
            class="relative w-11 h-6 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            :class="on ? 'bg-primary-800' : 'bg-gray-300'">
        <span class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all" :class="on ? 'start-6' : 'start-1'"></span>
    </button>
</div>
