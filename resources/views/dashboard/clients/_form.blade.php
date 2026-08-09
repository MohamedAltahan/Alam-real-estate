@php
    $client = $client ?? null;
    $nationalities = [
        'كويتي', 'سعودي', 'مصري', 'أردني', 'فلسطيني', 'سوري', 'لبناني',
        'عراقي', 'يمني', 'إماراتي', 'بحريني', 'قطري', 'عُماني', 'سوداني',
        'تونسي', 'جزائري', 'مغربي', 'هندي', 'باكستاني', 'فلبيني', 'أخرى',
    ];
    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
@endphp

<div class="space-y-6">
    <section>
        <h4 class="font-bold text-ink mb-3">بيانات التواصل</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم <span class="text-danger">*</span></label>
                <input name="name" value="{{ old('name', $client?->name) }}" required class="{{ $field }}">
                @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">رقم الهاتف <span class="text-danger">*</span></label>
                <input name="phone" value="{{ old('phone', $client?->phone) }}" required dir="ltr" class="{{ $field }} text-end">
                @error('phone')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label>
                <input name="email" type="email" value="{{ old('email', $client?->email) }}" dir="ltr" class="{{ $field }} text-end">
                @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">طريقة التواصل المفضلة</label>
                <select name="preferred_contact" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    <option value="whatsapp" @selected(old('preferred_contact', $client?->preferred_contact) === 'whatsapp')>واتساب</option>
                    <option value="call" @selected(old('preferred_contact', $client?->preferred_contact) === 'call')>اتصال</option>
                </select>
            </div>
        </div>
    </section>

    <section class="pt-5 border-t border-gray-100">
        <h4 class="font-bold text-ink mb-3">احتياج العقار</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">نوع الوحدة المطلوبة</label>
                <select name="desired_unit_type_id" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($unitTypes as $unitType)
                        <option value="{{ $unitType->id }}" @selected(old('desired_unit_type_id', $client?->desired_unit_type_id) == $unitType->id)>{{ $unitType->name }}</option>
                    @endforeach
                </select>
                @error('desired_unit_type_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">المدينة / المنطقة المطلوبة</label>
                <select name="area_id" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" @selected(old('area_id', $client?->area_id) == $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">عنوان العقار بالتفصيل</label>
                <input name="property_address" value="{{ old('property_address', $client?->property_address) }}" placeholder="المنطقة، القطعة، الشارع أو أي تفاصيل مهمة" class="{{ $field }}">
                @error('property_address')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">أوقات الزيارة المناسبة</label>
                <input name="visit_times" value="{{ old('visit_times', $client?->visit_times) }}" placeholder="مثال: من الأحد إلى الخميس بعد 5 مساءً" class="{{ $field }}">
            </div>
        </div>
    </section>

    <section class="pt-5 border-t border-gray-100">
        <h4 class="font-bold text-ink mb-3">البيانات الشخصية</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">الجنسية</label>
                <input name="nationality" list="client-nationalities" value="{{ old('nationality', $client?->nationality) }}" placeholder="اكتب للبحث..." autocomplete="off" class="{{ $field }}">
                <datalist id="client-nationalities">
                    @foreach ($nationalities as $nationality)<option value="{{ $nationality }}"></option>@endforeach
                </datalist>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">الحالة الاجتماعية</label>
                <select name="social_status" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach (['single' => 'أعزب', 'married' => 'متزوج', 'family' => 'عائلة', 'company' => 'شركات'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('social_status', $client?->social_status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">عدد الأفراد</label>
                <input name="household_size" type="number" min="1" max="100" value="{{ old('household_size', $client?->household_size) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">حضوري</label>
                <select name="in_person" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    <option value="1" @selected((string) old('in_person', is_null($client?->in_person) ? '' : (int) $client->in_person) === '1')>نعم</option>
                    <option value="0" @selected((string) old('in_person', is_null($client?->in_person) ? '' : (int) $client->in_person) === '0')>لا</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">مكان العمل</label>
                <input name="workplace" value="{{ old('workplace', $client?->workplace) }}" class="{{ $field }}">
            </div>
        </div>
    </section>

    <section class="pt-5 border-t border-gray-100">
        <h4 class="font-bold text-ink mb-3">بيانات المتابعة</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">نوع العميل</label>
                <select name="type_id" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('type_id', $client?->type_id) == $type->id)>{{ $type->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">الحالة</label>
                <select name="stage_id" class="{{ $field }}">
                    <option value="">— طلب جديد —</option>
                    @foreach ($stages as $stage)<option value="{{ $stage->id }}" @selected(old('stage_id', $client?->stage_id) == $stage->id)>{{ $stage->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">مسؤول العقار</label>
                <select name="agent_id" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($agents as $user)<option value="{{ $user->id }}" @selected(old('agent_id', $client?->agent_id) == $user->id)>{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">مصدر التسويق</label>
                <select name="source_id" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($sources as $source)<option value="{{ $source->id }}" @selected(old('source_id', $client?->source_id) == $source->id)>{{ $source->name }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">ملاحظات</label>
                <textarea name="notes" rows="3" class="{{ $field }} resize-y">{{ old('notes', $client?->notes) }}</textarea>
            </div>
        </div>
    </section>
</div>
