<?php

namespace App\Notifications;

use App\Models\ClientViewing;
use Carbon\Carbon;
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

        $data = [
            'kind' => self::KIND,
            'viewing_id' => $this->viewing->id,
            'client_id' => $client?->id,
            'client_name' => $client?->name,
            'property_ref' => $property?->reference_code,
            'scheduled_at' => $this->viewing->scheduled_at?->toIso8601String(),
            // الضغط على الإشعار (من الجرس أو التنبيه المنبثق) يفتح صفحة المعاينات — مسار نسبي
            'url' => route('dashboard.viewings.index', absolute: false),
        ];

        // نص احتياطي وقت الإرسال — العرض يعيد حسابه من scheduled_at في كل مرة
        return $data + ['title' => self::describe($data)];
    }

    /**
     * نص الإشعار محسوبًا لحظة العرض لا لحظة الإرسال، حتى لا يظل مكتوبًا
     * «بعد 30 دقيقة» بعد مرور الموعد.
     *
     * @param  array<string, mixed>  $data
     */
    public static function describe(array $data): string
    {
        $at = filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : null;
        $client = $data['client_name'] ?: 'عميل';
        $reference = $data['property_ref'] ?? null;

        return self::when($at)
            .': '.$client
            .($reference ? ' — '.$reference : '')
            .($at ? ' ('.$at->format('Y-m-d H:i').')' : '');
    }

    /** هل فات موعد المعاينة؟ (يستخدمه العرض لتمييز التنبيه) */
    public static function isOverdue(array $data): bool
    {
        return filled($data['scheduled_at'] ?? null)
            && Carbon::parse($data['scheduled_at'])->isPast();
    }

    private static function when(?Carbon $at): string
    {
        if (! $at) {
            return 'معاينة';
        }

        $minutes = (int) round(Carbon::now()->diffInMinutes($at, false));

        if ($minutes < 0) {
            return 'معاينة فات موعدها منذ '.self::humanize(abs($minutes));
        }

        if ($minutes < 1) {
            return 'معاينة الآن';
        }

        return 'معاينة بعد '.self::humanize($minutes);
    }

    /** صياغة عربية سليمة للمدة: دقيقة · دقيقتين · 5 دقائق · 20 دقيقة */
    private static function humanize(int $minutes): string
    {
        if ($minutes < 60) {
            return self::count($minutes, 'دقيقة', 'دقيقتين', 'دقائق');
        }

        if ($minutes < 1440) {
            return self::count((int) round($minutes / 60), 'ساعة', 'ساعتين', 'ساعات');
        }

        return self::count((int) round($minutes / 1440), 'يوم', 'يومين', 'أيام');
    }

    private static function count(int $value, string $one, string $two, string $few): string
    {
        return match (true) {
            $value === 1 => $one,
            $value === 2 => $two,
            $value <= 10 => $value.' '.$few,
            default => $value.' '.$one,
        };
    }
}
