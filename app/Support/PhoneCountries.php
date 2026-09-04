<?php

namespace App\Support;

/**
 * قائمة مفاتيح الدول (config/phone_countries.php) — مصدر واحد للداشبورد والموقع والتحقق.
 */
final class PhoneCountries
{
    public const DEFAULT = '+965';

    /** @var array<int, array{iso:string, code:string, ar:string, en:string}>|null */
    private static ?array $all = null;

    /** @var array<int, string>|null */
    private static ?array $codesByLength = null;

    /** @return array<int, array{iso:string, code:string, ar:string, en:string}> */
    public static function all(): array
    {
        return self::$all ??= array_values(config('phone_countries', []));
    }

    /** المفاتيح الفريدة بترتيب القائمة. @return array<int, string> */
    public static function codes(): array
    {
        return array_values(array_unique(array_column(self::all(), 'code')));
    }

    /** المفاتيح مرتبة من الأطول للأقصر — لمطابقة أطول مفتاح أولاً. @return array<int, string> */
    public static function codesByLength(): array
    {
        if (self::$codesByLength === null) {
            $codes = self::codes();
            usort($codes, fn (string $a, string $b) => strlen($b) <=> strlen($a));
            self::$codesByLength = $codes;
        }

        return self::$codesByLength;
    }

    /** أول دولة تحمل هذا المفتاح (الدولة الرئيسية تأتي أولاً في القائمة). */
    public static function find(?string $code): ?array
    {
        foreach (self::all() as $country) {
            if ($country['code'] === $code) {
                return $country;
            }
        }

        return null;
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && in_array($code, self::codes(), true);
    }
}
