/**
 * جرس الإشعارات: استطلاع كل دقيقة (polling) لتذكيرات المعاينات — بدون اتصال لحظي.
 *
 * - يحدّث العدّاد ويضيف إشعارات المعاينات الجديدة أعلى القائمة.
 * - يشغّل «بيب بيب» لكل تذكير معاينة جديد لم يُفتح (مرة واحدة لكل إشعار)،
 *   ولو إعداد التكرار مفعّل يعيد التنبيه كل دقيقة ما دام هناك تذكير غير مفتوح.
 * - باقي الإشعارات (طلبات التواصل…) بلا صوت.
 */
import { withAlpine } from './alpine';
import { beep } from './beep';

const POLL_MS = 60_000;
const FIRST_POLL_MS = 4_000;
const STORAGE_KEY = 'alam.notifications.beeped';

function readSeen() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch {
        return [];
    }
}

function writeSeen(ids) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids.slice(-200)));
    } catch {
        // التخزين المحلي غير متاح — نتجاهل
    }
}

withAlpine((Alpine) => {
    Alpine.data('notificationBell', (opts = {}) => ({
        open: false,
        unread: Number(opts.unread ?? 0),
        viewingUnread: 0,
        repeat: Boolean(opts.repeat),
        pollUrl: opts.pollUrl ?? '',
        timer: null,
        toast: false,
        toastText: '',

        init() {
            if (! this.pollUrl) {
                return;
            }

            setTimeout(() => this.poll(), FIRST_POLL_MS);
            this.timer = setInterval(() => this.poll(), POLL_MS);
            document.addEventListener('visibilitychange', () => {
                if (! document.hidden) {
                    this.poll();
                }
            });
        },

        async poll() {
            if (document.hidden || ! this.pollUrl) {
                return;
            }

            try {
                const response = await fetch(this.pollUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    return;
                }

                const data = await response.json();
                const items = Array.isArray(data.items) ? data.items : [];

                this.unread = Number(data.unread_total ?? 0);
                this.viewingUnread = Number(data.viewing_unread ?? 0);
                this.repeat = Boolean(data.repeat_beep);

                this.merge(items);
                this.maybeBeep(items);
            } catch {
                // فشل الشبكة — نحاول في الدورة التالية
            }
        },

        /** إضافة الإشعارات المخزّنة الجديدة إلى القائمة وتحديث نقطة «غير مقروء» */
        merge(items) {
            const list = this.$refs.list;
            const template = this.$refs.itemTemplate;
            if (! list || ! template) {
                return;
            }

            items.slice().reverse().forEach((item) => {
                let el = list.querySelector(`[data-notification-id="${item.id}"]`);

                if (! el) {
                    el = template.content.firstElementChild.cloneNode(true);
                    el.dataset.notificationId = item.id;
                    el.href = item.url;
                    el.querySelector('[data-title]').textContent = item.title;
                    el.querySelector('[data-time]').textContent = item.human ?? '';
                    list.querySelector('[data-empty]')?.remove();
                    list.prepend(el);
                }

                el.querySelector('[data-dot]')?.classList.toggle('hidden', Boolean(item.read));
            });
        },

        maybeBeep(items) {
            const seen = readSeen();
            const fresh = items.filter((item) => item.kind === 'viewing' && ! item.read && ! seen.includes(item.id));

            if (fresh.length) {
                beep();
                this.showToast(fresh[0].title);
                writeSeen([...seen, ...fresh.map((item) => item.id)]);

                return;
            }

            if (this.repeat && this.viewingUnread > 0) {
                beep();
            }
        },

        showToast(text) {
            this.toastText = text;
            this.toast = true;
            setTimeout(() => { this.toast = false; }, 7000);
        },
    }));
});
