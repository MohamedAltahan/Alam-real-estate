@php
    use App\Support\ClientFields;
    use App\Support\ClientFormData;

    $client = $client ?? null;
    $form = $form ?? ClientFormData::for($client);
    $rowErrors = ClientFormData::errorMap($errors);
    $nationalities = [
        'كويتي', 'سعودي', 'مصري', 'أردني', 'فلسطيني', 'سوري', 'لبناني',
        'عراقي', 'يمني', 'إماراتي', 'بحريني', 'قطري', 'عُماني', 'سوداني',
        'تونسي', 'جزائري', 'مغربي', 'هندي', 'باكستاني', 'فلبيني', 'أخرى',
    ];
    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $label = 'block text-sm font-medium text-gray-700 mb-1.5';
@endphp

<div class="space-y-6">
    {{-- ===== بيانات التواصل ===== --}}
    <section>
        <h4 class="font-bold text-ink mb-3">بيانات التواصل</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="{{ $label }}">الاسم <span class="text-danger">*</span></label>
                <input name="name" value="{{ old('name', $client?->name) }}" required class="{{ $field }}">
                @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $label }}">رقم الهاتف <span class="text-danger">*</span></label>
                <x-phone-field :countries="$form['countries']" :code="old('phone_code', $client?->phone_code ?: '+965')" :national="old('phone', $client?->phone)" />
            </div>
            <div>
                <label class="{{ $label }}">البريد الإلكتروني</label>
                <input name="email" type="email" value="{{ old('email', $client?->email) }}" dir="ltr" class="{{ $field }} text-end">
                @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $label }}">طريقة التواصل</label>
                <select name="preferred_contact" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach (ClientFields::CONTACT_METHODS as $value => $text)
                        <option value="{{ $value }}" @selected(old('preferred_contact', $client?->preferred_contact) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    {{-- ===== احتياج العقار (أكثر من سطر) ===== --}}
    <section class="pt-5 border-t border-gray-100">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h4 class="font-bold text-ink">احتياج العقار</h4>
            <p class="text-xs text-gray-400">العميل قد يبحث عن أكثر من نوع أو منطقة — أضف سطرًا لكل احتياج.</p>
        </div>
        @include('dashboard.clients._needs', ['form' => $form, 'rowErrors' => $rowErrors, 'field' => $field, 'label' => $label])
    </section>

    {{-- ===== البيانات الشخصية ===== --}}
    <section class="pt-5 border-t border-gray-100">
        <h4 class="font-bold text-ink mb-3">البيانات الشخصية</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="{{ $label }}">الجنسية</label>
                <input name="nationality" list="client-nationalities" value="{{ old('nationality', $client?->nationality) }}" placeholder="اكتب للبحث..." autocomplete="off" class="{{ $field }}">
                <datalist id="client-nationalities">
                    @foreach ($nationalities as $nationality)<option value="{{ $nationality }}"></option>@endforeach
                </datalist>
            </div>
            <div>
                <label class="{{ $label }}">الحالة الاجتماعية</label>
                <select name="social_status" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach (ClientFields::SOCIAL_STATUSES as $value => $text)
                        <option value="{{ $value }}" @selected(old('social_status', $client?->social_status) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">عدد الأفراد</label>
                <input name="household_size" type="number" min="1" max="100" value="{{ old('household_size', $client?->household_size) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">مكان العمل</label>
                <input name="workplace" value="{{ old('workplace', $client?->workplace) }}" class="{{ $field }}">
            </div>
        </div>
    </section>

    {{-- ===== بيانات المتابعة ===== --}}
    <section class="pt-5 border-t border-gray-100">
        <h4 class="font-bold text-ink mb-3">بيانات المتابعة</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start mb-4">
            <div>
                <label class="{{ $label }}">الحالة</label>
                <select name="stage_id" class="{{ $field }}">
                    <option value="">— طلب جديد —</option>
                    @foreach ($stages as $stage)<option value="{{ $stage->id }}" @selected(old('stage_id', $client?->stage_id) == $stage->id)>{{ $stage->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">مسؤول العقار</label>
                <select name="agent_id" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($agents as $user)<option value="{{ $user->id }}" @selected(old('agent_id', $client?->agent_id) == $user->id)>{{ $user->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 mb-3">
            <h5 class="font-semibold text-sm text-ink">المعاينات</h5>
            <p class="text-xs text-gray-400">اختر العقار وموعد المعاينة — يصل تذكير لمسؤول العقار قبل الموعد.</p>
        </div>
        @include('dashboard.clients._viewings', ['form' => $form, 'rowErrors' => $rowErrors, 'field' => $field, 'label' => $label])

        <div class="mt-4">
            <label class="{{ $label }}">ملاحظات</label>
            <textarea name="notes" rows="3" class="{{ $field }} resize-y">{{ old('notes', $client?->notes) }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
    </section>
</div>
