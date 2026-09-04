{{--
    مودال إضافة/تعديل المالك — يُستخدم في القائمة وصفحة المالك.
    يعتمد على x-data="ownerForm(...)" في الصفحة الأم (resources/js/dashboard/owner-form.js).
--}}
@php
    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $label = 'block text-sm font-medium text-gray-700 mb-1.5';
@endphp

@canany(['property_owners.create', 'property_owners.edit'])
<x-modal name="owner-form" maxWidth="3xl">
    <form :action="action" method="POST" enctype="multipart/form-data">
        @csrf
        <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
        <input type="hidden" name="owner_id" :value="ownerId || ''">

        <div class="sticky top-0 z-10 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل المالك' : 'إضافة مالك جديد'"></h3>
            <button type="button" @click="$dispatch('close-modal', 'owner-form')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>

        <div class="p-6 space-y-6">
            {{-- ===== البيانات الأساسية ===== --}}
            <section>
                <h4 class="font-bold text-sm text-ink mb-3">بيانات المالك</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">الاسم الكامل <span class="text-danger">*</span></label>
                        <input name="name" x-model="form.name" required class="{{ $field }}">
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">البريد الإلكتروني</label>
                        <input name="email" type="email" x-model="form.email" dir="ltr" class="{{ $field }} text-end">
                        @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">المنطقة</label>
                        <select name="area_id" x-model="form.area_id" class="{{ $field }}">
                            <option value="">— اختر —</option>
                            @foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                        </select>
                        @error('area_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">العنوان المسجّل</label>
                        <input name="registered_address" x-model="form.registered_address" class="{{ $field }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">الملاحظات</label>
                        <textarea name="notes" x-model="form.notes" rows="3" class="{{ $field }}"></textarea>
                        @error('notes')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            {{-- ===== أرقام التواصل (أكثر من سطر) ===== --}}
            <section>
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h4 class="font-bold text-sm text-ink">أرقام التواصل <span class="text-danger">*</span></h4>
                    <p class="text-xs text-gray-400">رقم الهاتف · صفة صاحب الرقم · اسمه</p>
                </div>
                @error('contacts')<p class="mb-2 text-xs text-danger">{{ $message }}</p>@enderror
                <div class="space-y-3">
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_auto] gap-3 items-start rounded-2xl border p-3"
                             :class="hasErrors(i) ? 'border-danger/40 bg-danger/5' : 'border-gray-100 bg-gray-50/60'">
                            <input type="hidden" :name="name(i, 'id')" :value="row.id">
                            <div>
                                <label class="{{ $label }}">رقم الهاتف <span class="text-danger">*</span></label>
                                <x-phone-field dynamic countries="countries" code="row.phone_code" national="row.phone"
                                               code-name="name(i, 'phone_code')" phone-name="name(i, 'phone')" :show-errors="false"
                                               x-init="$watch('code', v => row.phone_code = v); $watch('national', v => row.phone = v)" />
                                <p x-show="errorFor(i, 'phone')" x-text="errorFor(i, 'phone')" class="mt-1 text-xs text-danger"></p>
                            </div>
                            <div>
                                <label class="{{ $label }}">صفته</label>
                                <input :name="name(i, 'role')" x-model="row.role" placeholder="المالك · الوكيل · المدير" list="owner-contact-roles" class="{{ $field }}">
                                <p x-show="errorFor(i, 'role')" x-text="errorFor(i, 'role')" class="mt-1 text-xs text-danger"></p>
                            </div>
                            <div>
                                <label class="{{ $label }}">إسمه</label>
                                <input :name="name(i, 'name')" x-model="row.name" class="{{ $field }}">
                                <p x-show="errorFor(i, 'name')" x-text="errorFor(i, 'name')" class="mt-1 text-xs text-danger"></p>
                            </div>
                            <button type="button" @click="removeContact(i)" :disabled="rows.length <= 1" title="حذف السطر"
                                    class="lg:mt-7 grid place-items-center w-9 h-9 rounded-full text-danger hover:bg-danger/10 disabled:text-gray-300 disabled:hover:bg-transparent disabled:cursor-not-allowed transition justify-self-end">
                                <x-icon.trash />
                            </button>
                        </div>
                    </template>
                </div>
                <datalist id="owner-contact-roles">
                    <option value="المالك"></option><option value="الوكيل"></option><option value="المدير"></option>
                    <option value="الحارس"></option><option value="قريب المالك"></option><option value="المحامي"></option>
                </datalist>
                <button type="button" @click="add()"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-dashed border-primary-300 text-primary-700 hover:bg-primary-50 px-4 py-2 text-sm font-semibold transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    إضافة رقم
                </button>
            </section>

            {{-- ===== ملفات المالك ===== --}}
            <section>
                <h4 class="font-bold text-sm text-ink mb-3">ملفات المالك</h4>
                <x-file-uploader name="files" remove-name="files_removed" reset-event="owner-files-reset" />
                @error('files')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                @foreach ($errors->get('files.*') as $messages)<p class="mt-1 text-xs text-danger">{{ $messages[0] }}</p>@endforeach
            </section>
        </div>

        <div class="sticky bottom-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/95">
            <button type="button" @click="$dispatch('close-modal', 'owner-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
            <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة المالك'"></button>
        </div>
    </form>
</x-modal>
@endcanany

@can('property_owners.delete')
<x-modal name="owner-delete" maxWidth="md">
    <div class="p-6 text-center">
        <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
        <h3 class="font-bold text-ink mb-1">حذف المالك</h3>
        <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
        <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
            @csrf @method('DELETE')
            <button type="button" @click="$dispatch('close-modal', 'owner-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
            <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
        </form>
    </div>
</x-modal>
@endcan
