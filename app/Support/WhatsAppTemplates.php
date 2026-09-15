<?php

namespace App\Support;

use App\Models\ClientViewing;
use App\Models\WhatsappTemplate;

/**
 * قوالب رسائل واتساب الخاصة بالمعاينات ومتغيّراتها.
 * نوعان — كلاهما يُرسل إلى المسؤولين عن العقار: تفاصيل المعاينة · نتيجة المعاينة.
 */
final class WhatsAppTemplates
{
    public const KIND_OWNER = 'viewing_owner';

    public const KIND_CLIENT = 'viewing_client';

    public const KINDS = [
        self::KIND_OWNER => 'تفاصيل المعاينة لمسؤول العقار',
        self::KIND_CLIENT => 'نتيجة المعاينة لمسؤول العقار',
    ];

    /** العلامة على المعاينة التي تُضبط عند قبول البوابة للرسالة */
    public const MARKS = [
        self::KIND_OWNER => 'owner_notified_at',
        self::KIND_CLIENT => 'client_followed_up_at',
    ];

    public const DEFAULTS = [
        self::KIND_OWNER => [
            'name' => 'تفاصيل المعاينة لمسؤول العقار',
            'body' => "السلام عليكم {اسم_المستلم}،\nنود إبلاغكم بموعد معاينة للعقار {رقم_العقار} — {اسم_العقار}.\nالعميل: {اسم_العميل}\nالهاتف: {هاتف_العميل}\nالموعد: {موعد_المعاينة}\nمندوب المبيعات: {مندوب_المبيعات} — {هاتف_المندوب}\n\nعلم العقارية",
        ],
        self::KIND_CLIENT => [
            'name' => 'نتيجة المعاينة لمسؤول العقار',
            'body' => "السلام عليكم {اسم_المستلم}،\nنفيدكم بنتيجة معاينة العميل {اسم_العميل} للعقار {رقم_العقار} — {اسم_العقار} بتاريخ {موعد_المعاينة}.\nنتيجة المعاينة: {نتيجة_المعاينة}\nللاستفسار: مندوب المبيعات {مندوب_المبيعات} — {هاتف_المندوب}\n\nعلم العقارية",
        ],
    ];

    /** المتغيّرات المتاحة وشرحها (تُعرض في شاشة القوالب) */
    public const PLACEHOLDERS = [
        '{اسم_المستلم}' => 'اسم مسؤول العقار المختار (الحارس / الوكيل / المدير…)',
        '{اسم_العميل}' => 'اسم العميل',
        '{هاتف_العميل}' => 'هاتف العميل مع إخفاء آخر رقمين (‎+965551122xx)',
        '{رقم_العقار}' => 'الرقم المرجعي للعقار',
        '{اسم_العقار}' => 'عنوان العقار',
        '{عنوان_العقار}' => 'المنطقة واسم المبنى',
        '{اسم_المالك}' => 'اسم مالك العقار',
        '{موعد_المعاينة}' => 'تاريخ ووقت المعاينة',
        '{مندوب_المبيعات}' => 'اسم مندوب المبيعات',
        '{هاتف_المندوب}' => 'هاتف مندوب المبيعات',
        '{نتيجة_المعاينة}' => 'قيد الانتظار / قيد الدراسة / مهتم / غير مهتم / إلغاء الموعد',
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
            '{هاتف_العميل}' => $client ? PhoneNumber::masked($client->phone_code, $client->phone) : '',
            '{رقم_العقار}' => (string) ($property?->reference_code ?? ''),
            '{اسم_العقار}' => (string) ($property?->title ?? ''),
            '{عنوان_العقار}' => $address,
            '{اسم_المالك}' => (string) ($owner?->name ?? ''),
            '{موعد_المعاينة}' => $viewing->scheduled_at?->format('Y-m-d — h:i A') ?? '',
            '{مندوب_المبيعات}' => (string) ($agent?->name ?? ''),
            '{هاتف_المندوب}' => PhoneNumber::ltr($agent?->phone),
            '{نتيجة_المعاينة}' => ClientFields::outcomeLabel($viewing->outcome),
            '{ملاحظات}' => (string) ($viewing->notes ?? ''),
        ];
    }
}
