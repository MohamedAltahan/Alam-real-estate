<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * مصيدة السبام لنماذج الموقع العام.
 *
 * فخّان لا يراهما الزائر الحقيقي:
 *  1. حقل مخفي بصرياً — البوت يملأ كل حقول النموذج فيقع فيه.
 *  2. ختم وقت موقَّع — الإرسال خلال أقل من ثوانٍ قليلة من فتح الصفحة ليس بشرياً.
 *
 * الرسالة المعادة عامة عمداً حتى لا يتعلّم البوت أي فخ أوقعه.
 */
final class Honeypot
{
    /** اسم عادي يغري البوت بالملء */
    public const FIELD = 'website';

    /** ختم الوقت الموقَّع */
    public const TIME_FIELD = 'form_time';

    /** أقل زمن معقول لملء نموذج بشرياً */
    public const MIN_SECONDS = 3;

    /** أقصى عمر للنموذج قبل أن يُعتبر منتهياً (12 ساعة) */
    public const MAX_SECONDS = 43200;

    /** ختم الوقت الذي يوضع في النموذج */
    public static function stamp(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /** يرمي ValidationException لو بدا الإرسال آلياً */
    public static function assertHuman(Request $request, string $form): void
    {
        if (filled($request->input(self::FIELD))) {
            self::reject($request, $form, 'honeypot_filled');
        }

        $age = self::age($request->input(self::TIME_FIELD));

        if ($age === null) {
            self::reject($request, $form, 'missing_or_bad_stamp');
        }

        if ($age < self::MIN_SECONDS) {
            self::reject($request, $form, 'too_fast_'.$age.'s');
        }

        if ($age > self::MAX_SECONDS) {
            self::reject($request, $form, 'stale_form');
        }
    }

    /** عمر النموذج بالثواني، أو null لو الختم مفقود أو غير صالح */
    private static function age(mixed $stamp): ?int
    {
        if (! is_string($stamp) || $stamp === '') {
            return null;
        }

        try {
            $issued = (int) Crypt::decryptString($stamp);
        } catch (\Throwable) {
            return null;
        }

        return $issued > 0 ? now()->getTimestamp() - $issued : null;
    }

    private static function reject(Request $request, string $form, string $reason): never
    {
        Log::info('spam blocked', [
            'form' => $form,
            'reason' => $reason,
            'ip' => $request->ip(),
            'agent' => substr((string) $request->userAgent(), 0, 120),
        ]);

        throw ValidationException::withMessages([
            'name' => 'تعذّر إرسال النموذج. أعد تحميل الصفحة وحاول مرة أخرى.',
        ]);
    }
}
