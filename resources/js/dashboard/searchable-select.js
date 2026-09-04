/**
 * كل القوائم المنسدلة قابلة للبحث — طبقة واحدة فوق الـ <select> الأصلي.
 *
 * لا تعديل في أي Blade: نعترض فتح القائمة الأصلية ونعرض لوحة بحث واحدة
 * مشتركة تقرأ خيارات العنصر لحظة الفتح (فتعمل تلقائياً مع القوائم المبنيّة
 * بـ Alpine مثل المنطقة حسب المحافظة)، ثم نضبط قيمة الـ select ونطلق
 * input/change ليكمل كل شيء كالمعتاد (x-model · الفلاتر الحيّة · الإرسال).
 *
 * الاستثناء: data-no-search على أي قائمة نريدها أصلية.
 * حقل البحث يظهر عند 6 خيارات فأكثر (أو data-search-min="N").
 */

const MIN_FOR_SEARCH = 6;
const MIN_PANEL_WIDTH = 240;
const GAP = 6;

let panel = null;
let searchBox = null;
let searchInput = null;
let list = null;
let empty = null;

let select = null;      // القائمة المفتوحة حالياً
let options = [];       // كل خيارات القائمة
let matches = [];       // الخيارات المطابقة للبحث
let highlighted = 0;

/** تطبيع عربي: تشكيل وتطويل وهمزات وتاء مربوطة — ليطابق البحث ما يكتبه المستخدم */
function normalize(text) {
    return String(text)
        .toLowerCase()
        .replace(/[ً-ْـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/[ىئ]/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/\s+/g, ' ')
        .trim();
}

/** هل هذا العنصر قائمة منسدلة نتولّاها؟ */
function candidate(target) {
    const el = target instanceof Element ? target.closest('select') : null;

    if (! el || el.disabled || el.multiple || el.size > 1) {
        return null;
    }

    if (el.dataset.noSearch !== undefined || el.options.length < 2) {
        return null;
    }

    return el;
}

function isOpen() {
    return panel !== null && ! panel.hidden;
}

function build() {
    panel = document.createElement('div');
    panel.dir = 'rtl';
    panel.hidden = true;
    panel.className = 'fixed z-[90] rounded-2xl bg-white border border-gray-100 shadow-2xl p-2';
    panel.innerHTML = `
        <div data-search class="relative mb-2">
            <input type="text" autocomplete="off" spellcheck="false" placeholder="ابحث..."
                   class="w-full rounded-full bg-gray-50 border border-gray-200 ps-9 pe-3 py-2 text-sm text-ink focus:outline-none focus:border-primary-500 focus:bg-white">
            <svg class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <ul data-list class="max-h-64 overflow-y-auto space-y-0.5"></ul>
        <p data-empty hidden class="px-3 py-3 text-sm text-gray-400 text-center">لا توجد نتائج</p>
    `;

    searchBox = panel.querySelector('[data-search]');
    searchInput = panel.querySelector('input');
    list = panel.querySelector('[data-list]');
    empty = panel.querySelector('[data-empty]');

    searchInput.addEventListener('input', () => filter());
    searchInput.addEventListener('keydown', (event) => onKey(event));
    panel.addEventListener('pointerdown', (event) => event.stopPropagation());

    document.body.appendChild(panel);
}

function open(target) {
    if (! panel) {
        build();
    }

    select = target;
    options = Array.from(select.options).map((option, index) => ({
        index,
        label: option.textContent.trim() || '—',
        group: option.parentElement?.tagName === 'OPTGROUP' ? option.parentElement.label : null,
        disabled: option.disabled,
        selected: option.selected,
        haystack: normalize(option.textContent + ' ' + (option.parentElement?.label ?? '')),
    }));

    const min = Number(select.dataset.searchMin ?? MIN_FOR_SEARCH);
    searchBox.hidden = options.length < min;
    searchInput.value = '';

    panel.hidden = false;
    filter();

    if (searchBox.hidden) {
        select.focus({ preventScroll: true });
    } else {
        searchInput.focus({ preventScroll: true });
    }
}

function close() {
    if (! isOpen()) {
        return;
    }

    panel.hidden = true;
    select = null;
    matches = [];
}

function filter() {
    const term = normalize(searchInput.value);
    matches = term === '' ? options : options.filter((option) => option.haystack.includes(term));

    const active = matches.findIndex((option) => option.selected && ! option.disabled);
    highlighted = active >= 0 ? active : matches.findIndex((option) => ! option.disabled);

    render();
    position();
}

function render() {
    list.innerHTML = '';
    empty.hidden = matches.length > 0;

    let group = null;

    matches.forEach((option, index) => {
        if (option.group && option.group !== group) {
            group = option.group;
            const heading = document.createElement('li');
            heading.className = 'px-3 pt-2 pb-1 text-[11px] font-bold text-gray-400';
            heading.textContent = group;
            list.appendChild(heading);
        }

        const row = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.index = String(index);
        button.disabled = option.disabled;
        button.textContent = option.label;
        button.className = 'w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-start transition disabled:opacity-50 disabled:cursor-not-allowed '
            + (option.selected
                ? 'bg-primary-50 text-primary-800 font-semibold'
                : (index === highlighted ? 'bg-gray-50 text-gray-700' : 'text-gray-700 hover:bg-gray-50'));

        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => pick(option));
        button.addEventListener('mouseenter', () => {
            highlighted = index;
            paint();
        });

        row.appendChild(button);
        list.appendChild(row);
    });

    scrollToHighlighted();
}

