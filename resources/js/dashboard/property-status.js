/**
 * تغيير حالة العقار من عمود «الحالة» في جدول العقارات دون الدخول إلى التفاصيل:
 * <select data-property-status="{url}"> → PATCH JSON ثم تحديث النتائج الحيّة.
 */
import { toast } from './whatsapp';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

// تفويض: الجدول يُستبدل بالفلاتر الحيّة
document.addEventListener('change', async (event) => {
    const select = event.target.closest('select[data-property-status]');

    if (! select) {
        return;
    }

    const url = select.dataset.propertyStatus;
    select.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status_id: select.value }),
        });

        const data = await response.json().catch(() => ({}));

        if (! response.ok) {
            throw new Error(data.message || ('HTTP ' + response.status));
        }

        // إعادة تلوين القائمة فوراً بلون الحالة الجديدة
        if (data.status?.color) {
            select.style.color = data.status.color;
            select.style.backgroundColor = data.status.color + '1a';
        }

        toast('تم تغيير حالة العقار إلى «' + (data.status?.name ?? '') + '»', 'bg-success');
        window.dispatchEvent(new CustomEvent('live-filters:refresh'));
    } catch (error) {
        toast(error.message || 'تعذّر تغيير الحالة', 'bg-danger');
        window.dispatchEvent(new CustomEvent('live-filters:refresh'));
    } finally {
        select.disabled = false;
    }
});
