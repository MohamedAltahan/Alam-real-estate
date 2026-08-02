<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ContactRequest;
use App\Models\Property;
use App\Models\PropertyStatus;
use Illuminate\Support\Collection;

/**
 * تجميع "الإشعارات" من السجلات الحقيقية (طلبات تواصل، عقارات، عملاء)
 * بدل جدول إشعارات منفصل — كل عنصر مربوط برابط يفتح مصدره.
 */
class NotificationFeed
{
    /** @return Collection<int, array> */
    public static function items(int $limit = 7): Collection
    {
        $soldId = PropertyStatus::where('key', 'sold')->value('id');

        $requests = ContactRequest::latest()->take($limit)->get()
            ->map(fn (ContactRequest $r) => [
                'title' => 'طلب تواصل جديد من '.$r->name,
                'at' => $r->created_at,
                'unread' => ! $r->is_read,
                'icon' => 'phone',
                'tone' => 'accent',
                'url' => route('dashboard.requests.index'),
            ]);

        $properties = Property::with('status')->latest()->take($limit)->get()
            ->map(fn (Property $p) => [
                'title' => $p->status_id === $soldId
                    ? 'تم إغلاق صفقة '.$p->title.' بنجاح'
                    : 'تم إضافة عقار جديد '.($p->reference_code ?: $p->title),
                'at' => $p->created_at,
                'unread' => false,
                'icon' => $p->status_id === $soldId ? 'check' : 'building',
                'tone' => $p->status_id === $soldId ? 'success' : 'info',
                'url' => route('dashboard.properties.index'),
            ]);

        $clients = Client::with('source')->latest()->take($limit)->get()
            ->map(fn (Client $c) => [
                'title' => 'عميل جديد: '.$c->name.($c->source ? ' عبر '.$c->source->name : ''),
                'at' => $c->created_at,
                'unread' => false,
                'icon' => 'users',
                'tone' => 'info',
                'url' => route('dashboard.clients.index'),
            ]);

        return $requests->concat($properties)->concat($clients)
            ->filter(fn ($i) => $i['at'] !== null)
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }

    /** عدد غير المقروء = طلبات التواصل التي لم تُفتح بعد */
    public static function unreadCount(): int
    {
        return ContactRequest::unread()->count();
    }
}
