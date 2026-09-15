/**
 * ملاحظة المعاينة من الخارج: زر يحمل data-viewing-notes (JSON: action, notes, title, subtitle)
 * يفتح نافذة «viewing-notes» لتعديل الملاحظة وحفظها بـ PATCH دون الدخول إلى المعاينة.
 * بعد الحفظ تُحدَّث النتائج الحيّة (live-filters:refresh) أو تُعاد الصفحة.
 */
import { withAlpine } from './alpine';
import { toast } from './whatsapp';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

// تفويض: يعمل داخل الجداول التي تُستبدل بالفلاتر الحيّة
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-viewing-notes]');

    if (! trigger) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    try {
        window.dispatchEvent(new CustomEvent('viewing-notes', { detail: JSON.parse(trigger.dataset.viewingNotes) }));
    } catch (error) {
        console.error('viewing-notes payload:', error);
    }
});

withAlpine((Alpine) => {
    Alpine.data('viewingNotes', () => ({
        action: '',
        title: '',
        subtitle: '',
        notes: '',
        saving: false,
        error: '',

        start(payload = {}) {
            this.action = payload.action ?? '';
            this.title = payload.title ?? 'ملاحظة المعاينة';
            this.subtitle = payload.subtitle ?? '';
            this.notes = payload.notes ?? '';
            this.error = '';
            this.$dispatch('open-modal', 'viewing-notes');
            this.$nextTick(() => this.$refs.notes?.focus());
        },

        async save() {
            if (this.saving || ! this.action) {
                return;
            }

            this.saving = true;
            this.error = '';

            try {
                const response = await fetch(this.action, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ notes: this.notes }),
                });

                if (! response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || ('HTTP ' + response.status));
                }

                this.$dispatch('close-modal', 'viewing-notes');
                toast('تم حفظ الملاحظة', 'bg-success');

                if (document.querySelector('form[data-live-filters]')) {
                    window.dispatchEvent(new CustomEvent('live-filters:refresh'));
                } else {
                    window.location.reload();
                }
            } catch (error) {
                this.error = error.message || 'تعذّر حفظ الملاحظة';
            } finally {
                this.saving = false;
            }
        },
    }));
});
