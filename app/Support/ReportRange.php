<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * فترة التقارير: افتراضياً آخر 6 شهور، تُقلب الحدود المعكوسة، وتُقصّ الفترة الأطول من الحد
 * من تاريخ البداية (الرسم الشهري يبني عموداً لكل شهر).
 */
final class ReportRange
{
    public const MAX_MONTHS = 24;

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable, 2:bool} [from, to, shortened] */
    public static function resolve(array $filters, int $maxMonths = self::MAX_MONTHS): array
    {
        $now = CarbonImmutable::now();
        $from = filled($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'])->startOfDay() : $now->subMonths(5)->startOfMonth();
        $to = filled($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'])->endOfDay() : $now->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $shortened = $from->diffInMonths($to) > $maxMonths;

        if ($shortened) {
            $to = $from->addMonths($maxMonths)->endOfMonth();
        }

        return [$from, $to, $shortened];
    }
}
