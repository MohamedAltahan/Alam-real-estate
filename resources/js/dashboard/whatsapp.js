/**
 * واتساب:
 *  - نافذة إرسال رسالة معاينة: تُفتح من أي زر يحمل data-wa-send (حمولة JSON من السيرفر)
 *    وتعرض أرقام المستلمين (المالك بصفاته / العميل) ونص القالب معبّأً قابلاً للتعديل.
 *  - لوحة ربط الرقم بالـ QR (شاشة واتساب): استطلاع كل 3 ثوانٍ حتى «متصل».
 */
import { withAlpine } from './alpine';

const QR_POLL_MS = 3000;

// تفويض: يعمل داخل الجداول التي تُستبدل بالفلاتر الحيّة وخارج أي x-data
document.addEventListener('click', (event) => {
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
