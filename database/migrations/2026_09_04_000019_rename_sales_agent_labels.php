<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** «مسؤول العقار» أصبح «مندوب المبيعات» في البيانات المخزّنة (الأدوار، الوظائف، صفحة المندوب) */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('مسؤول العقار', 'مندوب المبيعات', 'مسؤول عقار', 'مندوب مبيعات', 'مسؤولة عقار', 'مندوبة مبيعات');
    }

    public function down(): void
    {
        $this->rename('مندوب المبيعات', 'مسؤول العقار', 'مندوب مبيعات', 'مسؤول عقار', 'مندوبة مبيعات', 'مسؤولة عقار');
    }

    private function rename(string $fromLabel, string $toLabel, string $fromRole, string $toRole, string $fromRoleF, string $toRoleF): void
    {
        DB::table('roles')->where('description', $fromRole)->update(['description' => $toRole]);

        if (Schema::hasColumn('users', 'job_title')) {
            DB::table('users')->where('job_title', $fromRole)->update(['job_title' => $toRole]);
            DB::table('users')->where('job_title', $fromRoleF)->update(['job_title' => $toRoleF]);
        }

        if (! Schema::hasTable('pages')) {
            return;
        }

        $page = DB::table('pages')->where('slug', 'agent')->first();
        if (! $page) {
            return;
        }

        $page = array_change_key_case((array) $page, CASE_LOWER);
        $update = [];

        foreach (['name', 'title'] as $column) {
            if (array_key_exists($column, $page) && is_string($page[$column]) && str_contains($page[$column], $fromLabel)) {
                $update[$column] = str_replace($fromLabel, $toLabel, $page[$column]);
            }
        }

        foreach (['seo_title', 'seo_description'] as $column) {
            if (! array_key_exists($column, $page) || ! is_string($page[$column])) {
                continue;
            }

            $decoded = json_decode($page[$column], true);
            if (is_array($decoded)) {
                $changed = array_map(fn ($v) => is_string($v) ? str_replace($fromLabel, $toLabel, $v) : $v, $decoded);
                if ($changed !== $decoded) {
                    $update[$column] = json_encode($changed, JSON_UNESCAPED_UNICODE);
                }
            } elseif (str_contains($page[$column], $fromLabel)) {
                $update[$column] = str_replace($fromLabel, $toLabel, $page[$column]);
            }
        }

        if ($update) {
            DB::table('pages')->where('id', $page['id'])->update($update);
        }
    }
};
