<?php

namespace App\Notifications;

use App\Models\ClientViewing;
use Illuminate\Notifications\Notification;

/** تذكير بموعد معاينة قريب — يُخزَّن في جدول notifications ويظهر في الجرس مع صوت تنبيه. */
class ViewingReminder extends Notification
{
    public const KIND = 'viewing';

    public function __construct(public ClientViewing $viewing) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $client = $this->viewing->client;
        $property = $this->viewing->property;
        $at = $this->viewing->scheduled_at;

        return [
            'kind' => self::KIND,
            'viewing_id' => $this->viewing->id,
            'client_id' => $client?->id,
            'client_name' => $client?->name,
            'property_ref' => $property?->reference_code,
            'scheduled_at' => $at?->toIso8601String(),
            'title' => $this->title(),
            'url' => $client
                ? route('dashboard.clients.show', $client)
                : route('dashboard.viewings.index'),
        ];
    }

    private function title(): string
    {
        $at = $this->viewing->scheduled_at;
        $client = $this->viewing->client?->name ?: 'عميل';
        $ref = $this->viewing->property?->reference_code;
        $minutes = $at ? (int) now()->diffInMinutes($at, false) : null;

        $when = match (true) {
            $minutes === null => 'معاينة',
            $minutes < 0 => 'معاينة فات موعدها',
            $minutes < 60 => 'معاينة بعد '.$minutes.' دقيقة',
            $minutes < 1440 => 'معاينة بعد '.(int) round($minutes / 60).' ساعة',
            default => 'معاينة غداً',
        };

        return $when.': '.$client.($ref ? ' — '.$ref : '').($at ? ' ('.$at->format('Y-m-d H:i').')' : '');
    }
}
