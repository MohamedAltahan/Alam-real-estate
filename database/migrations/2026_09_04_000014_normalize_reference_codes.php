<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الرقم المرجعي للعقار أرقام فقط بدون بادئة ALM- ولا أصفار بادئة (ALM-001 → 1).
 * على مرحلتين حتى لا يصطدم القيد الفريد أثناء التحويل.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('properties')->select(['id', 'reference_code'])->orderBy('id')->get()
            ->map(fn ($row) => array_change_key_case((array) $row, CASE_LOWER));

        $targets = [];
        $used = [];
        $next = (int) $rows->max('id');

        foreach ($rows as $row) {
            $candidate = ltrim(preg_replace('/\D+/', '', (string) $row['reference_code']) ?? '', '0');

            if ($candidate !== '' && ! isset($used[$candidate])) {
                $targets[$row['id']] = $candidate;
                $used[$candidate] = true;
                $next = max($next, (int) $candidate);
            }
        }

        foreach ($rows as $row) {
            if (! isset($targets[$row['id']])) {
                do {
                    $next++;
                } while (isset($used[(string) $next]));

                $targets[$row['id']] = (string) $next;
                $used[(string) $next] = true;
            }
        }

        $changing = $rows->filter(fn ($row) => (string) $row['reference_code'] !== $targets[$row['id']]);

        // المرحلة الأولى: قيم مؤقتة فريدة لا تتعارض مع أي هدف
        foreach ($changing as $row) {
            DB::table('properties')->where('id', $row['id'])->update(['reference_code' => '#'.$row['id']]);
        }

        foreach ($changing as $row) {
            DB::table('properties')->where('id', $row['id'])->update(['reference_code' => $targets[$row['id']]]);
        }
    }

    public function down(): void
    {
        // لا رجوع: الصيغة القديمة غير قابلة للاستنتاج
    }
};
