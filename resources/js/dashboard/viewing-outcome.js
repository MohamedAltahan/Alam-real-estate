/**
 * نتيجة المعاينة داخل الجداول: تغييرها يُحفظ فوراً.
 *
 * الاستخدام: <form x-data="viewingOutcome({ outcome })"> — $root هو الفورم نفسه.
 */
import { withAlpine } from './alpine';

withAlpine((Alpine) => {
    Alpine.data('viewingOutcome', (opts = {}) => ({
        outcome: opts.outcome ?? 'pending',

        /** يُؤجَّل خطوة واحدة حتى يحدّث Alpine القيمة قبل إرسال الفورم */
        submit() {
            setTimeout(() => this.$root.requestSubmit(), 0);
        },
    }));
});
