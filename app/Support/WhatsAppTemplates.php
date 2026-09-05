<?php

namespace App\Support;

use App\Models\ClientViewing;
use App\Models\WhatsappTemplate;

/**
 * قوالب رسائل واتساب الخاصة بالمعاينات ومتغيّراتها.
 * نوعان: تفاصيل المعاينة للمالك · متابعة المعاينة للعميل.
 */
final class WhatsAppTemplates
{
    public const KIND_OWNER = 'viewing_owner';

    public const KIND_CLIENT = 'viewing_client';

    public const KINDS = [
        self::KIND_OWNER => 'تفاصيل المعاينة للمالك',
        self::KIND_CLIENT => 'متابعة المعاينة للعميل',
    ];

    /** العلامة على المعاينة التي تُضبط عند قبول البوابة للرسالة */
    public const MARKS = [
        self::KIND_OWNER => 'owner_notified_at',
        self::KIND_CLIENT => 'client_followed_up_at',
    ];

    public const DEFAULTS = [
        self::KIND_OWNER => [
            'name' => 'تفاصيل المعاينة للمالك',
            'body' => "السلام عليكم {اسم_المستلم}،\nنود إبلاغكم بموعد معاينة للعقار {رقم_العقار} — {اسم_العقار}.\nالعميل: {اسم_العميل}\nالهاتف: {هاتف_العميل}\nالموعد: {موعد_المعاينة}\nمندوب المبيعات: {مندوب_المبيعات} — {هاتف_المندوب}\n\nعلم العقارية",
        ],
        self::KIND_CLIENT => [
            'name' => 'متابعة المعاينة للعميل',
            'body' => "السلام عليكم {اسم_العميل}،\nشكراً لمعاينتكم العقار {رقم_العقار} — {اسم_العقار} بتاريخ {موعد_المعاينة}.\nنتيجة المعاينة: {نتيجة_المعاينة}\nيسعدنا تواصلكم مع مندوب المبيعات {مندوب_المبيعات} — {هاتف_المندوب} لأي استفسار.\n\nعلم العقارية",
        ],
    ];

    /** المتغيّرات المتاحة وشرحها (تُعرض في شاشة القوالب) */
    public const PLACEHOLDERS = [
        '{اسم_المستلم}' => 'اسم صاحب الرقم المختار (المالك / الوكيل / العميل)',
        '{اسم_العميل}' => 'اسم العميل',
        '{هاتف_العميل}' => 'هاتف العميل',
        '{رقم_العقار}' => 'الرقم المرجعي للعقار',
        '{اسم_العقار}' => 'عنوان العقار',
        '{عنوان_العقار}' => 'المنطقة واسم المبنى',
        '{اسم_المالك}' => 'اسم مالك العقار',
        '{موعد_المعاينة}' => 'تاريخ ووقت المعاينة',
        '{مندوب_المبيعات}' => 'اسم مندوب المبيعات',
        '{هاتف_المندوب}' => 'هاتف مندوب المبيعات',
        '{نتيجة_المعاينة}' => 'اختار العقار / لم يختر / قيد الانتظار',
        '{ملاحظات}' => 'ملاحظة المعاينة',
    ];

    public static function kindLabel(string $kind): string
    {
        return self::KINDS[$kind] ?? $kind;
    }

    /** نص القالب المحفوظ (أو الافتراضي إن لم يُحفظ بعد) */
    public static function body(string $kind): string
    {
        return WhatsappTemplate::where('key', $kind)->value('body') ?? self::DEFAULTS[$kind]['body'] ?? '';
    }

    /** استبدال المتغيّرات بقيم المعاينة */
    public static function render(string $body, ClientViewing $viewing, ?string $recipientName = null): string
    {
        return strtr($body, self::variables($viewing, $recipientName));
    }

    /** @return array<string, string> */
    public static function variables(ClientViewing $viewing, ?string $recipientName = null): array
    {
        $client = $viewing->client;
        $property = $viewing->property;
        $agent = $client?->agent ?? $property?->agent;
        $owner = $property?->owner;

        $address = collect([$property?->area?->name, $property?->building_name])->filter()->implode(' — ');

        return [
            '{اسم_المستلم}' => $recipientName ?: ($owner?->name ?? ''),
            '{اسم_العميل}' => (string) ($client?->name ?? ''),
            '{هاتف_العميل}' => (string) ($client?->full_phone ?? ''),
            '{رقم_العقار}' => (string) ($property?->reference_code ?? ''),
            '{اسم_العقار}' => (string) ($property?->title ?? ''),
            '{عنوان_العقار}' => $address,
            '{اسم_المالك}' => (string) ($owner?->name ?? ''),
            '{موعد_المعاينة}' => $viewing->scheduled_at?->format('Y-m-d — h:i A') ?? '',
            '{مندوب_المبيعات}' => (string) ($agent?->name ?? ''),
            '{هاتف_المندوب}' => (string) ($agent?->phone ?? ''),
            '{نتيجة_المعاينة}' => ClientFields::outcomeLabel($viewing->outcome),
            '{ملاحظات}' => (string) ($viewing->notes ?? ''),
        ];
    }
}
