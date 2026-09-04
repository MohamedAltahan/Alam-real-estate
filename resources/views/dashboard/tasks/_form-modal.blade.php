{{--
    مودال إضافة/تعديل مهمة — يعتمد على x-data="taskBoard(...)" في الصفحة الأم
    (resources/js/dashboard/task-board.js). الحالة تُعدَّل من هنا في وضع التعديل فقط.
--}}
@php
    use App\Models\Task;

    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $label = 'block text-sm font-medium text-gray-700 mb-1.5';
@endphp

@canany(['tasks.create', 'tasks.edit'])
<x-modal name="task-form" maxWidth="3xl">
    <form :action="form.action" method="POST" enctype="multipart/form-data">
        @csrf
        <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
        <input type="hidden" name="task_id" :value="form.id || ''">
        <input type="hidden" name="property_id" :value="form.property_id">

        <div class="sticky top-0 z-10 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل المهمة #' + form.id : 'مهمة جديدة'"></h3>
            <button type="button" @click="$dispatch('close-modal', 'task-form')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="{{ $label }}">العنوان <span class="text-danger">*</span></label>
                    <input name="title" x-model="form.title" required maxlength="200" class="{{ $field }}">
                    @error('title')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="{{ $label }}">الوصف</label>
                    <textarea name="description" x-model="form.description" rows="4" class="{{ $field }}"></textarea>
                    @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">المسند إليه</label>
                    <select name="assignee_id" x-model="form.assignee_id" class="{{ $field }}">
                        <option value="">— غير مسندة —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                    @error('assignee_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">الأولوية <span class="text-danger">*</span></label>
                    <select name="priority" x-model="form.priority" required class="{{ $field }}">
                        @foreach (Task::PRIORITIES as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach
                    </select>
                    @error('priority')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">تاريخ الاستحقاق</label>
                    <input x-datetime.date x-ref="due" name="due_date" x-model="form.due_date" placeholder="اختر التاريخ" class="{{ $field }}" dir="ltr">
                    @error('due_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- الحالة: في التعديل فقط (الجديدة تبدأ من «جديد») — الحقل المعطّل لا يُرسل --}}
                <div x-show="mode === 'edit'">
                    <label class="{{ $label }}">الحالة</label>
                    <select name="status" x-model="form.status" :disabled="mode !== 'edit'" class="{{ $field }}">
                        @foreach (Task::STATUSES as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach
                    </select>
                    @error('status')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- العقار المرتبط: بحث بالرقم المرجعي أو الاسم --}}
                <div class="sm:col-span-2" x-data="propertyLookup({ url: @js(route('dashboard.tasks.property-lookup')), row: form })"
                     x-init="$watch('form.property_label', (v) => q = v || '')" @click.outside="open = false">
                    <label class="{{ $label }}">العقار المرتبط <span class="text-gray-400 font-normal">(اختياري)</span></label>
                    <div class="relative">
                        <input x-ref="input" x-model="q" @input="search()" @keydown="onKey($event)" @blur="onBlur()"
                               @focus="q.trim().length >= 2 && results.length && (open = true)"
                               placeholder="ابحث بالرقم أو اسم العقار..." autocomplete="off" class="{{ $field }} pe-9">
                        <button type="button" x-show="row.property_id" @click="clear()" title="مسح"
                                class="absolute end-2 top-1/2 -translate-y-1/2 grid place-items-center w-6 h-6 rounded-full text-gray-400 hover:text-danger hover:bg-danger/10">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                        <svg x-show="! row.property_id && ! loading" class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <span x-show="loading" class="absolute end-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full border-2 border-primary-300 border-t-primary-800 animate-spin"></span>

                        <div x-show="open" x-cloak
                             class="absolute z-40 mt-1 w-full rounded-2xl bg-white border border-gray-100 shadow-2xl p-1.5 max-h-64 overflow-y-auto">
                            <template x-for="(item, j) in results" :key="item.id">
                                <button type="button" @mousedown.prevent="pick(item)" @mouseenter="hi = j"
                                        :class="hi === j ? 'bg-primary-50' : ''"
                                        class="w-full text-start rounded-xl px-3 py-2 text-sm transition">
                                    <span class="flex items-center justify-between gap-2">
                                        <strong class="text-ink" dir="ltr" x-text="item.reference_code"></strong>
                                        <span class="text-[11px] text-gray-400" x-text="item.area || ''"></span>
                                    </span>
                                    <span class="block text-xs text-gray-500 truncate" x-text="item.title || ''"></span>
                                </button>
                            </template>
                            <p x-show="! results.length" class="px-3 py-3 text-sm text-gray-400 text-center">لا توجد نتائج مطابقة</p>
                        </div>
                    </div>
                    <p x-show="row.property_id && ! open" class="mt-1 text-[11px] text-success font-semibold">تم اختيار <span dir="ltr" x-text="row.property_label"></span></p>
                    @error('property_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <section>
                <h4 class="font-bold text-sm text-ink mb-2">المرفقات</h4>
                <x-file-uploader name="files" remove-name="files_removed" reset-event="task-files-reset" />
                @error('files.*')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </section>
        </div>

        <div class="sticky bottom-0 bg-white flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button type="button" @click="$dispatch('close-modal', 'task-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
            <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm transition" x-text="mode === 'edit' ? 'حفظ التعديلات' : 'إضافة المهمة'"></button>
        </div>
    </form>
</x-modal>
@endcanany
