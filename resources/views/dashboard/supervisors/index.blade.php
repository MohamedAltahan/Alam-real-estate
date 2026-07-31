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
            <button @click="startAdd()" class="inline-flex items-center gap-2 rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة مشرف
            </button>
        @endcan
    </div>

    <form method="GET" class="mb-4">
        <div class="relative max-w-md">
            <svg class="absolute inset-y-0 start-3 my-auto text-gray-400" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو البريد أو الهاتف..."
                   class="w-full rounded-field bg-white border border-gray-200 ps-10 pe-4 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
    </form>

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
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center w-9 h-9 rounded-full bg-primary-900 text-white font-bold">{{ mb_substr($u->name, 0, 1) }}</span>
                                    <div class="min-w-0">
                                        <span class="flex items-center gap-1.5 font-semibold text-ink truncate">{{ $u->name }}@if ($u->is_agent)<span class="text-[10px] bg-accent-100 text-accent-700 rounded px-1.5 py-0.5">وكيل</span>@endif</span>
                                        <span class="block text-xs text-gray-400 truncate" dir="ltr">{{ $u->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $u->phone ?: '—' }}</td>
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
                                        <button @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                                    @endcan
                                    @can('supervisors.delete')
                                        @if ($u->id !== auth()->id())
                                            <button @click="startDelete('{{ route('dashboard.supervisors.destroy', $u) }}', @js($u->name))" class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger hover:bg-danger/10" title="حذف"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button>
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

    <x-modal name="supervisor-form">
        <form :action="action" method="POST">
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
                        <input type="checkbox" name="is_agent" value="1" x-model="form.is_agent" class="rounded border-gray-300 text-primary-900 focus:ring-primary-500/30">
                        وكيل عقاري (يظهر في الموقع)
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'supervisor-form')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ' : 'إضافة'"></button>
            </div>
        </form>
    </x-modal>

    <x-modal name="supervisor-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></span>
            <h3 class="font-bold text-ink mb-1">حذف المشرف</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'supervisor-delete')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-field bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>

<script>
    function supervisorCrud() {
        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: { name: '', email: '', password: '', phone: '', civil_id: '', job_title: '', role: '', status: 'active', is_agent: false },
            startAdd() {
                this.mode = 'add';
                this.form = { name: '', email: '', password: '', phone: '', civil_id: '', job_title: '', role: '', status: 'active', is_agent: false };
                this.action = '{{ route('dashboard.supervisors.store') }}';
                this.$dispatch('open-modal', 'supervisor-form');
            },
            startEdit(u) {
                this.mode = 'edit';
                this.form = { name: u.name ?? '', email: u.email ?? '', password: '', phone: u.phone ?? '', civil_id: u.civil_id ?? '', job_title: u.job_title ?? '', role: u.role ?? '', status: u.status ?? 'active', is_agent: !!u.is_agent };
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
