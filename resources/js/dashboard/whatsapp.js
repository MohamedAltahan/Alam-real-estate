/**
 * واتساب:
 *  - نافذة إرسال رسالة معاينة: تُفتح من أي زر يحمل data-wa-send (حمولة JSON من السيرفر)
 *    وتعرض أرقام المسؤولين عن العقار بصفاتهم ونص القالب معبّأً قابلاً للتعديل.
 *  - زر محجوب (data-wa-blocked): يعرض سبب الحجب فوراً عند التمرير (فقاعة ملتصقة بالزر) وعند النقر.
 *  - لوحة ربط الرقم بالـ QR (شاشة واتساب): استطلاع كل 3 ثوانٍ حتى «متصل».
 */
import { withAlpine } from './alpine';

const QR_POLL_MS = 3000;
const TOAST_MS = 2500;

/** فقاعة قصيرة أعلى الصفحة (نفس شكل «تم نسخ بيانات العميل») */
export function toast(message, tone = 'bg-primary-950') {
    const el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.className = `fixed top-20 start-1/2 -translate-x-1/2 z-[70] rounded-full ${tone} text-white px-5 py-2.5 text-sm shadow-xl`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), TOAST_MS);
}

/** فقاعة تلميح فورية فوق الزر المحجوب — بلا تأخير الـ title الأصلي (~ثانية) */
let hint = null;

function showHint(target) {
    hideHint();
    const rect = target.getBoundingClientRect();
    hint = document.createElement('div');
    hint.setAttribute('role', 'tooltip');
    hint.className = 'fixed z-[80] pointer-events-none rounded-lg bg-primary-950 text-white text-[11px] font-semibold px-2.5 py-1.5 shadow-lg whitespace-nowrap';
    hint.textContent = target.dataset.waBlocked || 'يجب اختيار النتيجة أولاً';
    document.body.appendChild(hint);
    const top = rect.top - hint.offsetHeight - 6;
    hint.style.top = (top < 4 ? rect.bottom + 6 : top) + 'px';
    hint.style.left = Math.max(4, Math.min(innerWidth - hint.offsetWidth - 4, rect.left + rect.width / 2 - hint.offsetWidth / 2)) + 'px';
}

function hideHint() {
    hint?.remove();
    hint = null;
}

document.addEventListener('mouseover', (event) => {
    const blocked = event.target.closest('[data-wa-blocked]');
    if (blocked && ! blocked.contains(event.relatedTarget)) {
        showHint(blocked);
    }
});
document.addEventListener('mouseout', (event) => {
    const blocked = event.target.closest('[data-wa-blocked]');
    if (blocked && ! blocked.contains(event.relatedTarget)) {
        hideHint();
    }
});
window.addEventListener('scroll', hideHint, true);
window.addEventListener('live-filters:updated', hideHint);

// تفويض: يعمل داخل الجداول التي تُستبدل بالفلاتر الحيّة وخارج أي x-data
document.addEventListener('click', (event) => {
    const blocked = event.target.closest('[data-wa-blocked]');

    if (blocked) {
        event.preventDefault();
        toast(blocked.dataset.waBlocked || 'يجب اختيار النتيجة أولاً', 'bg-danger');

        return;
    }

    const trigger = event.target.closest('[data-wa-send]');

    if (! trigger || trigger.disabled) {
        return;
    }

    event.preventDefault();

    try {
        window.dispatchEvent(new CustomEvent('wa-send', { detail: JSON.parse(trigger.dataset.waSend) }));
    } catch (error) {
        console.error('wa-send payload:', error);
    }
});

withAlpine((Alpine) => {
    Alpine.data('waSend', () => ({
        payload: { recipients: [], bodies: {}, title: '', reference: '', client: '', action: '', kind: '', connected: false },
        to: '',
        body: '',
        dirty: false,

        start(payload) {
            this.payload = { recipients: [], bodies: {}, ...payload };
            this.dirty = false;
            this.to = this.payload.recipients[0]?.phone ?? '';
            this.body = this.payload.bodies[this.to] ?? '';
            this.$dispatch('open-modal', 'wa-send');
        },

        /** تغيير المستلم يعيد تعبئة النص ما لم يكن المستخدم عدّله بنفسه */
        pick(phone) {
            this.to = phone;

            if (! this.dirty) {
                this.body = this.payload.bodies[phone] ?? this.body;
            }
        },

        get canSend() {
            return this.to !== '' && this.body.trim() !== '' && this.payload.connected;
        },
    }));

    Alpine.data('qrPanel', (opts = {}) => ({
        url: opts.url,
        status: opts.status ?? 'created',
        qr: null,
        phone: opts.phone ?? null,
        label: opts.label ?? '',
        error: '',
        timer: null,

        init() {
            if (this.status === 'connected') {
                return;
            }

            this.load();
            this.timer = setInterval(() => this.load(), QR_POLL_MS);
        },

        async load() {
            try {
                const response = await fetch(this.url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await response.json();

                this.status = data.status ?? this.status;
                this.qr = data.qr ?? null;
                this.phone = data.phone ?? this.phone;
                this.label = data.label ?? this.label;
                this.error = data.error ?? '';

                if (this.status === 'connected') {
                    clearInterval(this.timer);
                    // الصفحة تُعاد لتظهر بطاقة «متصل» وتُحدَّث الأيقونة
                    setTimeout(() => window.location.reload(), 800);
                }
            } catch {
                this.error = 'تعذّر الاتصال بالبوابة';
            }
        },
    }));
});
