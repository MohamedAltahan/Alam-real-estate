<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Client;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * الوحدات المشمولة بسجل النشاط العام — المصدر الوحيد لتعريفها:
 * مفتاح الوحدة، تسمية السجل، الحقول المسجَّلة عند الإضافة/الحذف، الحقول المتجاهَلة عند التعديل،
 * وخرائط التسميات العربية والقيم المرجعية لعرض «الحقل: القديم ← الجديد».
 */
final class ActivitySubjects
{
    public const EVENTS = [
        'created' => 'إضافة',
        'updated' => 'تعديل',
        'deleted' => 'حذف',
    ];

    /** ألوان شارة العملية (Tailwind) */
    public const EVENT_TONES = [
        'created' => 'bg-success-soft text-success',
        'updated' => 'bg-info-soft text-info',
        'deleted' => 'bg-danger/10 text-danger',
    ];

    public const MODULES = [
        'properties' => 'العقارات',
        'property_owners' => 'ملاك العقارات',
        'clients' => 'العملاء',
        'viewings' => 'المعاينات',
        'tasks' => 'المهام',
        'supervisors' => 'المستخدمون',
        'roles' => 'الأدوار والصلاحيات',
    ];

    /** تسميات حقول المستخدم (لا يوجد خريطة مستقلة له) — كلمة السر تُسجَّل كحدث بلا قيمة */
    public const USER_LABELS = [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'civil_id' => 'الرقم المدني',
        'job_title' => 'المسمى الوظيفي',
        'status' => 'الحالة',
        'is_agent' => 'وكيل عقاري',
        'role' => 'الدور',
        'password' => 'كلمة المرور',
    ];

    public const USER_STATUSES = [
        'active' => 'نشط',
        'suspended' => 'موقوف',
    ];

    public const ROLE_LABELS = [
        'description' => 'اسم الدور',
        'status' => 'الحالة',
        'permissions_added' => 'صلاحيات أُضيفت',
        'permissions_removed' => 'صلاحيات أُزيلت',
    ];

    public const ROLE_STATUSES = [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ];

    public const OWNER_LABELS = [
        'name' => 'الاسم الكامل',
        'phone_code' => 'مفتاح الدولة',
        'phone' => 'رقم الهاتف',
        'email' => 'البريد الإلكتروني',
        'area_id' => 'المنطقة',
        'registered_address' => 'العنوان المسجّل',
        'notes' => 'الملاحظات',
    ];

