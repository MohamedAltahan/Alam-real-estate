/**
 * أسطر متعددة في الفورم (احتياجات العقار · المعاينات): إضافة/حذف سطر،
 * أسماء الحقول rows[i][field]، ورسائل الخطأ لكل سطر من التحقق على السيرفر.
 */
import { withAlpine } from './alpine';

let counter = 0;
const key = () => 'r' + (++counter) + '-' + Date.now().toString(36);

/** الأساس المشترك — يُستخدم مباشرة للمعاينات ويُوسَّع للاحتياجات */
export function rowRepeater(opts = {}) {
    const blank = opts.blank ?? {};

    return {
        prefix: opts.prefix ?? 'rows',
        blank,
        errors: opts.errors ?? {},
        rows: (opts.rows ?? []).map((row) => ({ ...blank, ...row, _key: key() })),

        add() {
            this.rows.push({ ...this.blank, _key: key() });
        },

        remove(index) {
            this.rows.splice(index, 1);
        },

        name(index, field) {
            return `${this.prefix}[${index}][${field}]`;
        },

        errorFor(index, field) {
            return this.errors[`${this.prefix}.${index}.${field}`] ?? null;
        },

        hasErrors(index) {
            const start = `${this.prefix}.${index}.`;

            return Object.keys(this.errors).some((k) => k.startsWith(start));
        },
    };
}

withAlpine((Alpine) => {
    Alpine.data('rowRepeater', rowRepeater);

    /** احتياجات العقار: نوع الوحدة يتبع نوع العقار (سكني/تجاري)، والمنطقة تعتمد على المحافظة المختارة */
    Alpine.data('clientNeeds', (opts = {}) => ({
        ...rowRepeater({ prefix: 'needs', blank: { id: '', category: '', unit_type_id: '', city_id: '', area_id: '', area_size: '', rooms: '' }, ...opts }),
        cities: opts.cities ?? [],
        areas: opts.areas ?? [],
        unitTypes: opts.unitTypes ?? [],

        unitTypesFor(row) {
            if (! row.category) {
                return this.unitTypes;
            }

            return this.unitTypes.filter((t) => t.category === row.category);
        },

        onCategoryChange(row) {
            if (row.unit_type_id && ! this.unitTypesFor(row).some((t) => String(t.id) === String(row.unit_type_id))) {
                row.unit_type_id = '';
            }
        },

        // اختيار نوع وحدة بدون نوع عقار يضبط نوع العقار تلقائياً
        onUnitTypeChange(row) {
            const type = this.unitTypes.find((t) => String(t.id) === String(row.unit_type_id));
            if (type && type.category && ! row.category) {
                row.category = type.category;
            }
        },

        areasFor(row) {
            if (! row.city_id) {
                return this.areas;
            }

            return this.areas.filter((a) => String(a.city_id) === String(row.city_id));
        },

        onCityChange(row) {
            if (row.area_id && ! this.areasFor(row).some((a) => String(a.id) === String(row.area_id))) {
                row.area_id = '';
            }
        },

        // اختيار منطقة بدون مدينة يضبط مدينتها تلقائياً
        onAreaChange(row) {
            const area = this.areas.find((a) => String(a.id) === String(row.area_id));
            if (area && area.city_id && ! row.city_id) {
                row.city_id = String(area.city_id);
            }
        },
    }));

    Alpine.data('clientViewings', (opts = {}) => ({
        ...rowRepeater({
            prefix: 'viewings',
            blank: { id: '', property_id: '', property_label: '', property_purpose: '', scheduled_at: '', in_person: '1', outcome: 'pending', notes: '' },
            ...opts,
        }),
        lookupUrl: opts.lookupUrl ?? '',
    }));
});
