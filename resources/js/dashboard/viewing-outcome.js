/**
 * نتيجة المعاينة داخل الجداول: تغييرها يُحفظ فوراً،
 * إلا «تم اختيار العقار» لعقار إيجار فتنتظر تاريخ انتهاء العقد ثم تُحفظ عند اختياره.
 *
 * الاستخدام: <form x-data="viewingOutcome({ outcome, ends, rent })"> — $root هو الفورم نفسه.
 */
import { withAlpine } from './alpine';

withAlpine((Alpine) => {
    Alpine.data('viewingOutcome', (opts = {}) => ({
        outcome: opts.outcome ?? 'pending',
        ends: opts.ends ?? '',
        rent: Boolean(opts.rent),

        get askDate() {
            return this.outcome === 'chosen' && this.rent;
        },

        onOutcomeChange() {
            if (this.askDate && ! this.ends) {
                return; // ننتظر اختيار تاريخ انتهاء العقد
            }

            this.submit();
        },

        onDateChange(event) {
            // منتقي التاريخ يطلق change على الحقل المخفي دون input، فلا يحدّث x-model — نقرأ القيمة من الحقل مباشرة
            this.ends = event?.target?.value ?? this.ends;

            if (this.askDate && this.ends) {
                this.submit();
            }
        },

        /** يُؤجَّل خطوة واحدة حتى ينتهي منتقي التاريخ من أحداثه الداخلية قبل إرسال الفورم */
        submit() {
            setTimeout(() => this.$root.requestSubmit(), 0);
        },
    }));
});
