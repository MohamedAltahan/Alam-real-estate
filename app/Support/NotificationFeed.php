<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ContactRequest;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\User;
use App\Notifications\TaskEvent;
use App\Notifications\ViewingReminder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * قائمة الإشعارات في الشريط العلوي: إشعارات المستخدم المخزّنة (تذكيرات المعاينات)
 * + عناصر مجمّعة من السجلات الحقيقية (طلبات تواصل، عقارات، عملاء) — كل عنصر مربوط برابط يفتح مصدره.
 */
class NotificationFeed
{
    /** @return Collection<int, array> */
    public static function items(?User $user = null, int $limit = 7): Collection
    {
        if (! self::canView($user, 'notifications.view')) {
            return collect();
        }

        $soldId = PropertyStatus::where('key', 'sold')->value('id');

        // إشعارات المستخدم المخزّنة (تذكيرات المعاينات) — غير المقروءة أولاً
        $stored = $user
            ? $user->notifications()->latest()->take($limit)->get()
                ->map(fn (DatabaseNotification $n) => self::databaseItem($n))
                ->sortBy(fn (array $item) => $item['unread'] ? 0 : 1)
                ->values()
            : collect();

        $requests = self::canView($user, 'contact_requests.view') && self::enabled($user, 'contact_requests', true)
            ? ContactRequest::latest()->take($limit)->get()
                ->map(fn (ContactRequest $r) => [
                    'id' => null,
                    'kind' => 'request',
                    'title' => 'طلب تواصل جديد من '.$r->name,
                    'at' => $r->created_at,
                    'unread' => ! $r->is_read,
                    'overdue' => false,
                    'icon' => 'phone',
                    'tone' => 'accent',
                    'url' => route('dashboard.requests.index'),
                ])
            : collect();

        $properties = self::canView($user, 'properties.view')
            ? Property::with('status')->latest()->take($limit)->get()
                ->filter(fn (Property $p) => $p->status_id !== $soldId || self::enabled($user, 'closed_deals', true))
                ->map(fn (Property $p) => [
                    'id' => null,
                    'kind' => 'property',
                    'title' => $p->status_id === $soldId
                        ? 'تم إغلاق صفقة '.$p->title.' بنجاح'
                        : 'تم إضافة عقار جديد '.($p->reference_code ?: $p->title),
                    'at' => $p->status_id === $soldId ? $p->updated_at : $p->created_at,
                    'unread' => false,
                    'overdue' => false,
                    'icon' => $p->status_id === $soldId ? 'check' : 'building',
                    'tone' => $p->status_id === $soldId ? 'success' : 'info',
                    'url' => route('dashboard.properties.index'),
                ])
            : collect();

        $clients = self::canView($user, 'clients.view') && self::enabled($user, 'new_clients', true)
            ? Client::with('source')->latest()->take($limit)->get()
                ->map(fn (Client $c) => [
                    'id' => null,
                    'kind' => 'client',
                    'title' => 'عميل جديد: '.$c->name.($c->source ? ' عبر '.$c->source->name : ''),
                    'at' => $c->created_at,
                    'unread' => false,
                    'overdue' => false,
                    'icon' => 'users',
                    'tone' => 'info',
                    'url' => route('dashboard.clients.index'),
                ])
            : collect();

        $followUps = self::followUpSummary($user);

        $feed = $requests->concat($properties)->concat($clients)->concat($followUps)
            ->filter(fn ($i) => $i['at'] !== null)
            ->sortByDesc('at')
            ->values();

        // الإشعارات المخزّنة أولاً (غير المقروءة في المقدمة) ثم بقية العناصر بالأحدث
        return $stored->concat($feed)->take($limit)->values();
    }

    /** تحويل إشعار مخزّن (جدول notifications) إلى عنصر قائمة */
    public static function databaseItem(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;
        $isViewing = ($data['kind'] ?? null) === ViewingReminder::KIND;
        $isTask = ($data['kind'] ?? null) === TaskEvent::KIND;

        // تذكير المعاينة: النص يُعاد حسابه الآن من موعد المعاينة بدل النص المحفوظ وقت الإرسال
        $overdue = $isViewing && ViewingReminder::isOverdue($data);

        return [
            'id' => $notification->id,
            'kind' => $data['kind'] ?? 'general',
            'title' => $isViewing ? ViewingReminder::describe($data) : ($data['title'] ?? 'إشعار جديد'),
            'at' => $notification->created_at,
            'unread' => $notification->read_at === null,
            'overdue' => $overdue,
            'icon' => $isViewing ? 'calendar' : ($isTask ? 'check' : 'bell'),
            'tone' => $overdue ? 'danger' : ($isViewing ? 'accent' : 'primary'),
            'url' => route('dashboard.notifications.open', $notification->id),
        ];
    }

    /** عدد غير المقروء = طلبات التواصل التي لم تُفتح بعد + إشعارات المستخدم غير المقروءة */
    public static function unreadCount(?User $user = null): int
    {
        if (! self::canView($user, 'notifications.view')) {
            return 0;
        }

        $count = $user ? $user->unreadNotifications()->count() : 0;

        if (self::canView($user, 'contact_requests.view') && self::enabled($user, 'contact_requests', true)) {
            $count += ContactRequest::unread()->count();
        }

        return $count;
    }

    private static function followUpSummary(?User $user): Collection
    {
        if (! self::canView($user, 'clients.view') || ! self::enabled($user, 'follow_up_reminders', false)) {
            return collect();
        }

        $finalIds = ClientStage::where('is_final', true)->pluck('id');
        $query = Client::query()->when($finalIds->isNotEmpty(), fn ($q) => $q->where(
            fn ($q) => $q->whereNull('stage_id')->orWhereNotIn('stage_id', $finalIds)
        ));
        $count = $query->count();

        if ($count === 0) {
            return collect();
        }

        $latest = $query->latest('updated_at')->first(['updated_at']);

        return collect([[
            'id' => null,
            'kind' => 'follow_up',
            'title' => 'لديك '.$count.' متابعة عميل معلقة',
            'at' => $latest?->updated_at,
            'unread' => false,
            'overdue' => false,
            'icon' => 'users',
            'tone' => 'primary',
            'url' => route('dashboard.clients.index'),
        ]]);
    }

    private static function enabled(?User $user, string $key, bool $default): bool
    {
        return (bool) data_get($user?->preferences, 'notifications.'.$key, $default);
    }

    private static function canView(?User $user, string $ability): bool
    {
        return $user === null || $user->can($ability);
    }
}
