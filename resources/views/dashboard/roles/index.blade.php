@extends('layouts.dashboard')

@section('title', 'إدارة الأدوار')
@section('page-title', 'إدارة الأدوار')

@section('content')
<div x-data="roleCrud()">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">الأدوار</h2>
            <p class="text-sm text-gray-500">تحكّم في مجموعات الصلاحيات لكل فريق</p>
        </div>
        @can('roles.create')
            <button @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة دور
            </button>
        @endcan
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($roles as $r)
            @php $editData = $r->only(['id', 'description', 'status']); @endphp
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-5">
                <div class="flex items-start justify-between">
                    <span class="grid place-items-center w-11 h-11 rounded-field bg-primary-50 text-primary-700">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </span>
                    @if ($r->status === 'active')
                        <span class="rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-medium">نشط</span>
                    @else
                        <span class="rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-medium">غير نشط</span>
                    @endif
                </div>
                <h3 class="mt-4 font-bold text-ink">{{ $r->description ?: $r->name }}</h3>
                <p class="text-xs text-gray-400"><span dir="ltr">{{ $r->name }}</span></p>
                <div class="flex items-center gap-4 mt-4 pt-4 border-t border-gray-100 text-sm">
                    <span class="text-gray-600"><span class="font-bold text-ink tabular-nums">{{ $r->permissions_count }}</span> صلاحية</span>
                    <span class="text-gray-600"><span class="font-bold text-ink tabular-nums">{{ $r->users_count }}</span> مستخدم</span>
                    <div class="ms-auto flex items-center gap-1">
                        @can('roles.edit')
                            <button @click='startEdit(@json($editData))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                        @endcan
                        @can('roles.delete')
                            @if ($r->name !== 'super-admin')
                                <button @click="startDelete('{{ route('dashboard.roles.destroy', $r) }}', @js($r->description ?: $r->name))" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-modal name="role-form" maxWidth="md">
        <form :action="action" method="POST">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل الدور' : 'إضافة دور جديد'"></h3>
                <button type="button" @click="$dispatch('close-modal', 'role-form')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">اسم الدور <span class="text-danger">*</span></label>
                    <input name="description" x-model="form.description" required placeholder="مثال: مدير المبيعات" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الحالة</label>
                    <select name="status" x-model="form.status" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                    </select></div>
                <p class="text-xs text-gray-400">لتحديد صلاحيات هذا الدور، افتح شاشة «الصلاحيات».</p>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'role-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ' : 'إضافة'"></button>
            </div>
        </form>
    </x-modal>

    <x-modal name="role-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف الدور</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف دور "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'role-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>

<script>
    function roleCrud() {
        return {
            mode: 'add', action: '', delAction: '', delName: '',
            form: { description: '', status: 'active' },
            startAdd() {
                this.mode = 'add';
                this.form = { description: '', status: 'active' };
                this.action = '{{ route('dashboard.roles.store') }}';
                this.$dispatch('open-modal', 'role-form');
            },
            startEdit(r) {
                this.mode = 'edit';
                this.form = { description: r.description ?? '', status: r.status ?? 'active' };
                this.action = '{{ url('dashboard/roles') }}/' + r.id;
                this.$dispatch('open-modal', 'role-form');
            },
            startDelete(action, name) {
                this.delAction = action; this.delName = name;
                this.$dispatch('open-modal', 'role-delete');
            },
        };
    }
</script>
@endsection