    /**
     * @return array{module:string, label:string, snapshot:array<int,string>, ignore:array<int,string>, fields:array<string,string>, foreign:array<string,class-string>, enums:array<string,array>, booleans:array<int,string>}|null
     */
    public static function for(Model|string $subject): ?array
    {
        $class = is_string($subject) ? $subject : $subject::class;

        return match ($class) {
            Property::class => [
                'module' => 'properties', 'label' => 'عقار',
                'snapshot' => ['reference_code', 'title', 'purpose', 'price', 'price_period', 'status_id', 'unit_type_id', 'city_id', 'area_id', 'owner_id', 'agent_id', 'building_name'],
                // مشتقة من التقييمات/الحالة — ليست تعديلاً من المستخدم
                'ignore' => ['rating', 'reviews_count', 'sold_at'],
                'fields' => PropertyFields::LABELS, 'foreign' => PropertyFields::FOREIGN,
                'enums' => PropertyFields::ENUMS, 'booleans' => PropertyFields::BOOLEANS,
            ],
            PropertyOwner::class => [
                'module' => 'property_owners', 'label' => 'مالك',
                'snapshot' => ['name', 'phone_code', 'phone', 'email', 'area_id'],
                'ignore' => [],
                'fields' => self::OWNER_LABELS, 'foreign' => ['area_id' => Area::class], 'enums' => [], 'booleans' => [],
            ],
            Client::class => [
                'module' => 'clients', 'label' => 'عميل',
                'snapshot' => ['name', 'phone_code', 'phone', 'email', 'stage_id', 'agent_id', 'type_id', 'source_id', 'preferred_contact', 'nationality'],
                'ignore' => ['won_at'],
                'fields' => ClientFields::LABELS, 'foreign' => ClientFields::FOREIGN,
                'enums' => ClientFields::ENUMS, 'booleans' => ClientFields::BOOLEANS,
            ],
            ClientViewing::class => [
                'module' => 'viewings', 'label' => 'معاينة',
                'snapshot' => ['client_id', 'property_id', 'scheduled_at', 'in_person', 'outcome', 'notes'],
                // طوابع تذكير/إبلاغ يضبطها النظام
                'ignore' => ['outcome_at', 'reminded_at', 'owner_notified_at', 'client_followed_up_at'],
                'fields' => ['client_id' => 'العميل'] + ClientFields::LABELS,
                'foreign' => ['client_id' => Client::class] + ClientFields::FOREIGN,
                'enums' => ClientFields::ENUMS, 'booleans' => ClientFields::BOOLEANS,
            ],
            Task::class => [
                'module' => 'tasks', 'label' => 'مهمة',
                'snapshot' => ['title', 'status', 'priority', 'due_date', 'assignee_id', 'property_id'],
                'ignore' => ['position', 'completed_at'],
                'fields' => TaskFields::LABELS, 'foreign' => TaskFields::FOREIGN, 'enums' => TaskFields::ENUMS, 'booleans' => [],
            ],
            User::class => [
                'module' => 'supervisors', 'label' => 'مستخدم',
                'snapshot' => ['name', 'email', 'phone', 'job_title', 'status', 'is_agent'],
                'ignore' => ['password', 'remember_token', 'email_verified_at', 'bio', 'languages', 'response_time', 'preferences', 'rating', 'reviews_count'],
                'fields' => self::USER_LABELS, 'foreign' => [], 'enums' => ['status' => self::USER_STATUSES], 'booleans' => ['is_agent'],
            ],
            Role::class => [
                'module' => 'roles', 'label' => 'دور',
                'snapshot' => ['description', 'status'],
                'ignore' => ['name', 'guard_name'],
                'fields' => self::ROLE_LABELS, 'foreign' => [], 'enums' => ['status' => self::ROLE_STATUSES], 'booleans' => [],
            ],
            default => null,
        };
    }

    /** اسم السجل كما يُخزَّن وقت الحدث (يبقى بعد الحذف) */
    public static function name(Model $subject): string
    {
        return match ($subject::class) {
            Property::class => $subject->codeLabel(),
            ClientViewing::class => implode(' · ', array_filter([
                $subject->client?->name,
                $subject->property?->codeLabel(),
                $subject->scheduled_at?->format('Y-m-d H:i'),
            ])),
            Task::class => '#'.$subject->id.' — '.$subject->title,
            Role::class => (string) ($subject->description ?: $subject->name),
            default => (string) ($subject->name ?? ('#'.$subject->getKey())),
        };
    }

    /** رابط السجل في لوحة التحكم — null لو الوحدة بلا صفحة تفاصيل */
    public static function url(Model $subject): ?string
    {
        return match ($subject::class) {
            Property::class => route('dashboard.properties.show', $subject),
            PropertyOwner::class => route('dashboard.owners.show', $subject),
            Client::class => route('dashboard.clients.show', $subject),
            ClientViewing::class => $subject->client_id ? route('dashboard.clients.show', $subject->client_id) : null,
            Task::class => route('dashboard.tasks.show', $subject),
            User::class => route('dashboard.supervisors.index'),
            Role::class => route('dashboard.roles.index'),
            default => null,
        };
    }

    public static function eventLabel(string $event): string
    {
        return self::EVENTS[$event] ?? $event;
    }

    public static function eventTone(string $event): string
    {
        return self::EVENT_TONES[$event] ?? 'bg-gray-100 text-gray-500';
    }

    public static function moduleLabel(string $module): string
    {
        return self::MODULES[$module] ?? $module;
    }
}
