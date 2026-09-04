<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * أسماء الشهور بالعربي وتقسيم فترات زمنية إلى شهور — للرسوم والتقارير.
 * التجميع يتم في PHP حتى لا نعتمد على دوال تواريخ خاصة بمحرك قاعدة بيانات بعينه.
 */
final class Months
{
    /** أسماء الشهور بالعربي (index 1..12) */
    public const NAMES = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    public static function name(int $month): string
    {
        return self::NAMES[$month] ?? (string) $month;
    }

    /**
     * آخر N شهر مع حدود كل شهر واسمه بالعربي.
     *
     * @return Collection<int, array{label:string, key:string, from:CarbonImmutable, to:CarbonImmutable}>
     */
    public static function last(CarbonImmutable $now, int $count): Collection
    {
        return collect(range($count - 1, 0))->map(function ($back) use ($now) {
            $from = $now->startOfMonth()->subMonths($back);

            return self::bucket($from);
        })->values();
    }

    /**
     * كل الشهور بين تاريخين (شاملة).
     *
     * @return Collection<int, array{label:string, key:string, from:CarbonImmutable, to:CarbonImmutable}>
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();
        $months = collect();

        while ($cursor->lte($end)) {
            $months->push(self::bucket($cursor));
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    /** @return array{label:string, key:string, from:CarbonImmutable, to:CarbonImmutable} */
    private static function bucket(CarbonImmutable $from): array
    {
        return [
            'label' => self::name((int) $from->format('n')).' '.$from->format('Y'),
            'key' => $from->format('Y-m'),
            'from' => $from,
            'to' => $from->addMonth(),
        ];
    }

    /** الأربعاء، ٢٢ يوليو ٢٠٢٦ */
    public static function arabicDate(CarbonImmutable $date): string
    {
        $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

        return $days[(int) $date->format('w')].'، '
            .$date->format('j').' '.self::name((int) $date->format('n')).' '.$date->format('Y');
    }
}
