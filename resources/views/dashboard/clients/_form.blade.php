@php $client = $client ?? null; @endphp

{{-- عشرة حقول في عمودين = خمسة أسطر، كل سطر فيه حقلان --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم <span class="text-danger">*</span></label>
        <input name="name" value="{{ old('name', $client?->name) }}" required
               class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">رقم الهاتف <span class="text-danger">*</span></label>
        <input name="phone" value="{{ old('phone', $client?->phone) }}" required dir="ltr"
               class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
        @error('phone')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label>
        <input name="email" type="email" value="{{ old('email', $client?->email) }}" dir="ltr"
               class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
        @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">المدينة / المنطقة</label>
        <select name="area_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
            <option value="">— اختر —</option>
            @foreach ($areas as $a)
                <option value="{{ $a->id }}" @selected(old('area_id', $client?->area_id) == $a->id)>{{ $a->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">نوع العميل</label>
        <select name="type_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
            <option value="">— اختر —</option>
            @foreach ($types as $t)
                <option value="{{ $t->id }}" @selected(old('type_id', $client?->type_id) == $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">المرحلة</label>
        <select name="stage_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
            <option value="">— اختر —</option>
            @foreach ($stages as $s)
                <option value="{{ $s->id }}" @selected(old('stage_id', $client?->stage_id) == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">الوكيل المسؤول</label>
        <select name="agent_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
            <option value="">— اختر —</option>
            @foreach ($agents as $u)
                <option value="{{ $u->id }}" @selected(old('agent_id', $client?->agent_id) == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">مصدر التسويق</label>
        <select name="source_id" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
            <option value="">— اختر —</option>
            @foreach ($sources as $src)
                <option value="{{ $src->id }}" @selected(old('source_id', $client?->source_id) == $src->id)>{{ $src->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">التقييم (%)</label>
        <input name="rating" type="number" min="0" max="100" value="{{ old('rating', $client?->rating) }}"
               class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
        @error('rating')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">ملاحظات</label>
        <textarea name="notes" rows="1" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm resize-y focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">{{ old('notes', $client?->notes) }}</textarea>
    </div>
</div>
