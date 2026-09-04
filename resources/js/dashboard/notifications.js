/**
 * جرس الإشعارات: استطلاع كل دقيقة (polling) لتذكيرات المعاينات — بدون اتصال لحظي.
 *
 * - يحدّث العدّاد ويضيف إشعارات المعاينات الجديدة أعلى القائمة.
 * - يعرض تنبيهًا ثابتًا أسفل يسار الشاشة لكل تذكير معاينة لم يُفتح — لا يختفي
 *   إلا إذا أغلقه المستخدم (يُحفظ الإغلاق محليًا فلا يعود بعد الاستطلاع التالي)
 *   أو فتح الإشعار من أي مكان. الضغط عليه يفتح صفحة المعاينات.
 * - يشغّل «بيب بيب» لكل تذكير معاينة جديد لم يُفتح (مرة واحدة لكل إشعار)،
 *   ولو إعداد التكرار مفعّل يعيد التنبيه كل دقيقة ما دام هناك تذكير غير مفتوح.
 * - باقي الإشعارات (طلبات التواصل…) بلا صوت وبلا تنبيه منبثق.
 */
import { withAlpine } from './alpine';
import { beep } from './beep';

const POLL_MS = 60_000;
const FIRST_POLL_MS = 2_000;
const BEEPED_KEY = 'alam.notifications.beeped';
const DISMISSED_KEY = 'alam.notifications.dismissed';
const KEEP_IDS = 200;

function readSet(key) {
    try {
        const value = JSON.parse(localStorage.getItem(key) || '[]');

        return Array.isArray(value) ? value : [];
    } catch {
        return [];
    }
}

function writeSet(key, ids) {
    try {
        localStorage.setItem(key, JSON.stringify(ids.slice(-KEEP_IDS)));
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
        toasts: [],

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
                this.syncToasts(items);
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
                    list.querySelector('[data-empty]')?.remove();
                    list.prepend(el);
                }

                // النص والوقت يُحدَّثان في كل استطلاع حتى لا يتجمّد «بعد 30 دقيقة»
                el.querySelector('[data-title]').textContent = item.title;
                el.querySelector('[data-time]').textContent = item.human ?? '';
                el.querySelector('[data-dot]')?.classList.toggle('hidden', Boolean(item.read));
            });
        },

        /** التنبيهات المنبثقة = تذكيرات المعاينات غير المفتوحة والتي لم يُغلقها المستخدم */
        syncToasts(items) {
            const pending = items.filter((item) => item.kind === 'viewing' && ! item.read);
            const dismissed = readSet(DISMISSED_KEY);

            // إشعار اتفتح من مكان تاني → يختفي تنبيهه
            this.toasts = this.toasts.filter((toast) => pending.some((item) => item.id === toast.id));

            pending.forEach((item) => {
                if (dismissed.includes(item.id)) {
                    return;
                }

                const existing = this.toasts.find((toast) => toast.id === item.id);

                // نص التنبيه يُعاد حسابه من السيرفر في كل استطلاع (لا يتجمّد)
                if (existing) {
                    existing.title = item.title;
                    existing.human = item.human ?? '';
                    existing.overdue = Boolean(item.overdue);

                    return;
                }

                this.toasts.push({
                    id: item.id,
                    title: item.title,
                    url: item.url,
                    human: item.human ?? '',
                    overdue: Boolean(item.overdue),
                });
            });
        },

        /** الإغلاق يدويًا: لا يعود التنبيه حتى لو ظل الإشعار غير مقروء */
        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
            writeSet(DISMISSED_KEY, [...readSet(DISMISSED_KEY), id]);
        },

        maybeBeep(items) {
            const seen = readSet(BEEPED_KEY);
            const fresh = items.filter((item) => item.kind === 'viewing' && ! item.read && ! seen.includes(item.id));

            if (fresh.length) {
                beep();
                writeSet(BEEPED_KEY, [...seen, ...fresh.map((item) => item.id)]);

                return;
            }

            if (this.repeat && this.viewingUnread > 0) {
                beep();
            }
        },
    }));
});
