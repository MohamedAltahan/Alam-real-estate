<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class SupervisorController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles', 'reviews', 'media'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.supervisors.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('supervisors.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            ...$this->sharedRules(),
        ], self::MESSAGES);

        $user = User::create([
            ...collect($data)->except('role', 'bio_ar', 'bio_en', 'languages', 'avatar')->all(),
            'is_agent' => $request->boolean('is_agent'),
            ...$this->agentProfile($request),
        ]);
        $user->assignRole($data['role']);
        $this->syncAvatar($request, $user);
        $this->syncReviews($request, $user);

        $this->activity->created($user, ['role' => ['old' => null, 'new' => $this->roleLabel($data['role'])]]);

        return back()->with('success', 'تم إضافة المشرف بنجاح.');
    }

    public function update(Request $request, User $supervisor): RedirectResponse
    {
        abort_unless($request->user()->can('supervisors.edit'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($supervisor->id)],
            'password' => ['nullable', 'string', 'min:8'],
            ...$this->sharedRules(),
        ], self::MESSAGES);

        $previousRole = $supervisor->roles->first()?->name;

        $supervisor->fill([
            ...collect($data)->except('role', 'password', 'bio_ar', 'bio_en', 'languages', 'avatar')->all(),
            'is_agent' => $request->boolean('is_agent'),
            ...$this->agentProfile($request),
            // الحقل قد يغيب كلياً عن الطلب (nullable) — لا نلمس كلمة المرور حينها
            ...(($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        // فرق الحقول قبل الحفظ — كلمة المرور تُسجَّل كحدث بلا قيمة، والدور من الجدول الوسيط
        $changes = $this->activity->changes($supervisor, $supervisor->getDirty());

        $supervisor->save();
        $supervisor->syncRoles([$data['role']]);
        $this->syncAvatar($request, $supervisor);
        $this->syncReviews($request, $supervisor);

        if ($previousRole !== $data['role']) {
            $changes['role'] = ['old' => $this->roleLabel($previousRole), 'new' => $this->roleLabel($data['role'])];
        }

        if ($data['password'] ?? null) {
            $changes['password'] = ['old' => null, 'new' => 'تم التغيير'];
        }

        $this->activity->updated($supervisor, [], $changes);

        return back()->with('success', 'تم تحديث بيانات المشرف.');
    }

    public function destroy(Request $request, User $supervisor): RedirectResponse
    {
        abort_unless($request->user()->can('supervisors.delete'), 403);
        abort_if($supervisor->id === $request->user()->id, 403, 'لا يمكنك حذف حسابك.');

        $this->activity->deleted($supervisor, ['role' => ['old' => $this->roleLabel($supervisor->roles->first()?->name), 'new' => null]]);
        $supervisor->delete();

        return back()->with('success', 'تم حذف المشرف.');
    }

    /** الاسم المعروض للدور (الوصف العربي وإلا الاسم البرمجي) */
    private function roleLabel(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $role = Role::where('name', $name)->where('guard_name', 'web')->first();

        return $role ? (string) ($role->description ?: $role->name) : $name;
    }

    /** قواعد مشتركة بين الإضافة والتعديل — تشمل حقول ملف الوكيل العام */
    private function sharedRules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:40'],
            'civil_id' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'exists:roles,name'],
            'status' => ['required', 'in:active,suspended'],

            // ملف الوكيل — يظهر في صفحة "من نحن" وصفحة الوكيل بالموقع
            'avatar' => User::imageRules(),
            'bio_ar' => ['nullable', 'string', 'max:2000'],
            'bio_en' => ['nullable', 'string', 'max:2000'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'in:ar,en'],
            'response_time' => ['nullable', 'string', 'max:60'],

            // تقييمات الوكيل — صفوف ديناميكية تُحفظ مع النموذج نفسه
            'reviews' => ['nullable', 'array'],
            'reviews.*.reviewer_name' => ['required', 'string', 'max:120'],
            'reviews.*.rating' => ['required', 'integer', 'between:1,5'],
            'reviews.*.comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * يزامن تقييمات الوكيل: يحذف المشطوبة، ويحدّث القائمة، ثم يعيد حساب المتوسط.
     * الصفوف الجديدة تأتي بمعرّف فارغ.
     */
    private function syncReviews(Request $request, User $user): void
    {
        if (! $request->has('reviews') && ! $request->has('reviews_removed')) {
            return;
        }

        $removed = array_filter((array) $request->input('reviews_removed', []));
        if ($removed) {
            $user->reviews()->whereIn('id', $removed)->delete();
        }

        foreach ((array) $request->input('reviews', []) as $row) {
            if (blank($row['reviewer_name'] ?? null)) {
                continue;
            }

            $payload = [
                'reviewer_name' => $row['reviewer_name'],
                'rating' => (int) $row['rating'],
                'comment' => $row['comment'] ?? null,
                'is_published' => (bool) ($row['is_published'] ?? true),
            ];

            if (! empty($row['id'])) {
                $user->reviews()->whereKey($row['id'])->update($payload);

                continue;
            }

            $user->reviews()->create($payload + ['created_by' => $request->user()->id]);
        }

        $user->refreshRating();
    }

    /** الحقول التي تحتاج تشكيلاً قبل الحفظ (bio مترجَم · languages مصفوفة) */
    private function agentProfile(Request $request): array
    {
        $bio = array_filter([
            'ar' => trim((string) $request->input('bio_ar')),
            'en' => trim((string) $request->input('bio_en')),
        ], fn ($v) => $v !== '');

        return [
            'bio' => $bio ?: null,
            'languages' => array_values((array) $request->input('languages', [])) ?: null,
        ];
    }

    /** رفع/حذف الصورة الشخصية — مجموعة avatar التي يقرأها الموقع عبر avatar_url */
    private function syncAvatar(Request $request, User $user): void
    {
        if ($request->boolean('avatar_removed')) {
            $user->clearMediaCollection('avatar');
        }

        if ($file = $request->file('avatar')) {
            $user->clearMediaCollection('avatar');
            $user->addMedia($file)->toMediaCollection('avatar');
        }
    }

    private const MESSAGES = [
        'avatar.max' => 'حجم الصورة يتجاوز الحد المسموح (٦ ميجابايت).',
        'avatar.mimetypes' => 'الصيغ المسموحة: JPG · PNG · WEBP.',
    ];
}
