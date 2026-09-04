/**
 * حقل الهاتف: قائمة مفاتيح الدول قابلة للبحث (بالمفتاح أو اسم الدولة عربي/إنجليزي)
 * + رقم محلي أرقام فقط. الكويت هي الافتراضي.
 *
 * الاستخدام: <div x-data="phoneField({ countries, code, national })">
 */
import { withAlpine } from './alpine';

const MAX_RESULTS = 80;

/** علم الدولة من رمز ISO (رموز المؤشر الإقليمي) */
function flag(iso) {
    if (! iso || iso.length !== 2 || iso === 'XK') {
        return '🌐';
    }

    return [...iso.toUpperCase()].map((c) => String.fromCodePoint(127397 + c.charCodeAt(0))).join('');
}

withAlpine((Alpine) => {
    Alpine.data('phoneField', (opts = {}) => ({
        countries: opts.countries ?? [],
        code: opts.code || '+965',
        national: String(opts.national ?? ''),
        q: '',
        open: false,
        hi: 0,

        get current() {
            return this.countries.find((c) => c.code === this.code)
                ?? { iso: '', code: this.code, ar: '', en: '' };
        },

        flag,

        filtered() {
            const q = this.q.trim().toLowerCase();
            if (! q) {
                return this.countries;
            }

            const digits = q.replace(/[^0-9]/g, '');

            return this.countries.filter((c) =>
                c.ar.includes(q)
                || c.en.toLowerCase().includes(q)
                || c.iso.toLowerCase() === q
                || (digits !== '' && c.code.replace('+', '').startsWith(digits)),
            ).slice(0, MAX_RESULTS);
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            this.q = '';
            this.hi = 0;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        close() {
            this.open = false;
        },

        pick(country) {
            this.code = country.code;
            this.close();
            this.$nextTick(() => this.$refs.national?.focus());
        },

        onKey(e) {
            const list = this.filtered();
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.hi = Math.min(this.hi + 1, list.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.hi = Math.max(this.hi - 1, 0);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (list[this.hi]) {
                    this.pick(list[this.hi]);
                }
            } else if (e.key === 'Escape') {
                this.close();
            }
        },

        /** أرقام فقط في الرقم المحلي */
        sanitize() {
            this.national = this.national.replace(/[^0-9]/g, '');
        },
    }));
});
