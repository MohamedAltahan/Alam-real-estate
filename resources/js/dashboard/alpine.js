/**
 * تسجيل مكوّنات/توجيهات Alpine من وحدات لوحة التحكم.
 *
 * app.js يؤجّل Alpine.start() إلى DOMContentLoaded، ووحدات dashboard.js تُنفَّذ قبله
 * (سكربتات module مؤجّلة بترتيب المستند) — لذلك window.Alpine متاح هنا ولم يبدأ بعد.
 * نسجّل مباشرة إن وُجد، وإلا ننتظر حدث alpine:init.
 */
export function withAlpine(callback) {
    if (window.Alpine) {
        callback(window.Alpine);

        return;
    }

    document.addEventListener('alpine:init', () => callback(window.Alpine));
}
