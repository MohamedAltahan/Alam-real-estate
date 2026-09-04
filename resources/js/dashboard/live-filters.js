/* ==========================================================================
 | الفلترة الحيّة (بحث فوري + فلاتر)
 |
 | الفكرة: كل عناصر الفلترة في الصفحة تنتمي لنموذج واحد <form data-live-filters>
 | (العناصر البعيدة عنه في الـ DOM تنضمّ له بالخاصية form="..."). عند الكتابة
 | نطلب نفس الرابط بترويسة X-Fragment فيرجع محتوى الصفحة فقط، ثم نستبدل منطقة
 | [data-results] وحدها — فلا يفقد حقل البحث التركيز ولا موضع المؤشّر.
 |
 | الضوابط: تأخير 350ms بعد آخر حرف · إلغاء الطلب السابق (AbortController)
 | · تجاهل الردود المتأخّرة · تحديث شريط العنوان بالفلاتر الحالية
 | · حقل عليه data-min-length لا يُطبَّق قبل بلوغ هذا الطول (الفراغ يُطبَّق لمسح الفلتر).
 ========================================================================== */
const DEBOUNCE_MS = 350;

function initLiveFilters(form) {
    const results = document.querySelector('[data-results]');
    if (! results) {
        return;
    }

    let timer = null;
    let controller = null;
    let lastQuery = null;

    const tooShort = (el) => {
        const min = parseInt(el.dataset.minLength ?? '0', 10);
        const len = String(el.value ?? '').trim().length;

        return min > 0 && len > 0 && len < min;
    };

    const buildUrl = () => {
        const params = new URLSearchParams();

        for (const [key, value] of new FormData(form).entries()) {
            const field = form.elements[key];
            if (field && ! (field instanceof RadioNodeList) && tooShort(field)) {
                continue;
            }

            if (String(value).trim() !== '') {
                params.append(key, value);
            }
        }

        const query = params.toString();

        return { url: location.pathname + (query ? '?' + query : ''), query };
    };

    const apply = async () => {
        const { url, query } = buildUrl();

        // لا نعيد الطلب إذا لم تتغيّر الفلاتر فعلياً
        if (query === lastQuery) {
            return;
        }
        lastQuery = query;

        controller?.abort();
        controller = new AbortController();

        results.setAttribute('data-loading', '');

        try {
            const response = await fetch(url, {
                headers: { 'X-Fragment': '1', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });

            if (! response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.querySelector('[data-results]');

            if (fresh) {
                // عناصر الفلترة الواقعة داخل منطقة النتائج تُستبدَل معها،
                // فنعيد التركيز إلى نظيرها الجديد حتى لا يضيع مسار لوحة المفاتيح.
                const focused = results.contains(document.activeElement) ? document.activeElement.name : null;

                results.innerHTML = fresh.innerHTML;

                if (focused) {
                    results.querySelector(`[name="${focused}"]`)?.focus();
                }

                history.replaceState(null, '', url);
                results.dispatchEvent(new CustomEvent('live-filters:updated', { bubbles: true }));
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                // فشل الشبكة: نُبقي النتائج الحالية ونسمح بإعادة المحاولة
                lastQuery = null;
                console.error('live filters:', error);
            }
        } finally {
            results.removeAttribute('data-loading');
        }
    };

    // ملاحظة: العناصر المرتبطة بالنموذج عبر form="..." تقع خارجه في الـ DOM،
    // فأحداثها لا تصعد إليه — لذلك نستمع على المستند ونرشّح بـ target.form.
    const belongs = (el) => el && el.form === form;

    // الكتابة: بعد توقّف المستخدم · القوائم المنسدلة وحقول التاريخ: فوراً
    document.addEventListener('input', (e) => {
        if (! belongs(e.target) || e.target.matches('select, input[type="date"]')) {
            return;
        }
        clearTimeout(timer);
        if (tooShort(e.target)) {
            return;
        }
        timer = setTimeout(apply, DEBOUNCE_MS);
    });

    document.addEventListener('change', (e) => {
        if (! belongs(e.target) || ! e.target.matches('select, input[type="date"], input[data-datepicker]')) {
            return;
        }
        clearTimeout(timer);
        apply();
    });

    // Enter داخل حقل البحث يطبّق فوراً بدل إعادة تحميل الصفحة
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(timer);
        apply();
    });

    // أزرار الفلترة داخل منطقة النتائج (مثل أزرار المراحل) — تفويض الأحداث
    results.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-filter-set]');
        if (! trigger) {
            return;
        }

        e.preventDefault();
        const field = form.elements[trigger.dataset.filterSet];
        if (field) {
            field.value = trigger.dataset.filterValue ?? '';
            apply();
        }
    });

    // مسح كل الفلاتر من زر خارج النموذج
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-filters-reset]');
        if (! trigger || trigger.dataset.filtersReset !== form.id) {
            return;
        }

        e.preventDefault();
        for (const el of form.elements) {
            if (el.name && el.type !== 'hidden') {
                el.value = '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
            } else if (el.name) {
                el.value = '';
            }
        }
        clearTimeout(timer);
        apply();
    });
}

document.querySelectorAll('form[data-live-filters]').forEach(initLiveFilters);
