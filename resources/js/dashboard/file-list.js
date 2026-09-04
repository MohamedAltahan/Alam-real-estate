/**
 * قائمة ملفات مرفقة (صور · PDF · Word · Excel) داخل الفورم:
 * - تعرض الملفات المحفوظة مع زر حذف (يُرسل files_removed[]).
 * - تختار ملفات جديدة بتحقق من النوع والحجم قبل الإرسال.
 * - لا ضغط ولا معاينة صور — ملفات كما هي.
 *
 * الاستخدام: <div x-data="fileList({ existing, accept, maxMb })">
 */
import { withAlpine } from './alpine';

const KIND_BY_EXT = {
    jpg: 'image', jpeg: 'image', png: 'image', webp: 'image', gif: 'image', svg: 'image',
    pdf: 'pdf',
    doc: 'word', docx: 'word',
    xls: 'excel', xlsx: 'excel', csv: 'excel',
};

export function extensionOf(name) {
    const match = /\.([a-z0-9]+)$/i.exec(name || '');

    return match ? match[1].toLowerCase() : '';
}

export function kindOf(ext) {
    return KIND_BY_EXT[ext] ?? 'file';
}

export function formatSize(bytes) {
    if (! bytes) {
        return '';
    }

    return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

withAlpine((Alpine) => {
    Alpine.data('fileList', (opts = {}) => ({
        existing: opts.existing ?? [],       // [{ id, name, size, url, ext }]
        removed: [],                         // معرّفات الملفات المطلوب حذفها
        pending: [],                         // [{ name, size, ext, file }]
        allowed: (opts.accept ?? 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx').split(',').map((e) => e.trim().replace(/^\./, '').toLowerCase()).filter(Boolean),
        maxBytes: (opts.maxMb ?? 15) * 1024 * 1024,
        error: '',

        get visibleExisting() {
            return this.existing.filter((f) => ! this.removed.includes(f.id));
        },

        get isEmpty() {
            return this.visibleExisting.length === 0 && this.pending.length === 0;
        },

        /** إعادة الضبط عند فتح الفورم لمالك آخر */
        reset(files = []) {
            this.existing = Array.isArray(files) ? files : [];
            this.removed = [];
            this.pending = [];
            this.error = '';
            this.syncInput();
        },

        pick(event) {
            this.add(event.target.files);
        },

        add(fileList) {
            this.error = '';

            for (const file of Array.from(fileList ?? [])) {
                const ext = extensionOf(file.name);

                if (! this.allowed.includes(ext)) {
                    this.error = `«${file.name}» نوع غير مسموح. الملفات المسموحة: ${this.allowed.join(' · ')}`;
                    continue;
                }

                if (file.size > this.maxBytes) {
                    this.error = `«${file.name}» أكبر من ${Math.round(this.maxBytes / 1048576)} ميجابايت.`;
                    continue;
                }

                this.pending.push({ name: file.name, size: file.size, ext, file });
            }

            this.syncInput();
        },

        dropPending(index) {
            this.pending.splice(index, 1);
            this.syncInput();
        },

        removeExisting(id) {
            if (! this.removed.includes(id)) {
                this.removed.push(id);
            }
        },

        restoreExisting(id) {
            this.removed = this.removed.filter((r) => r !== id);
        },

        kindOf,
        formatSize,

        /** حقن الملفات المختارة في حقل الرفع الحقيقي ليُرسَل مع الفورم */
        syncInput() {
            const input = this.$refs.input;
            if (! input) {
                return;
            }

            const transfer = new DataTransfer();
            this.pending.forEach((p) => transfer.items.add(p.file));
            input.files = transfer.files;
        },
    }));
});
