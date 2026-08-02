@extends('layouts.dashboard')

@section('title', 'المشرفين')
@section('page-title', 'المشرفين')

@section('content')
<div x-data="supervisorCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">المشرفين والموظفين</h2>
            <p class="text-sm text-gray-500">{{ number_format($users->total()) }} مستخدم</p>
        </div>
        @can('supervisors.create')
            <button @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة مشرف
            </button>
        @endcan
    </div>

    <form method="GET" id="supervisors-filters" data-live-filters class="mb-4">
        <div class="relative max-w-md">
            <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو البريد أو الهاتف..." autocomplete="off"
                   class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
    </form>

    <div data-results>
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3">المشرف</th>
                        <th class="text-start font-medium px-4 py-3">الهاتف</th>
                        <th class="text-start font-medium px-4 py-3">الدور</th>
                        <th class="text-start font-medium px-4 py-3">الحالة</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($users as $u)
                        @php
                            $role = $u->roles->first();
                            $editData = [
                                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                                'phone' => $u->phone, 'civil_id' => $u->civil_id, 'job_title' => $u->job_title,
                                'status' => $u->status, 'is_agent' => (bool) $u->is_agent, 'role' => $role?->name,
                                // ملف الوكيل
                                'bio_ar' => $u->getTranslation('bio', 'ar', false),
                                'bio_en' => $u->getTranslation('bio', 'en', false),
                                'languages' => $u->languages ?? [],
                                'response_time' => $u->response_time,
                                'avatar_url' => $u->avatar_url,
                                'reviews' => $u->reviews->map(fn ($r) => [
                                    'id' => $r->id, 'reviewer_name' => $r->reviewer_name,
                                    'rating' => $r->rating, 'comment' => $r->comment,
                                    'is_published' => (bool) $r->is_published,
                                ])->values(),
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center w-9 h-9 shrink-0 rounded-full overflow-hidden bg-primary-900 text-white font-bold">
                                        @if ($u->avatar_url)
                                            <img src="{{ $u->avatar_url }}" class="w-full h-full object-cover" alt="{{ $u->name }}">
                                        @else
                                            {{ mb_substr($u->name, 0, 1) }}
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <span class="flex items-center gap-1.5 font-semibold text-ink truncate">{{ $u->name }}@if ($u->is_agent)<span class="text-[10px] bg-accent-100 text-accent-700 rounded px-1.5 py-0.5">وكيل</span>@endif</span>
                                        <span class="block text-xs text-gray-400 truncate"><span dir="ltr">{{ $u->email }}</span></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><span dir="ltr">{{ $u->phone ?: '—' }}</span></td>
                            <td class="px-4 py-3">
                                @if ($role)<span class="inline-block rounded-full bg-info-soft text-info px-2.5 py-1 text-xs font-medium">{{ $role->description ?: $role->name }}</span>@else <span class="text-gray-300">—</span> @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($u->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium"><span class="w-1.5 h-1.5 rounded-full bg-success"></span>نشط</span>
                                @else
                                    <span class="inline-block rounded-full bg-danger-soft text-danger px-2.5 py-1 text-xs font-medium">موقوف</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    @can('supervisors.edit')
                                        <button @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                                    @endcan
                                    @can('supervisors.delete')
                                        @if ($u->id !== auth()->id())
                                            <button @click="startDelete('{{ route('dashboard.supervisors.destroy', $u) }}', @js($u->name))" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-16 text-center text-gray-400">لا يوجد مشرفون.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    <x-modal name="supervisor-form">
        <form :action="action" method="POST" enctype="multipart/form-data">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل المشرف' : 'إضافة مشرف جديد'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'supervisor-form')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم الكامل <span class="text-danger">*</span></label>
                    <input name="name" x-model="form.name" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني <span class="text-danger">*</span></label>
                    <input name="email" type="email" x-model="form.email" required dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">كلمة المرور <span x-show="mode==='add'" class="text-danger">*</span></label>
                    <input name="password" type="password" x-model="form.password" :required="mode==='add'" :placeholder="mode==='edit' ? 'اتركه فارغاً لعدم التغيير' : ''" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">رقم الهاتف</label>
                    <input name="phone" x-model="form.phone" dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الرقم المدني</label>
                    <input name="civil_id" x-model="form.civil_id" dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-end focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">المسمى الوظيفي</label>
                    <input name="job_title" x-model="form.job_title" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الدور <span class="text-danger">*</span></label>
                    <select name="role" x-model="form.role" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="">— اختر —</option>
                        @foreach ($roles as $r)<option value="{{ $r->name }}">{{ $r->description ?: $r->name }}</option>@endforeach
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الحالة</label>
                    <select name="status" x-model="form.status" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="active">نشط</option>
                        <option value="suspended">موقوف</option>
                    </select></div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="is_agent" value="1" x-model="form.is_agent">
                        وكيل عقاري (يظهر في الموقع)
                    </label>
                </div>

                {{-- ===== ملف الوكيل العام — يظهر فقط عند تفعيل "وكيل عقاري" ===== --}}
                <template x-if="form.is_agent">
                    <div class="sm:col-span-2 space-y-4 pt-4 border-t border-gray-100">
                        <p class="text-xs font-bold text-gray-400">بيانات تُعرض في صفحة «من نحن» وصفحة الوكيل بالموقع</p>

                        {{-- الصورة الشخصية --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">الصورة الشخصية</label>
                            <div class="flex items-center gap-4">
                                <span class="grid place-items-center w-16 h-16 shrink-0 rounded-full overflow-hidden bg-primary-900 text-white font-bold text-xl">
                                    <template x-if="avatarPreview && ! removeAvatar">
                                        <img :src="avatarPreview" class="w-full h-full object-cover" alt="">
                                    </template>
                                    <span x-show="! avatarPreview || removeAvatar" x-text="form.name ? form.name.charAt(0) : '؟'"></span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" @change="pickAvatar($event)"
                                           class="w-full text-sm text-gray-500 file:me-3 file:rounded-full file:border-0 file:bg-primary-50 file:text-primary-700 file:px-4 file:py-2 file:text-sm file:font-bold file:cursor-pointer hover:file:bg-primary-100">
                                    <p class="text-[11px] text-gray-400 mt-1">JPG · PNG · WEBP — حتى ٦ ميجابايت، وتُصغَّر تلقائياً.</p>
                                    <label x-show="form.avatar_url" x-cloak class="inline-flex items-center gap-1.5 text-xs text-danger mt-1.5 cursor-pointer">
                                        <input type="checkbox" name="avatar_removed" value="1" x-model="removeAvatar">
                                        حذف الصورة الحالية
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- النبذة (مترجَمة) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">النبذة (عربي)</label>
                                <textarea name="bio_ar" x-model="form.bio_ar" rows="3" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm leading-relaxed focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></textarea></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">النبذة (English)</label>
                                <textarea name="bio_en" x-model="form.bio_en" rows="3" dir="ltr" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-start leading-relaxed focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></textarea></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">اللغات</label>
                                <div class="flex items-center gap-5 h-[42px]">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" name="languages[]" value="ar" x-model="form.languages"> العربية
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" name="languages[]" value="en" x-model="form.languages"> الإنجليزية
                                    </label>
                                </div>
                            </div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">زمن الاستجابة</label>
                                <input name="response_time" x-model="form.response_time" placeholder="خلال ساعة" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                        </div>

                        {{-- ===== التقييمات ===== --}}
                        <div class="pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">التقييمات</label>
                                    <p class="text-[11px] text-gray-400 mt-0.5">
                                        المتوسط: <span class="font-bold text-ink tabular-nums" x-text="avgRating || '—'"></span>
                                        <span class="text-accent-500" x-text="'★'.repeat(Math.round(avgRating))"></span>
                                        <span x-text="'(' + publishedCount + ' منشور)'"></span>
                                    </p>
                                </div>
                                <button type="button" @click="addReview()"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 hover:bg-primary-100 text-primary-800 font-bold px-3.5 py-2 text-xs transition">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    إضافة تقييم
                                </button>
                            </div>

                            {{-- معرّفات التقييمات المحذوفة --}}
                            <template x-for="id in removedReviews" :key="'rm' + id">
                                <input type="hidden" name="reviews_removed[]" :value="id">
                            </template>

                            <p x-show="! form.reviews.length" class="text-xs text-gray-400 py-3 text-center rounded-field bg-gray-50">لا توجد تقييمات بعد.</p>

                            <div class="space-y-3">
                                <template x-for="(r, i) in form.reviews" :key="i">
                                    <div class="rounded-field border border-gray-200 bg-gray-50/60 p-3 space-y-2.5">
                                        <input type="hidden" :name="`reviews[${i}][id]`" :value="r.id ?? ''">
                                        <div class="flex items-center gap-2">
                                            <input :name="`reviews[${i}][reviewer_name]`" x-model="r.reviewer_name" placeholder="اسم المُقيِّم" required
                                                   class="flex-1 rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                                            <select :name="`reviews[${i}][rating]`" x-model.number="r.rating"
                                                    class="rounded-field border border-gray-200 bg-white px-2.5 py-2 text-sm cursor-pointer focus:outline-none focus:border-primary-500">
                                                <template x-for="n in 5" :key="n">
                                                    <option :value="n" x-text="'★'.repeat(n)"></option>
                                                </template>
                                            </select>
                                            <button type="button" @click="removeReview(i)"
                                                    class="grid place-items-center w-8 h-8 shrink-0 rounded-full text-danger hover:bg-danger/10 transition" title="حذف التقييم">
                                                <x-icon.trash size="15" />
                                            </button>
                                        </div>
                                        <textarea :name="`reviews[${i}][comment]`" x-model="r.comment" rows="2" placeholder="التعليق (اختياري)"
                                                  class="w-full rounded-field border border-gray-200 bg-white px-3 py-2 text-sm leading-relaxed focus:outline-none focus:border-primary-500"></textarea>
                                        <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                            <input type="hidden" :name="`reviews[${i}][is_published]`" value="0">
                                            <input type="checkbox" :name="`reviews[${i}][is_published]`" value="1" x-model="r.is_published"
                                                  >
                                            منشور في الموقع
                                        </label>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'supervisor-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ' : 'إضافة'"></button>
            </div>
        </form>
    </x-modal>

    <x-modal name="supervisor-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف المشرف</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'supervisor-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>

<script>
    function supervisorCrud() {
        const blank = () => ({
            name: '', email: '', password: '', phone: '', civil_id: '', job_title: '',
            role: '', status: 'active', is_agent: false,
            bio_ar: '', bio_en: '', languages: [], response_time: '', avatar_url: '',
            reviews: [],
        });

        return {
            mode: 'add', action: '', delAction: '', delName: '',
            // معاينة الصورة: الملف المختار حديثاً، وإلا الصورة المحفوظة
            pickedAvatar: '', removeAvatar: false,
            removedReviews: [],
            form: blank(),

            // ===== التقييمات =====
            get publishedCount() {
                return this.form.reviews.filter(r => r.is_published).length;
            },
            get avgRating() {
                const pub = this.form.reviews.filter(r => r.is_published);
                if (! pub.length) return 0;
                return Math.round(pub.reduce((s, r) => s + Number(r.rating || 0), 0) / pub.length * 100) / 100;
            },
            addReview() {
                this.form.reviews.push({ id: null, reviewer_name: '', rating: 5, comment: '', is_published: true });
            },
            removeReview(i) {
                const r = this.form.reviews[i];
                if (r.id) this.removedReviews.push(r.id);
                this.form.reviews.splice(i, 1);
            },

            get avatarPreview() {
                return this.pickedAvatar || this.form.avatar_url || '';
            },
            pickAvatar(e) {
                const f = e.target.files?.[0];
                this.pickedAvatar = f ? URL.createObjectURL(f) : '';
                if (f) this.removeAvatar = false;
            },
            resetAvatar() {
                this.pickedAvatar = ''; this.removeAvatar = false; this.removedReviews = [];
            },

            startAdd() {
                this.mode = 'add';
                this.form = blank();
                this.resetAvatar();
                this.action = '{{ route('dashboard.supervisors.store') }}';
                this.$dispatch('open-modal', 'supervisor-form');
            },
            startEdit(u) {
                this.mode = 'edit';
                this.form = {
                    ...blank(), ...u, password: '', is_agent: !!u.is_agent,
                    languages: u.languages ?? [],
                    // نسخة مستقلة حتى لا يعدّل التحرير بيانات الصف في الجدول
                    reviews: (u.reviews ?? []).map(r => ({ ...r, is_published: !!r.is_published })),
                };
                this.resetAvatar();
                this.action = '{{ url('dashboard/supervisors') }}/' + u.id;
                this.$dispatch('open-modal', 'supervisor-form');
            },
            startDelete(action, name) {
                this.delAction = action; this.delName = name;
                this.$dispatch('open-modal', 'supervisor-delete');
            },
        };
    }
</script>
@endsection
