<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'oracle') {
    fwrite(STDERR, "The active database connection must be Oracle.\n");
    exit(1);
}

$output = $argv[1] ?? database_path('backups/azra_alam_mariadb_data.sql');
$output = str_starts_with($output, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $output)
    ? $output
    : base_path($output);

File::ensureDirectoryExists(dirname($output));

$excludedTables = [
    'cache',
    'cache_locks',
    'failed_jobs',
    'job_batches',
    'jobs',
    'migrations',
    'password_reset_tokens',
    'sessions',
];

$tables = collect(DB::select('SELECT LOWER(table_name) AS table_name FROM user_tables ORDER BY table_name'))
    ->pluck('table_name')
    ->map(fn (string $table) => strtolower($table))
    ->reject(fn (string $table) => in_array($table, $excludedTables, true))
    ->values();

$handle = fopen($output, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Unable to create export file: {$output}\n");
    exit(1);
}

$write = static function (string $sql) use ($handle): void {
    if (fwrite($handle, $sql) === false) {
        throw new RuntimeException('Failed while writing the MariaDB export.');
    }
};

$sqlValue = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }

    if (is_resource($value)) {
        $value = stream_get_contents($value);
    }

    if ($value instanceof DateTimeInterface) {
        $value = $value->format('Y-m-d H:i:s');
    } elseif (is_bool($value)) {
        return $value ? '1' : '0';
    } elseif (is_array($value) || is_object($value) && ! method_exists($value, '__toString')) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } elseif (is_object($value)) {
        $value = (string) $value;
    }

    $value = (string) $value;
    $escaped = strtr($value, [
        '\\' => '\\\\',
        "\0" => '\\0',
        "\n" => '\\n',
        "\r" => '\\r',
        "'" => "\\'",
        '"' => '\\"',
        "\x1a" => '\\Z',
    ]);

    return "'{$escaped}'";
};

$quoteIdentifier = static fn (string $identifier): string => '`'.str_replace('`', '``', $identifier).'`';
$rowCounts = [];

try {
    $write("-- Azra Alam: Oracle data export for MariaDB\n");
    $write('-- Generated: '.now()->toIso8601String()."\n");
    $write("-- Run Laravel migrations on the empty MariaDB database before importing this file.\n\n");
    $write("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
    $write("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
    $write("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n");
    $write("SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n");
    $write("START TRANSACTION;\n\n");

    foreach ($tables as $table) {
        $columns = collect(DB::select(
            'SELECT LOWER(column_name) AS column_name FROM user_tab_columns WHERE table_name = UPPER(?) ORDER BY column_id',
            [$table],
        ))->pluck('column_name')->map(fn (string $column) => strtolower($column))->values();

        if ($columns->isEmpty()) {
            continue;
        }

        $rows = DB::table($table)->get();
        $rowCounts[$table] = $rows->count();

        $write('-- '.$table.' ('.$rows->count()." rows)\n");
        $write('DELETE FROM '.$quoteIdentifier($table).";\n");

        foreach ($rows->chunk(100) as $chunk) {
            $columnSql = $columns->map($quoteIdentifier)->implode(', ');
            $valuesSql = $chunk->map(function (object $row) use ($columns, $sqlValue) {
                $data = array_change_key_case((array) $row, CASE_LOWER);

                return '('.$columns->map(fn (string $column) => $sqlValue($data[$column] ?? null))->implode(', ').')';
            })->implode(",\n");

            $write('INSERT INTO '.$quoteIdentifier($table).' ('.$columnSql.") VALUES\n{$valuesSql};\n");
        }

        $write("\n");
    }

    $write("COMMIT;\n");
    $write("SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;\n");
    $write("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;\n");
    $write("SET SQL_MODE=@OLD_SQL_MODE;\n");
} finally {
    fclose($handle);
}

echo "MariaDB data export created: {$output}\n";
echo 'Tables: '.count($rowCounts).PHP_EOL;
echo 'Rows: '.array_sum($rowCounts).PHP_EOL;
