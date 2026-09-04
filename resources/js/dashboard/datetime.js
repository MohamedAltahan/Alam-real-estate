/**
 * منتقي التاريخ والوقت (flatpickr) بالعربية:
 *  - x-datetime        → تاريخ + وقت (12 ساعة)، يكتب Y-m-d H:i في الحقل الأصلي
 *  - x-datetime.date   → تاريخ فقط (Y-m-d)
 *  - [data-datepicker] → حقول الفلاتر خارج Alpine (تاريخ فقط)
 *
 * altInput يعرض صيغة مقروءة للمستخدم بينما يبقى الحقل الأصلي (name) بالصيغة التي يفهمها السيرفر.
 * flatpickr يطلق حدثي input/change على الحقل الأصلي فيلتقطهما x-model والفلترة الحيّة.
 */
import flatpickr from 'flatpickr';
import { Arabic } from 'flatpickr/dist/l10n/ar.js';
import 'flatpickr/dist/flatpickr.min.css';
import { withAlpine } from './alpine';

function options(dateOnly, el) {
    return {
        locale: Arabic,
        enableTime: ! dateOnly,
        time_24hr: false,
        dateFormat: dateOnly ? 'Y-m-d' : 'Y-m-d H:i',
        altInput: true,
        altFormat: dateOnly ? 'j F Y' : 'j F Y — h:i K',
        allowInput: false,
        disableMobile: true,
        minuteIncrement: 15,
        defaultHour: 10,
        static: true,
        defaultDate: el.value || null,
        onReady(_, __, instance) {
            instance.altInput.setAttribute('dir', 'rtl');
            instance.altInput.classList.add('datetime-alt');
            if (el.placeholder) {
                instance.altInput.placeholder = el.placeholder;
            }
        },
    };
}

export function initDatePickers(root = document) {
    root.querySelectorAll('input[data-datepicker]').forEach((el) => {
        if (el._flatpickr) {
            return;
        }

        flatpickr(el, options(true, el));
    });
}

withAlpine((Alpine) => {
    Alpine.directive('datetime', (el, { modifiers }, { cleanup }) => {
        const instance = flatpickr(el, options(modifiers.includes('date'), el));

        cleanup(() => instance.destroy());
    });
});

initDatePickers();
document.addEventListener('live-filters:updated', (e) => initDatePickers(e.target ?? document));
