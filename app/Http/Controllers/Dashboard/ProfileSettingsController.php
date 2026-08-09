<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileSettingsController extends Controller
{
    private const TABS = ['profile', 'security', 'notifications', 'preferences'];

    private const NOTIFICATION_DEFAULTS = [
        'contact_requests' => true,
        'new_clients' => true,
        'closed_deals' => true,
        'follow_up_reminders' => false,
    ];

    public function edit(Request $request): View
    {
        $tab = in_array($request->string('tab')->toString(), self::TABS, true)
            ? $request->string('tab')->toString()
            : 'profile';

        $user = $request->user()->load(['roles', 'media']);

        return view('dashboard.profile.edit', [
            'user' => $user,
            'tab' => $tab,
            'notificationPreferences' => array_replace(
                self::NOTIFICATION_DEFAULTS,
                (array) data_get($user->preferences, 'notifications', []),
            ),
            'displayPreferences' => array_replace([
                'language' => 'ar',
                'date_format' => 'd/m/Y',
                'currency' => 'KWD',
            ], (array) data_get($user->preferences, 'display', [])),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ], self::MESSAGES);

        $user->fill(collect($data)->except('bio')->all());
        $user->setTranslation('bio', 'ar', trim((string) ($data['bio'] ?? '')));
        $user->save();

        return $this->redirectTo('profile', 'تم حفظ بيانات الملف الشخصي بنجاح.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => User::imageRules(required: true),
        ], self::MESSAGES);

        $user = $request->user();
        $user->clearMediaCollection('avatar');
        $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return $this->redirectTo($this->requestedTab($request), 'تم تحديث الصورة الشخصية.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], self::MESSAGES);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return $this->redirectTo('security', 'تم تحديث كلمة المرور بنجاح.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_requests' => ['required', 'boolean'],
            'new_clients' => ['required', 'boolean'],
            'closed_deals' => ['required', 'boolean'],
            'follow_up_reminders' => ['required', 'boolean'],
        ]);

        $preferences = $request->user()->preferences ?? [];
        data_set($preferences, 'notifications', collect($data)->map(fn ($value) => (bool) $value)->all());

        $request->user()->update(['preferences' => $preferences]);

        return $this->redirectTo('notifications', 'تم حفظ إعدادات الإشعارات.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(['ar', 'en'])],
            'date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'd M Y'])],
            'currency' => ['required', Rule::in(['KWD', 'SAR', 'USD'])],
        ], self::MESSAGES);

        $preferences = $request->user()->preferences ?? [];
        data_set($preferences, 'display', $data);
        $request->user()->update(['preferences' => $preferences]);
        $request->session()->put('locale', $data['language']);

        return $this->redirectTo('preferences', 'تم حفظ تفضيلات العرض.');
    }

    private function requestedTab(Request $request): string
    {
        $tab = $request->string('tab')->toString();

        return in_array($tab, self::TABS, true) ? $tab : 'profile';
    }

    private function redirectTo(string $tab, string $message): RedirectResponse
    {
        return redirect()->route('dashboard.profile.edit', ['tab' => $tab])->with('success', $message);
    }

    private const MESSAGES = [
        'name.required' => 'الاسم الكامل مطلوب.',
        'email.required' => 'البريد الإلكتروني مطلوب.',
        'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
        'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
        'phone.max' => 'رقم الهاتف أطول من الحد المسموح.',
        'job_title.max' => 'المسمى الوظيفي أطول من الحد المسموح.',
        'bio.max' => 'السيرة الذاتية يجب ألا تتجاوز 2000 حرف.',
        'avatar.required' => 'اختر صورة شخصية أولاً.',
        'avatar.max' => 'حجم الصورة يتجاوز الحد المسموح (6 ميجابايت).',
        'avatar.mimetypes' => 'الصيغ المسموحة: JPG وPNG وWEBP.',
        'current_password.required' => 'كلمة المرور الحالية مطلوبة.',
        'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
        'password.required' => 'كلمة المرور الجديدة مطلوبة.',
        'password.min' => 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.',
        'password.confirmed' => 'تأكيد كلمة المرور الجديدة غير متطابق.',
        'language.in' => 'اللغة المختارة غير مدعومة.',
        'date_format.in' => 'تنسيق التاريخ المختار غير مدعوم.',
        'currency.in' => 'العملة المختارة غير مدعومة.',
    ];
}
