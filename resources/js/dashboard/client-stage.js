/**
 * حالة الطلب في صفحة العميل: «ربح» يفتح ديالوج اختيار العقار من المعاينات،
 * «خسارة» يفتح تأكيد تحويل كل العقارات إلى «غير مهتم»، وأي حالة أخرى تُحفظ فوراً.
 * الإلغاء يعيد القائمة إلى الحالة المحفوظة.
 *
 * الاستخدام: <form x-data="clientStage({ current, won, lost, stages, properties })">
 */
import { withAlpine } from './alpine';

withAlpine((Alpine) => {
    Alpine.data('clientStage', (opts = {}) => ({
        current: String(opts.current ?? ''),
        selected: String(opts.current ?? ''),
        stages: opts.stages ?? [],           // [{ id, name, key, color }]
        properties: opts.properties ?? [],   // [{ id, reference, title, cover, meta }]
        wonProperty: '',
        dialog: null,                        // 'won' | 'lost' | null

        get stage() {
            return this.stages.find((s) => s.id === this.selected) ?? null;
        },

        get color() {
            return this.stage?.color ?? '#6B7280';
        },

        change() {
            const key = this.stage?.key;
            this.wonProperty = '';

            // بلا معاينات لا يوجد ما يُختار أو يُحوَّل — تُحفظ الحالة مباشرة
            if (this.properties.length && (key === opts.won || key === opts.lost)) {
                this.dialog = key === opts.won ? 'won' : 'lost';

                return;
            }

            this.submit();
        },

        cancel() {
            this.dialog = null;
            this.selected = this.current;
        },

        submit() {
            this.$nextTick(() => this.$refs.form.requestSubmit());
        },
    }));
});
