import './bootstrap';

import Alpine from 'alpinejs';

/**
 * قائمة منسدلة قابلة للبحث (searchable dropdown)
 * الاستخدام: <x-site.combobox name="area" :items="$items" />
 */
Alpine.data('combobox', (opts = {}) => ({
    open: false,
    q: '',
    value: opts.value ?? '',
    label: opts.label ?? '',
    items: opts.items ?? [],

    toggle() {
        this.open = ! this.open;
        if (this.open) {
            this.$nextTick(() => this.$refs.search?.focus());
        }
    },

    filtered() {
        const q = this.q.trim().toLowerCase();

        return q ? this.items.filter((i) => String(i.l).toLowerCase().includes(q)) : this.items;
    },

    pick(item) {
        this.value = String(item.v);
        this.label = item.l;
        this.close();
    },

    clear() {
        this.value = '';
        this.label = '';
        this.close();
    },

    close() {
        this.open = false;
        this.q = '';
    },
}));

/**
 * شريط تمرير أفقي بأسهم تنقّل.
 * الاستخدام: <section x-data="carousel"> ... <div x-ref="track"> ... </div>
 * ملاحظة: داخل دوال x-data يجب استخدام this.$refs وليس $refs المجرّدة.
 */
Alpine.data('carousel', () => ({
    canScroll: false,

    init() {
        this.$nextTick(() => this.measure());
        const el = this.$refs.track;
        if (el) {
            el.addEventListener('scroll', () => this.measure(), { passive: true });
        }
        window.addEventListener('resize', () => this.measure());
    },

    measure() {
        const el = this.$refs.track;
        this.canScroll = !!el && el.scrollWidth - el.clientWidth > 4;
    },

    nav(direction) {
        const el = this.$refs.track;
        if (! el) {
            return;
        }
        // في الاتجاه العربي (RTL) يتناقص scrollLeft عند التقدّم، لذا نعكس الإشارة
        const rtl = getComputedStyle(el).direction === 'rtl';
        const step = direction * el.clientWidth * 0.8 * (rtl ? -1 : 1);

        el.scrollBy({ left: step, behavior: 'smooth' });
    },
}));

/* ==========================================================================
 | منطقة إفلات الصور (Drop zone) — تُستخدم في كل حقول الصور بالنظام
 |
 | سحب وإفلات أو اختيار · معاينة · حذف · ضغط في المتصفح قبل الرفع: كل صورة
 | تُصغَّر إلى ارتفاع 1080px مع الحفاظ على النسبة فيقلّ حجم الرفع كثيراً.
 | السيرفر يعيد التحويل بنفس القاعدة (medialibrary) كضمان نهائي.
 |
 | الملفات المضغوطة تُحقن في <input type="file"> عبر DataTransfer فتُرسَل مع
 | الفورم بشكل طبيعي — بدون رفع منفصل أو مسار API إضافي.
 ========================================================================== */
const MAX_UPLOAD_BYTES = 6 * 1024 * 1024;   // ٦ ميجابايت
const TARGET_HEIGHT = 1080;

async function compressImage(file, maxHeight = TARGET_HEIGHT) {
    if (! file.type.startsWith('image/') || file.type === 'image/gif') {
        return file;
    }

    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch {
        return file;                         // صيغة لا يفهمها المتصفح — تُرفع كما هي
    }

    if (bitmap.height <= maxHeight) {
        bitmap.close?.();

        return file;                         // أصغر من الحد أصلاً
    }

    const scale = maxHeight / bitmap.height;
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = maxHeight;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.85));

    if (! blob || blob.size >= file.size) {
        return file;                         // الضغط لم يفد — نُبقي الأصل
    }

    return new File([blob], file.name.replace(/\.[^.]+$/, type === 'image/png' ? '.png' : '.jpg'), { type });
}

Alpine.data('dropzone', (opts = {}) => ({
    multiple: opts.multiple ?? false,
    existing: opts.existing ?? [],           // [{ id, url }] الصور المحفوظة
    removed: [],                             // معرّفات الصور المطلوب حذفها
    picked: [],                              // [{ url, name, size, file }] الجديدة
    dragging: false,
    busy: false,
    error: '',

    get visibleExisting() {
        return this.existing.filter((m) => ! this.removed.includes(m.id));
    },

    get isEmpty() {
        return this.visibleExisting.length === 0 && this.picked.length === 0;
    },

    async add(fileList) {
        this.error = '';
        this.busy = true;

        for (const file of Array.from(fileList)) {
            if (! file.type.startsWith('image/')) {
                this.error = 'الملفات المسموحة صور فقط (jpg · png · webp).';
                continue;
            }
            if (file.size > MAX_UPLOAD_BYTES) {
                this.error = `«${file.name}» أكبر من ٦ ميجابايت.`;
                continue;
            }

            const compressed = await compressImage(file);

            // الحقل المفرد: الصورة الجديدة تحلّ محل القديمة
            if (! this.multiple) {
                this.picked = [];
                this.removed = this.existing.map((m) => m.id);
            }

            this.picked.push({
                url: URL.createObjectURL(compressed),
                name: compressed.name,
                size: compressed.size,
                file: compressed,
            });
        }

        this.busy = false;
        this.syncInput();
    },

    dropFiles(event) {
        this.dragging = false;
        this.add(event.dataTransfer.files);
    },

    removePicked(index) {
        URL.revokeObjectURL(this.picked[index].url);
        this.picked.splice(index, 1);
        this.syncInput();
    },

    removeExisting(id) {
        this.removed.push(id);
    },

    /** حقن الملفات المضغوطة في حقل الرفع ليُرسَل مع الفورم */
    syncInput() {
        const transfer = new DataTransfer();
        this.picked.forEach((p) => transfer.items.add(p.file));
        this.$refs.input.files = transfer.files;
    },

    human(bytes) {
        return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB';
    },
}));

/**
 * مشغّل الفيديو داخل الموقع — مخزن عام حتى يفتحه أي زر في أي صفحة.
 * الاستخدام: @click="$store.video.show('YT_ID', 'عنوان')"
 */
Alpine.store('video', {
    open: false,
    src: '',
    title: '',

    show(id, title = '') {
        if (! id) {
            return;
        }
        // الـ src يُضبط عند الفتح فقط ويُفرَّغ عند الإغلاق حتى يتوقّف التشغيل
        this.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0&modestbranding=1`;
        this.title = title;
        this.open = true;
        document.body.style.overflow = 'hidden';
    },

    close() {
        this.open = false;
        this.src = '';
        this.title = '';
        document.body.style.overflow = '';
    },
});

window.Alpine = Alpine;
Alpine.start();
