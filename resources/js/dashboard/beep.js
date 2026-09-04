/**
 * صوت التنبيه «بيب بيب» بـ Web Audio بدون ملف صوتي.
 *
 * المتصفح يمنع الصوت قبل أول تفاعل من المستخدم — نفتح AudioContext عند أول نقرة/ضغطة،
 * ولو طُلب التنبيه قبل ذلك نؤجّله ونشغّله فور فتح السياق.
 */
let context = null;
let pending = false;

function ensureContext() {
    if (context) {
        return context;
    }

    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (! AudioCtx) {
        return null;
    }

    context = new AudioCtx();

    return context;
}

function play() {
    const ctx = ensureContext();
    if (! ctx || ctx.state !== 'running') {
        return;
    }

    const start = ctx.currentTime + 0.02;

    [0, 0.24].forEach((offset) => {
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;

        gain.gain.setValueAtTime(0.0001, start + offset);
        gain.gain.exponentialRampToValueAtTime(0.28, start + offset + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.16);

        oscillator.connect(gain).connect(ctx.destination);
        oscillator.start(start + offset);
        oscillator.stop(start + offset + 0.18);
    });
}

function unlock() {
    const ctx = ensureContext();
    if (! ctx) {
        return;
    }

    ctx.resume().then(() => {
        if (pending) {
            pending = false;
            play();
        }
    }).catch(() => {});
}

['pointerdown', 'keydown', 'touchstart'].forEach((event) => {
    document.addEventListener(event, unlock, { once: true, passive: true });
});

/** يشغّل التنبيه الآن، أو يؤجّله حتى أول تفاعل لو الصوت ما زال مقفولاً */
export function beep() {
    const ctx = ensureContext();
    if (! ctx) {
        return;
    }

    if (ctx.state !== 'running') {
        pending = true;
        ctx.resume().then(() => {
            if (ctx.state === 'running' && pending) {
                pending = false;
                play();
            }
        }).catch(() => {});

        return;
    }

    play();
}
