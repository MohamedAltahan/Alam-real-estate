<?php

namespace App\Support;

use App\Models\Property;
use App\Models\Task;
use App\Models\User;

/** تسميات حقول المهمة وقيمها الثابتة — للفورم وسجل التعديلات */
final class TaskFields
{
    public const LABELS = [
        'title' => 'العنوان',
        'description' => 'الوصف',
        'status' => 'الحالة',
        'priority' => 'الأولوية',
        'due_date' => 'تاريخ الاستحقاق',
        'assignee_id' => 'المسند إليه',
        'property_id' => 'العقار',
        'file' => 'الملف',
    ];

    public const FOREIGN = [
        'assignee_id' => User::class,
        'property_id' => Property::class,
    ];

    public const ENUMS = [
        'status' => Task::STATUSES,
        'priority' => Task::PRIORITIES,
    ];

    public const AUDIT_ACTIONS = [
        'created' => 'إنشاء المهمة',
        'updated' => 'تعديل البيانات',
        'moved' => 'تغيير الحالة',
        'assigned' => 'إسناد المهمة',
        'comment_added' => 'إضافة تعليق',
        'attachment_added' => 'إضافة مرفق',
        'attachment_removed' => 'حذف مرفق',
        'deleted' => 'حذف المهمة',
    ];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    public static function enumLabel(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::ENUMS[$field][$value] ?? (string) $value;
    }

    public static function actionLabel(string $action): string
    {
        return self::AUDIT_ACTIONS[$action] ?? $action;
    }

    public static function isForeign(string $field): bool
    {
        return isset(self::FOREIGN[$field]);
    }
}
