/**
 * رسوم لوحة التحكم — تُبنى من <canvas data-chart="..." data-payload="{...}">
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
                label: `الإيراد (ألف ${payload.currency ?? 'د.ك'})`,
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

    // معدل التحويل — أعمدة رمادية (عدد) + خط أخضر (نسبة %)
    conversion: (payload) => {
        const options = baseOptions({ dash: true, max: 100, step: 20 });

        // الأعمدة تُقاس بالعدد، والخط بالنسبة المئوية — لذلك محور مخفي منفصل
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
                        label: payload.lineLabel ?? 'نسبة إغلاق الصفقات',
                        data: payload.line,
                        borderColor: GREEN,
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 3.5,
                        pointBackgroundColor: GREEN,
                        pointBorderWidth: 0,
                    },
                    {
                        label: payload.barLabel ?? 'عدد الطلبات',
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

export function initCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const builder = builders[canvas.dataset.chart];
        if (! builder) {
            return;
        }

        Chart.getChart(canvas)?.destroy();
        new Chart(canvas, builder(JSON.parse(canvas.dataset.payload)));
    });
}

initCharts();

// إعادة بناء الرسوم داخل منطقة النتائج بعد الفلترة الحيّة
document.addEventListener('live-filters:updated', (e) => initCharts(e.target ?? document));
