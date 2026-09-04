<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * حساب فرق القيم لسجلات التعديلات (العملاء · المهام) — منطق واحد مشترك
 * بصيغة {field: {old, new}} مع توحيد التواريخ والأرقام والقيم المنطقية.
 */
final class AuditDiff
{
    /**
     * فرق القيم الجديدة عن الأصلية — يقبل getDirty() قبل الحفظ أو getChanges() بعده
     * (داخل حدث updated تكون getOriginal() ما زالت تحمل القيم القديمة).
     *
     * @param  array<string, mixed>  $newValues
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public static function changes(Model $model, array $newValues, array $ignore = ['created_at', 'updated_at']): array
    {
        $changes = [];

        foreach ($newValues as $field => $value) {
            if (in_array($field, $ignore, true)) {
                continue;
            }

            $old = self::scalar($model->getOriginal($field));
            $new = self::scalar($value);

            if (self::same($old, $new)) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    /**
     * لقطة من قيم السجل — للإضافة (new فقط) أو الحذف (old فقط).
     *
     * @param  array<int, string>  $fields
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public static function snapshot(Model $model, array $fields, bool $removed = false): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $value = self::scalar($model->getAttribute($field));

            if ($value === null || $value === '') {
                continue;
            }

            $snapshot[$field] = $removed
                ? ['old' => $value, 'new' => null]
                : ['old' => null, 'new' => $value];
        }

        return $snapshot;
    }

    /** توحيد القيم للمقارنة والتخزين (تواريخ بصيغة واحدة، أرقام كنصوص) */
    public static function scalar(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i');
            } catch (\Throwable) {
                return $value;
            }
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value === '' ? null : $value;
    }

    private static function same(mixed $old, mixed $new): bool
    {
        if (is_bool($old) || is_bool($new)) {
            return self::toBool($old) === self::toBool($new);
        }

        return (string) ($old ?? '') === (string) ($new ?? '');
    }

    private static function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
