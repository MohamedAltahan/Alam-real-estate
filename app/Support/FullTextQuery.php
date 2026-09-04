<?php

namespace App\Support;

/**
 * بناء تعبير MATCH ... AGAINST بالوضع البولياني (MariaDB/MySQL) من نص بحث حر:
 * كل كلمة مطلوبة (+) وتطابق بداية الكلمة (*). تُحذف رموز التحكم والكلمات القصيرة.
 */
final class FullTextQuery
{
    public static function boolean(string $term): string
    {
        $tokens = preg_split('/\s+/u', trim($term)) ?: [];

        $parts = [];
        foreach ($tokens as $token) {
            $token = preg_replace('/[+\-<>()~*"@]/u', '', $token) ?? '';

            if (mb_strlen($token) < 2) {
                continue;
            }

            $parts[] = '+'.$token.'*';
        }

        return implode(' ', $parts);
    }
}
