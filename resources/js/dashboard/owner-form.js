/**
 * شاشة ملّاك العقارات: فورم الإضافة/التعديل (أرقام تواصل متعددة + ملفات)،
 * مودال الحذف، ومودال عقارات المالك.
 *
 * الاستخدام: <div x-data="ownerForm({ storeUrl, updateBase, reopen })">
 * reopen: بيانات old() بعد فشل التحقق لإعادة فتح المودال بنفس القيم.
 */
import { withAlpine } from './alpine';
import { rowRepeater } from './repeater';

const BLANK_CONTACT = { id: '', phone_code: '+965', phone: '', role: '', name: '' };
const BLANK_FORM = { name: '', email: '', area_id: '', registered_address: '', notes: '' };

withAlpine((Alpine) => {
    Alpine.data('ownerForm', (opts = {}) => ({
        ...rowRepeater({ prefix: 'contacts', blank: BLANK_CONTACT, errors: opts.errors ?? {} }),

        countries: opts.countries ?? [],
        filtersOpen: Boolean(opts.filtersOpen),
        mode: 'add',
        action: '',
        ownerId: null,
        form: { ...BLANK_FORM },
        files: [],
        delAction: '',
        delName: '',
        propertiesOwner: '',
        ownerProperties: [],

        init() {
            const reopen = opts.reopen;
            if (! reopen) {
                return;
            }

            // فشل التحقق: نعيد فتح المودال بالقيم المُدخلة
            this.mode = reopen.mode ?? 'add';
            this.ownerId = reopen.id ?? null;
            this.action = this.mode === 'edit' ? `${opts.updateBase}/${reopen.id}` : opts.storeUrl;
            this.form = { ...BLANK_FORM, ...(reopen.form ?? {}) };
            this.setRows(reopen.contacts ?? []);
            this.files = reopen.files ?? [];
            this.$nextTick(() => {
                this.$dispatch('owner-files-reset', this.files);
                this.$dispatch('open-modal', 'owner-form');
            });
        },

        setRows(rows) {
            this.rows = [];
            (rows.length ? rows : [{}]).forEach((row) => {
                this.rows.push({ ...BLANK_CONTACT, ...row, _key: 'c' + Math.random().toString(36).slice(2) });
            });
        },

        startAdd() {
            this.mode = 'add';
            this.ownerId = null;
            this.action = opts.storeUrl;
            this.form = { ...BLANK_FORM };
            this.errors = {};
            this.setRows([]);
            this.files = [];
            this.$dispatch('owner-files-reset', []);
            this.$dispatch('open-modal', 'owner-form');
        },

        startEdit(owner) {
            this.mode = 'edit';
            this.ownerId = owner.id;
            this.action = `${opts.updateBase}/${owner.id}`;
            this.form = {
                name: owner.name ?? '',
                email: owner.email ?? '',
                area_id: owner.area_id ? String(owner.area_id) : '',
                registered_address: owner.registered_address ?? '',
                notes: owner.notes ?? '',
            };
            this.errors = {};
            this.setRows((owner.contacts ?? []).map((c) => ({
                id: c.id ?? '',
                phone_code: c.phone_code || '+965',
                phone: c.phone ?? '',
                role: c.role ?? '',
                name: c.name ?? '',
            })));
            this.files = owner.files ?? [];
            this.$dispatch('owner-files-reset', this.files);
            this.$dispatch('open-modal', 'owner-form');
        },

        /** السطر الأخير لا يُحذف — رقم واحد على الأقل */
        removeContact(index) {
            if (this.rows.length > 1) {
                this.remove(index);
            }
        },

        startDelete(action, name) {
            this.delAction = action;
            this.delName = name;
            this.$dispatch('open-modal', 'owner-delete');
        },

        openProperties(name, properties) {
            this.propertiesOwner = name;
            this.ownerProperties = properties;
            this.$dispatch('open-modal', 'owner-properties');
        },
    }));
});
