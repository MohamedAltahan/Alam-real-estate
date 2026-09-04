/**
 * لوحة المهام (Kanban):
 *  - السحب والإفلات بين الأعمدة عبر SortableJS، ثم PATCH tasks/{id}/move بالحالة الجديدة
 *    وترتيب بطاقات العمود الهدف. عند الفشل نعيد تحميل اللوحة بالفلاتر الحالية.
 *  - يعاد الربط بعد كل تبديل للنتائج من الفلاتر الحيّة (live-filters:updated).
 *  - Alpine.data('taskBoard'): فتح تفاصيل المهمة في نافذة (XHR) + فورم الإضافة/التعديل.
 *
 * الاستخدام: <div x-data="taskBoard({ storeUrl, showBase, reopen, openTask })">
 */
import Sortable from 'sortablejs';
import { withAlpine } from './alpine';

const COLUMN = '[data-task-column]';
const CARD = '[data-task-card]';

let instances = [];

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function moveUrl(id) {
    const base = document.querySelector('[data-task-board]')?.dataset.moveBase ?? '';

    return `${base}/${id}/move`;
}

/** تحديث شارات عدد المهام في رؤوس الأعمدة */
function paintCounts(counts) {
    document.querySelectorAll(COLUMN).forEach((column) => {
        const badge = column.closest('[data-task-lane]')?.querySelector('[data-task-count]');
        if (badge) {
            badge.textContent = counts[column.dataset.status] ?? column.querySelectorAll(CARD).length;
        }
    });
}

async function persistMove(card, column) {
    const status = column.dataset.status;
    const order = Array.from(column.querySelectorAll(CARD)).map((el) => Number(el.dataset.taskCard));

    try {
        const response = await fetch(moveUrl(card.dataset.taskCard), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status, order }),
        });

        if (! response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const data = await response.json();
        card.dataset.taskStatus = status;
        card.classList.toggle('is-done', status === 'done');
        paintCounts(data.counts ?? {});
    } catch (error) {
        console.error('task move:', error);
        // نعيد اللوحة من السيرفر حتى لا تبقى البطاقة في مكان لم يُحفظ
        window.dispatchEvent(new CustomEvent('live-filters:refresh'));
    }
}

function bind() {
    instances.forEach((instance) => instance.destroy());
    instances = [];

    document.querySelectorAll(COLUMN).forEach((column) => {
        instances.push(Sortable.create(column, {
            group: 'tasks',
            animation: 150,
            delay: 150,
            delayOnTouchOnly: true,
            draggable: CARD,
            filter: '.no-drag, a, button',
            preventOnFilter: false,
            ghostClass: 'task-ghost',
            chosenClass: 'task-chosen',
            dragClass: 'task-drag',
            onEnd(event) {
                const moved = event.from !== event.to || event.oldIndex !== event.newIndex;

                if (moved) {
                    persistMove(event.item, event.to);
                }
            },
        }));
    });
}

bind();
document.addEventListener('live-filters:updated', bind);

withAlpine((Alpine) => {
    const BLANK = {
        id: null, action: '', title: '', description: '', status: 'new', priority: 'medium',
        due_date: '', assignee_id: '', property_id: '', property_label: '', files: [],
    };

    Alpine.data('taskBoard', (opts = {}) => ({
        mode: 'add',
        form: { ...BLANK },
        detail: '',
        detailId: null,
        loading: false,
        delAction: '',
        delRef: '',

        init() {
            const reopen = opts.reopen;

            if (reopen) {
                // فشل التحقق: نعيد فتح فورم المهمة بالقيم المُدخلة
                this.mode = reopen.mode ?? 'add';
                this.fill(reopen.form ?? {});
                this.$nextTick(() => this.openForm());

                return;
            }

            if (opts.openTask) {
                // بعد تهيئة مودال التفاصيل حتى يلتقط حدث الفتح
                this.$nextTick(() => this.openTask(opts.openTask));
            }
        },

        /** تفاصيل المهمة تُجلب كجزء HTML وتُحقن في النافذة */
        async openTask(id) {
            this.detailId = id;
            this.loading = true;
            this.detail = '';
            this.$dispatch('open-modal', 'task-view');

            try {
                const response = await fetch(`${opts.showBase}/${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                this.detail = await response.text();
            } catch (error) {
                console.error('task detail:', error);
                this.detail = '<p class="p-8 text-center text-sm text-danger">تعذّر تحميل المهمة.</p>';
            } finally {
                this.loading = false;
            }
        },

        startAdd() {
            this.mode = 'add';
            this.fill({ action: opts.storeUrl });
            this.openForm();
        },

        startEdit(task) {
            this.mode = 'edit';
            this.fill(task);
            this.$dispatch('close-modal', 'task-view');
            this.openForm();
        },

        /** نعدّل كائن الفورم نفسه لا نستبدله — حقل بحث العقار يحتفظ بمرجعه (row) */
        fill(values) {
            Object.assign(this.form, BLANK, { files: [] }, values);
        },

        openForm() {
            this.$dispatch('task-files-reset', this.form.files ?? []);
            this.$nextTick(() => {
                // منتقي التاريخ يحتفظ بقيمته الخاصة — نزامنه مع الفورم الحالي
                this.$refs.due?._flatpickr?.setDate(this.form.due_date || null, false);
                this.$dispatch('open-modal', 'task-form');
            });
        },

        /** نقل سريع من نافذة التفاصيل ثم إعادة تحميل النافذة واللوحة */
        async quickMove(id, status) {
            this.loading = true;

            try {
                const response = await fetch(moveUrl(id), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ status, order: [id] }),
                });

                if (! response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
            } catch (error) {
                console.error('task move:', error);
            }

            window.dispatchEvent(new CustomEvent('live-filters:refresh'));
            await this.openTask(id);
        },

        startDelete(action, ref) {
            this.delAction = action;
            this.delRef = ref;
            this.$dispatch('close-modal', 'task-view');
            this.$dispatch('open-modal', 'task-delete');
        },
    }));
});
