/**
 * رسوم لوحة التحكم — تُبنى من <canvas data-chart="..." data-payload="{...}">
 * مدخل Vite منفصل حتى لا تُحمَّل مكتبة الرسوم على الموقع العام.
 */
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Filler, Tooltip,
);

const GOLD  = '#c9a227';
const SLATE = '#5c6484';
const GREEN = '#4eae7f';
const GRID  = '#eceef4';
const TICK  = '#9aa0b0';

Chart.defaults.font.family = "'IBM Plex Sans Arabic', sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = TICK;

/** تدرّج رأسي من لون معيّن إلى شفاف — لتعبئة المخطط المساحي */
function verticalFade(ctx, color, alpha = 0.28) {
    const { chartArea, ctx: c } = ctx.chart;
    if (! chartArea) {
        return 'transparent';
    }

    const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    g.addColorStop(0, color + Math.round(alpha * 255).toString(16).padStart(2, '0'));
    g.addColorStop(1, color + '00');

    return g;
}

/** إعدادات مشتركة: بدون Legend، محور Y يبدأ من الصفر، شبكة فاتحة */
function baseOptions({ dash = false, verticalGrid = false, max = null, step = null } = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#111033',
                padding: 10,
                cornerRadius: 8,
                displayColors: false,
                titleFont: { weight: '700' },
                rtl: true,
                textDirection: 'rtl',
            },
        },
        scales: {
            x: {
                grid: { display: verticalGrid, color: GRID, drawTicks: false },
                border: { display: false },
                ticks: { padding: 8 },
            },
            y: {
                position: 'left',
                beginAtZero: true,
                max,
                grid: { color: GRID, borderDash: dash ? [3, 4] : undefined, drawTicks: false },
                border: { display: false },
                ticks: { padding: 8, stepSize: step, count: step ? undefined : 5, precision: 0 },
            },
        },
    };
}

const builders = {
    // الإيراد الشهري — خط ذهبي ناعم بتعبئة متدرّجة
    revenue: (payload) => ({
        type: 'line',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'الإيراد (ألف د.ك)',
                data: payload.data,
                borderColor: GOLD,
                borderWidth: 2.5,
                tension: 0.42,
                fill: true,
                backgroundColor: (ctx) => verticalFade(ctx, GOLD),
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: GOLD,
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
            }],
        },
        options: baseOptions({ verticalGrid: true }),
    }),

    // الـ Leads حسب الشهر — أعمدة ذهبية
    leads: (payload) => ({
        type: 'bar',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'عدد الطلبات',
                data: payload.data,
                backgroundColor: GOLD,
                hoverBackgroundColor: '#b89700',
                barThickness: 30,
                maxBarThickness: 34,
            }],
        },
        options: baseOptions(),
    }),

    // معدل التحويل — أعمدة رمادية (عدد الطلبات) + خط أخضر (نسبة الإغلاق %)
    conversion: (payload) => {
        const options = baseOptions({ dash: true, max: 100, step: 20 });

        // الأعمدة تُقاس بعدد الطلبات، والخط بالنسبة المئوية — لذلك محور مخفي منفصل
        options.scales.y1 = { display: false, beginAtZero: true, grid: { display: false } };
        options.plugins.tooltip.callbacks = {
            label: (ctx) => ctx.dataset.yAxisID === 'y1'
                ? `${ctx.dataset.label}: ${ctx.parsed.y}`
                : `${ctx.dataset.label}: ${ctx.parsed.y}%`,
        };

        return {
            type: 'bar',
            data: {
                labels: payload.labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'نسبة إغلاق الصفقات',
                        data: payload.line,
                        borderColor: GREEN,
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 3.5,
                        pointBackgroundColor: GREEN,
                        pointBorderWidth: 0,
                    },
                    {
                        label: 'عدد الطلبات',
                        yAxisID: 'y1',
                        data: payload.bars,
                        backgroundColor: SLATE,
                        barThickness: 18,
                        maxBarThickness: 22,
                    },
                ],
            },
            options,
        };
    },
};

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const builder = builders[canvas.dataset.chart];
    if (! builder) {
        return;
    }

    new Chart(canvas, builder(JSON.parse(canvas.dataset.payload)));
});

/* ==========================================================================
 | الفلترة الحيّة (بحث فوري + فلاتر)
 |
 | الفكرة: كل عناصر الفلترة في الصفحة تنتمي لنموذج واحد <form data-live-filters>
 | (العناصر البعيدة عنه في الـ DOM تنضمّ له بالخاصية form="..."). عند الكتابة
 | نطلب نفس الرابط بترويسة X-Fragment فيرجع محتوى الصفحة فقط، ثم نستبدل منطقة
 | [data-results] وحدها — فلا يفقد حقل البحث التركيز ولا موضع المؤشّر.
 |
 | الضوابط: تأخير 350ms بعد آخر حرف · إلغاء الطلب السابق (AbortController)
 | · تجاهل الردود المتأخّرة · تحديث شريط العنوان بالفلاتر الحالية.
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

    const buildUrl = () => {
        const params = new URLSearchParams();

        for (const [key, value] of new FormData(form).entries()) {
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

    // الكتابة: بعد توقّف المستخدم · القوائم المنسدلة: فوراً
    document.addEventListener('input', (e) => {
        if (! belongs(e.target) || e.target.matches('select')) {
            return;
        }
        clearTimeout(timer);
        timer = setTimeout(apply, DEBOUNCE_MS);
    });

    document.addEventListener('change', (e) => {
        if (! belongs(e.target) || ! e.target.matches('select')) {
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
}

document.querySelectorAll('form[data-live-filters]').forEach(initLiveFilters);
