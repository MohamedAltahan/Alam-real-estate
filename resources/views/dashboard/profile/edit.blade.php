@extends('layouts.dashboard')

@section('title', 'إعدادات الملف الشخصي')
@section('page-title', 'الملف الشخصي')

@php
    $tabs = [
        'profile' => 'الملف الشخصي',
        'security' => 'الأمان',
        'notifications' => 'الإشعارات',
        'preferences' => 'التفضيلات',
    ];
    $inputClass = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white transition';
    $roleName = $user->roles->first()?->name;
    $roleLabel = match ($roleName) {
        'super-admin' => 'مدير النظام',
        'admin' => 'مشرف',
        'agent' => 'مندوب مبيعات',
        default => $roleName ?: 'مستخدم',
    };
    $dateFormat = data_get($displayPreferences, 'date_format', 'd/m/Y');
    $initial = mb_substr($user->name, 0, 1);
@endphp

@section('content')
<div class="max-w-6xl mx-auto">
    <x-flash />

    <div class="mb-5">
        <h2 class="text-2xl font-bold text-ink">إعدادات الملف الشخصي</h2>
        <p class="text-sm text-gray-400 mt-1">إدارة معلومات حسابك وإعدادات الأمان والإشعارات والتفضيلات.</p>
    </div>

    <nav class="inline-flex max-w-full overflow-x-auto rounded-full bg-white border border-gray-100 p-1 mb-5" aria-label="تبويبات الإعدادات">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('dashboard.profile.edit', ['tab' => $key]) }}"
               class="shrink-0 rounded-full px-5 py-2 text-sm transition {{ $tab === $key ? 'bg-primary-900 text-white font-bold shadow-sm' : 'text-gray-500 hover:text-ink' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">
        <div class="lg:col-span-3">
            @if ($tab === 'profile')
                <form method="POST" action="{{ route('dashboard.profile.update') }}" class="rounded-card bg-white border border-gray-100 shadow-sm p-5 sm:p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="profile-name" class="block text-xs font-bold text-gray-600 mb-1.5">الاسم الكامل</label>
                            <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" class="{{ $inputClass }}">
                            @error('name')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-email" class="block text-xs font-bold text-gray-600 mb-1.5">البريد الإلكتروني</label>
                            <input id="profile-email" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" dir="ltr" class="{{ $inputClass }} text-start">
                            @error('email')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-phone" class="block text-xs font-bold text-gray-600 mb-1.5">رقم الهاتف</label>
                            <input id="profile-phone" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" dir="ltr" class="{{ $inputClass }} text-start">
                            @error('phone')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-job" class="block text-xs font-bold text-gray-600 mb-1.5">المسمى الوظيفي</label>
                            <input id="profile-job" name="job_title" value="{{ old('job_title', $user->job_title) }}" class="{{ $inputClass }}">
                            @error('job_title')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="profile-bio" class="block text-xs font-bold text-gray-600 mb-1.5">السيرة الذاتية</label>
                            <textarea id="profile-bio" name="bio" rows="4" class="{{ $inputClass }} resize-y">{{ old('bio', $user->getTranslation('bio', 'ar', false)) }}</textarea>
                            @error('bio')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-5">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-6 py-2.5 text-sm transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                            حفظ التغييرات
                        </button>
                    </div>
                </form>
            @elseif ($tab === 'security')
                <form method="POST" action="{{ route('dashboard.profile.password') }}" x-data="{ current: false, password: false, confirm: false }" class="rounded-card bg-white border border-gray-100 shadow-sm p-5 sm:p-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        @foreach ([
                            ['name' => 'current_password', 'label' => 'كلمة المرور الحالية', 'model' => 'current', 'autocomplete' => 'current-password'],
                            ['name' => 'password', 'label' => 'كلمة المرور الجديدة', 'model' => 'password', 'autocomplete' => 'new-password'],
                            ['name' => 'password_confirmation', 'label' => 'تأكيد كلمة المرور الجديدة', 'model' => 'confirm', 'autocomplete' => 'new-password'],
                        ] as $field)
                            <div>
                                <label for="{{ $field['name'] }}" class="block text-xs font-bold text-gray-600 mb-1.5">{{ $field['label'] }}</label>
                                <div class="relative">
                                    <input id="{{ $field['name'] }}" name="{{ $field['name'] }}" :type="{{ $field['model'] }} ? 'text' : 'password'" autocomplete="{{ $field['autocomplete'] }}" class="{{ $inputClass }} pe-11">
                                    <button type="button" @click="{{ $field['model'] }} = ! {{ $field['model'] }}" class="absolute inset-y-0 end-0 grid place-items-center w-11 text-gray-400 hover:text-ink" aria-label="إظهار أو إخفاء كلمة المرور">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                @error($field['name'])<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end mt-5">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-6 py-2.5 text-sm transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3"/></svg>
                            تحديث كلمة المرور
                        </button>
                    </div>
                </form>
            @elseif ($tab === 'notifications')
                <form method="POST" action="{{ route('dashboard.profile.notifications') }}" class="rounded-card bg-white border border-gray-100 shadow-sm p-4 sm:p-5 space-y-3">
                    @csrf
                    @method('PUT')

                    @foreach ([
                        'contact_requests' => ['طلبات التواصل الجديدة', 'استلام إشعار عند وصول طلب تواصل جديد.'],
                        'new_clients' => ['العملاء الجدد', 'إظهار تنبيه عند إضافة عميل جديد إلى النظام.'],
                        'closed_deals' => ['الصفقات المغلقة', 'إعلامك عند إغلاق صفقة عقارية بنجاح.'],
                        'follow_up_reminders' => ['تنبيهات المتابعة', 'إظهار ملخص بالعملاء الذين يحتاجون إلى متابعة.'],
                    ] as $key => [$title, $description])
                        <div x-data="{ enabled: @js((bool) $notificationPreferences[$key]) }" class="flex items-center gap-4 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3.5">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-ink">{{ $title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $description }}</p>
                            </div>
                            <input type="hidden" name="{{ $key }}" value="{{ $notificationPreferences[$key] ? 1 : 0 }}" :value="enabled ? 1 : 0">
                            <button type="button" role="switch" :aria-checked="enabled" @click="enabled = ! enabled"
                                    class="relative w-11 h-6 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                    :class="enabled ? 'bg-primary-800' : 'bg-gray-300'">
                                <span class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                      :class="enabled ? 'start-6' : 'start-1'"></span>
                            </button>
                        </div>
                    @endforeach

                    {{-- تذكيرات المعاينات --}}
                    <div x-data="{ enabled: @js((bool) $viewingReminders['enabled']), repeat: @js((bool) $viewingReminders['repeat_beep']) }"
                         class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-4 space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-ink">تذكير مواعيد المعاينات</p>
                                <p class="text-xs text-gray-400 mt-0.5">إشعار بصوت «بيب بيب» قبل موعد المعاينة بالمدة المحددة. يصل التذكير لمندوب المبيعات الخاص بالعميل ويُفحص كل دقيقة أثناء فتح لوحة التحكم.</p>
                            </div>
                            <input type="hidden" name="viewing_enabled" :value="enabled ? 1 : 0">
                            <button type="button" role="switch" :aria-checked="enabled" @click="enabled = ! enabled"
                                    class="relative w-11 h-6 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                    :class="enabled ? 'bg-primary-800' : 'bg-gray-300'">
                                <span class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all" :class="enabled ? 'start-6' : 'start-1'"></span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" :class="! enabled && 'opacity-50'">
                            <div>
                                <label for="viewing-lead" class="block text-xs font-bold text-gray-600 mb-1.5">التذكير قبل الموعد بـ (دقائق)</label>
                                <div class="flex items-center gap-2">
                                    <input id="viewing-lead" type="number" name="viewing_lead_minutes" min="5" max="1440" step="5"
                                           value="{{ old('viewing_lead_minutes', $viewingReminders['lead_minutes']) }}"
                                           class="w-32 rounded-field border border-gray-200 bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15" dir="ltr">
                                    <span class="text-xs text-gray-400">من 5 دقائق إلى يوم كامل (1440)</span>
                                </div>
                                @error('viewing_lead_minutes')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white px-4 py-3">
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-ink">تكرار الصوت حتى فتح الإشعار</p>
                                    <p class="text-xs text-gray-400 mt-0.5">يعيد «بيب بيب» كل دقيقة ما دام تذكير المعاينة لم يُفتح.</p>
                                </div>
                                <input type="hidden" name="viewing_repeat_beep" :value="repeat ? 1 : 0">
                                <button type="button" role="switch" :aria-checked="repeat" @click="repeat = ! repeat"
                                        class="relative w-11 h-6 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                        :class="repeat ? 'bg-primary-800' : 'bg-gray-300'">
                                    <span class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all" :class="repeat ? 'start-6' : 'start-1'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-6 py-2.5 text-sm transition">حفظ إعدادات الإشعارات</button>
                    </div>
                </form>
            @else
                <form method="POST" action="{{ route('dashboard.profile.preferences') }}" class="rounded-card bg-white border border-gray-100 shadow-sm p-5 sm:p-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label for="language" class="block text-xs font-bold text-gray-600 mb-1.5">اللغة</label>
                            <select id="language" name="language" class="{{ $inputClass }}">
                                <option value="ar" @selected(old('language', $displayPreferences['language']) === 'ar')>العربية</option>
                                <option value="en" @selected(old('language', $displayPreferences['language']) === 'en')>English</option>
                            </select>
                        </div>
                        <div>
                            <label for="date-format" class="block text-xs font-bold text-gray-600 mb-1.5">تنسيق التاريخ</label>
                            <select id="date-format" name="date_format" class="{{ $inputClass }}">
                                <option value="d/m/Y" @selected(old('date_format', $displayPreferences['date_format']) === 'd/m/Y')>يوم/شهر/سنة — 09/08/2026</option>
                                <option value="Y-m-d" @selected(old('date_format', $displayPreferences['date_format']) === 'Y-m-d')>سنة-شهر-يوم — 2026-08-09</option>
                                <option value="d M Y" @selected(old('date_format', $displayPreferences['date_format']) === 'd M Y')>يوم شهر سنة — 09 Aug 2026</option>
                            </select>
                        </div>
                        <div>
                            <label for="currency" class="block text-xs font-bold text-gray-600 mb-1.5">عملة العرض</label>
                            <select id="currency" name="currency" class="{{ $inputClass }}">
                                <option value="KWD" @selected(old('currency', $displayPreferences['currency']) === 'KWD')>دينار كويتي (KWD)</option>
                                <option value="SAR" @selected(old('currency', $displayPreferences['currency']) === 'SAR')>ريال سعودي (SAR)</option>
                                <option value="USD" @selected(old('currency', $displayPreferences['currency']) === 'USD')>دولار أمريكي (USD)</option>
                            </select>
                        </div>

                        {{-- إعداد عام للموقع (ليس شخصياً) — يظهر فقط لمن يملك صلاحية تعديل الموقع --}}
                        @if ($canEditSite)
                            <div x-data="{ enabled: @js((bool) $siteBusyBadge) }" class="flex items-center gap-4 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3.5">
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-ink">شارة «مشغول / مباع» على الموقع</p>
                                    <p class="text-xs text-gray-400 mt-0.5">إعداد عام لكل الزوّار: شارة حمراء أعلى كارت العقار وفي صفحته على الموقع للعقار المباع أو الذي اختاره عميل.</p>
                                </div>
                                <input type="hidden" name="site_busy_badge" value="{{ $siteBusyBadge ? 1 : 0 }}" :value="enabled ? 1 : 0">
                                <button type="button" role="switch" :aria-checked="enabled" @click="enabled = ! enabled"
                                        class="relative w-11 h-6 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                        :class="enabled ? 'bg-primary-800' : 'bg-gray-300'">
                                    <span class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all" :class="enabled ? 'start-6' : 'start-1'"></span>
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end mt-5">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-6 py-2.5 text-sm transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                            حفظ التفضيلات
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="lg:col-span-2 rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="navy-gradient text-white px-5 py-3">
                <h3 class="text-sm font-bold">الإعدادات العامة</h3>
            </div>

            <div class="p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-14 h-14 shrink-0 rounded-full object-cover">
                        @else
                            <span class="grid place-items-center w-14 h-14 shrink-0 rounded-full gold-gradient text-white text-xl font-bold">{{ $initial }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="font-bold text-ink truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $user->job_title ?: $roleLabel }}</p>
                            <p class="text-xs text-gray-400 truncate"><bdi dir="ltr">{{ $user->email }}</bdi></p>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('dashboard.profile.avatar') }}" enctype="multipart/form-data" class="mt-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="inline-flex items-center gap-2 rounded-full bg-primary-50 hover:bg-primary-100 text-primary-800 px-4 py-2 text-xs font-bold cursor-pointer transition">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                        تغيير الصورة
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.requestSubmit()">
                    </label>
                    @error('avatar')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                </form>

                <div class="grid grid-cols-3 gap-3 border-t border-gray-100 mt-5 pt-4 text-center">
                    <div>
                        <p class="text-[10px] text-gray-400">تاريخ الانضمام</p>
                        <p class="text-xs font-bold text-ink mt-1"><bdi dir="ltr">{{ $user->created_at?->format($dateFormat) }}</bdi></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400">آخر تحديث</p>
                        <p class="text-xs font-bold text-ink mt-1">{{ $user->updated_at?->locale('ar')->diffForHumans() }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400">الدور</p>
                        <p class="text-xs font-bold text-ink mt-1">{{ $roleLabel }}</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
