/**
 * تبويب «واتر مارك»: معاينة حيّة للشعار على صورة عقار (المكان والحجم كما تُطبَّق فعلياً)
 * + تحديث صور العقارات الحالية دفعةً دفعة (المشروع بلا queue worker).
 */
import { withAlpine } from './alpine';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

withAlpine((Alpine) => {
    Alpine.data('watermarkPage', (opts = {}) => ({
        enabled: !! opts.enabled,
        position: opts.position ?? 'bottom-right',
        size: opts.size ?? 'medium',
        positions: opts.positions ?? {},
        sizes: opts.sizes ?? {},
        padding: opts.padding ?? 3,
        opacity: opts.opacity ?? 70,
        logoUrl: opts.logoUrl ?? null,
        preview: null,
        removed: false,
        dirty: false,

        // تحديث الصور الحالية
        applying: false,
        total: opts.imagesCount ?? 0,
        processed: 0,
        failed: 0,
        cursor: 0,
        finished: false,
        error: '',

        init() {
            // تحذير قبل مغادرة الصفحة أثناء تحديث الصور (يتوقف التحديث عند المغادرة)
            window.addEventListener('beforeunload', (event) => {
                if (this.applying) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        get currentLogo() {
            return this.preview || (this.removed ? null : this.logoUrl);
        },

        get sizePercent() {
            return this.sizes[this.size]?.percent ?? 25;
        },

        get positionLabel() {
            return this.positions[this.position] ?? '';
        },

        /** نفس حساب Spatie: العرض والمسافة كنسبة من أبعاد الصورة النهائية */
        get markStyle() {
            const [vertical, horizontal] = this.axes(this.position);
            const pad = this.padding + '%';
            const style = {
                position: 'absolute',
                width: this.sizePercent + '%',
                height: 'auto',
                opacity: this.opacity / 100,
            };
            const shift = [];

            if (vertical === 'top') style.top = pad;
            else if (vertical === 'bottom') style.bottom = pad;
            else { style.top = '50%'; shift.push('translateY(-50%)'); }

            if (horizontal === 'left') style.left = pad;
            else if (horizontal === 'right') style.right = pad;
            else { style.left = '50%'; shift.push('translateX(-50%)'); }

            if (shift.length) style.transform = shift.join(' ');

            return style;
        },

        axes(position) {
            if (position === 'center') return ['center', 'center'];
            if (position === 'top' || position === 'bottom') return [position, 'center'];
            if (position === 'left' || position === 'right') return ['center', position];

            return position.split('-');
        },

        choosePosition(key) {
            this.position = key;
            this.dirty = true;
        },

        chooseSize(key) {
            this.size = key;
            this.dirty = true;
        },

        pickLogo(event) {
            const file = event.target.files[0];

            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = file ? URL.createObjectURL(file) : null;

            if (file) {
                this.removed = false;
                this.enabled = true;
            }
            this.dirty = true;
        },

        removeLogo() {
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = null;
            this.removed = true;
            this.enabled = false;
            this.$refs.logo.value = '';
            this.dirty = true;
        },

        get progress() {
            return this.total ? Math.min(100, Math.round((this.processed / this.total) * 100)) : 0;
        },

        /** يستدعي نقطة التحديث بالتتابع حتى تنتهي كل الصور — ويُكمل من حيث توقف بعد أي خطأ */
        async applyExisting() {
            if (this.applying || this.dirty) return;

            if (this.finished) {
                this.cursor = 0;
                this.processed = 0;
                this.failed = 0;
            }
            this.applying = true;
            this.finished = false;
            this.error = '';

            try {
                while (true) {
                    const response = await fetch(opts.applyUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ after: this.cursor }),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        throw new Error(data.message || ('HTTP ' + response.status));
                    }

                    this.processed += data.processed;
                    this.failed += data.failed;
                    this.cursor = data.next;
                    this.total = this.processed + data.remaining;

                    if (data.done || data.processed === 0) break;
                }
                this.finished = true;
            } catch (e) {
                this.error = 'توقف التحديث (' + e.message + ') — اضغط الزر مرة أخرى للمتابعة من حيث توقف.';
            } finally {
                this.applying = false;
            }
        },
    }));
});
