/**
 * اختيار العقار بالبحث (بالرقم المرجعي أو العنوان) — يطلب النتائج من السيرفر أثناء الكتابة.
 *
 * الاستخدام داخل سطر معاينة: <div x-data="propertyLookup({ url, row })">
 * يكتب في row.property_id و row.property_label (السطر عنصر تفاعلي من x-for).
 */
import { withAlpine } from './alpine';

const DEBOUNCE_MS = 300;
const MIN_CHARS = 2;

withAlpine((Alpine) => {
    Alpine.data('propertyLookup', (opts = {}) => ({
        url: opts.url,
        row: opts.row,
        q: String(opts.row?.property_label ?? ''),
        results: [],
        open: false,
        loading: false,
        hi: 0,
        timer: null,
        controller: null,

        search() {
            clearTimeout(this.timer);
            const q = this.q.trim();

            if (q.length < MIN_CHARS) {
                this.results = [];
                this.open = false;

                return;
            }

            this.timer = setTimeout(() => this.fetch(q), DEBOUNCE_MS);
        },

        async fetch(q) {
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;

            try {
                const response = await fetch(`${this.url}?q=${encodeURIComponent(q)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: this.controller.signal,
                });

                if (! response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                this.results = await response.json();
                this.hi = 0;
                this.open = true;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('property lookup:', error);
                }
            } finally {
                this.loading = false;
            }
        },

        pick(item) {
            if (item.blocked) {
                return;
            }

            this.row.property_id = String(item.id);
            this.row.property_label = item.label;
            this.q = item.label;
            this.open = false;
        },

        clear() {
            this.row.property_id = '';
            this.row.property_label = '';
            this.q = '';
            this.results = [];
            this.open = false;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        onBlur() {
            // مهلة قصيرة حتى يُسجَّل النقر على نتيجة قبل إغلاق القائمة
            setTimeout(() => {
                this.open = false;
                this.q = this.row.property_id ? this.row.property_label : '';
            }, 180);
        },

        onKey(e) {
            if (! this.open) {
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.hi = Math.min(this.hi + 1, this.results.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.hi = Math.max(this.hi - 1, 0);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.results[this.hi]) {
                    this.pick(this.results[this.hi]);
                }
            } else if (e.key === 'Escape') {
                this.open = false;
            }
        },
    }));
});
