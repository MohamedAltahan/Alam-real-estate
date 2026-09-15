<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\ActivitySubjects;
use App\Support\AuditDiff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * كتابة سجل النشاط العام. الموديلات المشمولة تُسجَّل تلقائياً عبر ActivityObserver،
 * والمستخدمون/الأدوار يدوياً من الكونترولر (تغيير الدور والصلاحيات لا يلتقطه observer).
 * حساب الفرق نفسه في AuditDiff (مشترك مع سجلي العملاء والمهام).
 */
class ActivityLogger
{
    private static bool $muted = false;

    /** تعطيل التسجيل مؤقتاً (استيراد · seeders · مهام صيانة) */
    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    /**
     * @param  array<string, array{old:mixed,new:mixed}>  $changes
     */
    public function record(string $event, Model $subject, array $changes = []): ?ActivityLog
    {
        $config = ActivitySubjects::for($subject);

        if (self::$muted || ! $config || ($event === 'updated' && ! $changes)) {
            return null;
        }

        return ActivityLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'module' => $config['module'],
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (int) $subject->getKey(),
            'subject_label' => Str::limit(ActivitySubjects::name($subject), 200, ''),
            'changes' => $changes ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function created(Model $subject, array $extra = []): ?ActivityLog
    {
        return $this->record('created', $subject, $this->snapshot($subject) + $extra);
    }

    /** يُستدعى بعد الحفظ (getChanges) أو قبله (getDirty) — كلاهما مقبول */
    public function updated(Model $subject, ?array $newValues = null, array $extra = []): ?ActivityLog
    {
        return $this->record('updated', $subject, $this->changes($subject, $newValues ?? $subject->getChanges()) + $extra);
    }

    public function deleted(Model $subject, array $extra = []): ?ActivityLog
    {
        return $this->record('deleted', $subject, $this->snapshot($subject, removed: true) + $extra);
    }

    /**
     * فرق القيم مع تجاهل الأعمدة المشتقة، والحقول المترجمة تُقارَن بنصّها العربي لا بالـ JSON الخام.
     *
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function changes(Model $subject, array $newValues): array
    {
        $config = ActivitySubjects::for($subject) ?? ['ignore' => []];
        $ignore = [...$config['ignore'], 'created_at', 'updated_at'];
        $translatable = method_exists($subject, 'getTranslatableAttributes') ? $subject->getTranslatableAttributes() : [];

        $changes = AuditDiff::changes($subject, Arr::except($newValues, $translatable), $ignore);

        foreach ($translatable as $field) {
            if (! array_key_exists($field, $newValues) || in_array($field, $ignore, true)) {
                continue;
            }

            $old = self::arabic($subject->getOriginal($field));
            $new = self::arabic($newValues[$field]);

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /** @return array<string, array{old:mixed,new:mixed}> */
    private function snapshot(Model $subject, bool $removed = false): array
    {
        $fields = ActivitySubjects::for($subject)['snapshot'] ?? [];

        return AuditDiff::snapshot($subject, $fields, $removed);
    }

    /** النص العربي من قيمة مترجمة (مصفوفة أو JSON) — أو النص كما هو */
    private static function arabic(mixed $value): ?string
    {
        if (is_string($value) && str_starts_with(ltrim($value), '{')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        if (is_array($value)) {
            $value = $value['ar'] ?? (reset($value) ?: null);
        }

        if (is_string($value)) {
            $value = str_replace("\r\n", "\n", $value);
        }

        return $value === null || $value === '' ? null : (string) $value;
    }
}
