/**
 * فورم العقار: القوائم المترابطة —
 * المحافظة → المنطقة، والتصنيف (سكني/تجاري) → أنواع الوحدات، وغرف النوم للسكني فقط.
 */
import { withAlpine } from './alpine';

withAlpine((Alpine) => {
    Alpine.data('propertyForm', (opts = {}) => ({
        purpose: opts.purpose || 'sale',
        category: String(opts.category ?? ''),
        unitType: String(opts.unitType ?? ''),
        city: String(opts.city ?? ''),
        area: String(opts.area ?? ''),
        categories: opts.categories ?? [],   // [{ id, key, name }]
        unitTypes: opts.unitTypes ?? [],     // [{ id, name, category }]
        areas: opts.areas ?? [],             // [{ id, name, city_id }]

        init() {
            // منطقة محفوظة بدون محافظة → نستنتج المحافظة
            if (this.area && ! this.city) {
                this.onAreaChange();
            }
        },

        get categoryKey() {
            return this.categories.find((c) => String(c.id) === this.category)?.key ?? '';
        },

        get isResidential() {
            return this.categoryKey !== 'commercial';
        },

        unitTypesFor() {
            const key = this.categoryKey;

            return key ? this.unitTypes.filter((t) => ! t.category || t.category === key) : this.unitTypes;
        },

        areasFor() {
            return this.city ? this.areas.filter((a) => String(a.city_id) === this.city) : this.areas;
        },

        onCategoryChange() {
            if (this.unitType && ! this.unitTypesFor().some((t) => String(t.id) === this.unitType)) {
                this.unitType = '';
            }
        },

        onCityChange() {
            if (this.area && ! this.areasFor().some((a) => String(a.id) === this.area)) {
                this.area = '';
            }
        },

        onAreaChange() {
            const area = this.areas.find((a) => String(a.id) === this.area);
            if (area && area.city_id && ! this.city) {
                this.city = String(area.city_id);
            }
        },
    }));
});