/** تحديث تمييز السطر النشط بدون إعادة بناء القائمة */
function paint() {
    list.querySelectorAll('button[data-index]').forEach((button) => {
        const index = Number(button.dataset.index);
        const option = matches[index];

        if (option?.selected) {
            return;
        }

        button.classList.toggle('bg-gray-50', index === highlighted);
    });
}

function scrollToHighlighted() {
    const button = list.querySelector(`button[data-index="${highlighted}"]`);
    button?.scrollIntoView({ block: 'nearest' });
}

function move(step) {
    if (! matches.length) {
        return;
    }

    let next = highlighted;

    for (let i = 0; i < matches.length; i++) {
        next = (next + step + matches.length) % matches.length;

        if (! matches[next].disabled) {
            highlighted = next;
            break;
        }
    }

    paint();
    scrollToHighlighted();
}

function pick(option) {
    if (! select || option.disabled) {
        return;
    }

    const target = select;
    const changed = target.selectedIndex !== option.index;

    close();
    target.selectedIndex = option.index;
    target.focus({ preventScroll: true });

    if (changed) {
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

/** اللوحة fixed حتى لا تُقصّ داخل المودالات والجداول القابلة للتمرير */
function position() {
    if (! isOpen() || ! select) {
        return;
    }

    const box = select.getBoundingClientRect();
    const width = Math.max(box.width, MIN_PANEL_WIDTH);
    panel.style.width = `${width}px`;

    const left = Math.min(Math.max(8, box.right - width), window.innerWidth - width - 8);
    panel.style.left = `${left}px`;

    const height = panel.offsetHeight;
    const below = window.innerHeight - box.bottom;

    panel.style.top = below < height + GAP && box.top > height + GAP
        ? `${box.top - height - GAP}px`
        : `${box.bottom + GAP}px`;
}

function onKey(event) {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Enter':
            event.preventDefault();
            if (matches[highlighted]) {
                pick(matches[highlighted]);
            }
            break;
        case 'Escape':
            event.preventDefault();
            close();
            select?.focus({ preventScroll: true });
            break;
        case 'Tab':
            close();
            break;
    }
}

// ===== التفويض على مستوى الصفحة: يغطي أي قائمة تُضاف لاحقاً (Alpine · الفلاتر الحيّة) =====

document.addEventListener('pointerdown', (event) => {
    if (panel && panel.contains(event.target)) {
        return;
    }

    const target = candidate(event.target);

    if (! target) {
        close();

        return;
    }

    if (event.button > 0) {
        return;
    }

    event.preventDefault();   // يمنع القائمة الأصلية

    if (target === select) {
        close();
    } else {
        open(target);
    }
}, true);

document.addEventListener('keydown', (event) => {
    if (isOpen()) {
        // لوحة مفتوحة والتركيز على الـ select نفسه (قائمة بلا حقل بحث)
        if (event.target === select) {
            onKey(event);
        }

        return;
    }

    const target = candidate(event.target);

    if (! target || target !== document.activeElement) {
        return;
    }

    if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        open(target);
    }
}, true);

/** أثناء التمرير: نلاحق القائمة، وإن خرجت من الشاشة تماماً نغلق */
function follow() {
    if (! isOpen() || ! select) {
        return;
    }

    const box = select.getBoundingClientRect();
    const viewport = window.innerHeight || document.documentElement.clientHeight;

    if (viewport > 0 && (box.bottom < 0 || box.top > viewport)) {
        close();

        return;
    }

    position();
}

window.addEventListener('resize', follow);
window.addEventListener('scroll', follow, true);
document.addEventListener('focusin', (event) => {
    if (isOpen() && ! panel.contains(event.target) && event.target !== select) {
        close();
    }
});
