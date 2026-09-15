<?php

namespace App\Support;

/**
 * تفكيك رقم الهاتف إلى مفتاح دولة + رقم محلي، وتنسيقه للعرض وواتساب.
 *
 * يقبل الصيغ القديمة كلها: "+96555112233" · "+966 501234567" · "0096555112233"
 * · "96555112233" · "55112233" · "055112233".
 */
final class PhoneNumber
{
    /** علامة اتجاه من اليسار لليمين — تمنع انعكاس مفتاح الدولة مع الرقم داخل نص عربي (رسائل واتساب) */
    public const LRM = "\u{200E}";

    /** @return array{code:string, national:string} */
    public static function split(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return ['code' => PhoneCountries::DEFAULT, 'national' => ''];
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($international) {
            foreach (PhoneCountries::codesByLength() as $code) {
                $codeDigits = ltrim($code, '+');

                if (str_starts_with($digits, $codeDigits) && strlen($digits) > strlen($codeDigits)) {
                    return ['code' => $code, 'national' => ltrim(substr($digits, strlen($codeDigits)), '0')];
                }
            }

            return ['code' => PhoneCountries::DEFAULT, 'national' => ltrim($digits, '0')];
        }

        // رقم كويتي كُتب بالمفتاح بدون + (965XXXXXXXX)
        if (strlen($digits) === 11 && str_starts_with($digits, '965')) {
            return ['code' => '+965', 'national' => substr($digits, 3)];
        }

        return ['code' => PhoneCountries::DEFAULT, 'national' => ltrim($digits, '0')];
    }

    /** "+965 55112233" */
    public static function format(?string $code, ?string $national): string
    {
        $national = trim((string) $national);

        if ($national === '') {
            return '';
        }

        return trim(($code ?: PhoneCountries::DEFAULT).' '.$national);
    }

    /** أرقام فقط للاستخدام في روابط wa.me — "96555112233" */
    public static function digits(?string $code, ?string $national): string
    {
        return preg_replace('/\D+/', '', ($code ?: PhoneCountries::DEFAULT).(string) $national) ?? '';
    }

    /**
     * للرسائل النصية: المفتاح ملتصق بالرقم بلا مسافة مع علامة LRM حتى لا ينعكس في النص العربي،
     * وآخر رقمين مخفيان — "‎+965551122xx".
     */
    public static function masked(?string $code, ?string $national): string
    {
        $digits = trim((string) $national) === '' ? '' : self::digits($code, $national);

        if ($digits === '') {
            return '';
        }

        return self::LRM.'+'.(strlen($digits) > 2 ? substr($digits, 0, -2).'xx' : 'xx');
    }

    /** رقم كما هو (بلا إخفاء) بلا مسافات ومع علامة LRM — لهاتف المندوب داخل الرسائل */
    public static function ltr(?string $raw): string
    {
        $raw = preg_replace('/\s+/', '', (string) $raw) ?? '';

        return $raw === '' ? '' : self::LRM.$raw;
    }
}
