<?php

namespace App\Models;

use App\Models\Concerns\HasAttachedFiles;
use App\Observers\ActivityObserver;
use App\Observers\ClientObserver;
use App\Support\ClientFields;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([ClientObserver::class, ActivityObserver::class])]
class Client extends Model implements HasMedia
{
    use HasAttachedFiles;

    /** مجموعة ملفات العميل (صور · PDF · Word · Excel) */
    public const FILES = 'files';

    protected $fillable = [
        'name', 'phone_code', 'phone', 'email', 'type_id',
        'stage_id', 'agent_id', 'source_id', 'rating', 'notes',
        'social_status', 'nationality', 'household_size',
        'workplace', 'preferred_contact', 'recorded_by', 'is_featured',
    ];

    protected $casts = [
        'rating' => 'integer',
        'household_size' => 'integer',
        'is_featured' => 'boolean',
        // تاريخ الربح الفعلي — يضبطه ClientObserver عند الانتقال إلى «ربح» (ليس fillable)
        'won_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ClientType::class, 'type_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ClientStage::class, 'stage_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class, 'source_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** سجل التواصل — كل مكالمة/مقابلة */
    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class)->latest('occurred_at');
    }

    /** احتياجات العقار (نوع وحدة + مدينة + منطقة) — أكثر من سطر */
    public function needs(): HasMany
    {
        return $this->hasMany(ClientPropertyNeed::class)->orderBy('sort_order')->orderBy('id');
    }

    /** المعاينات المجدولة/المنتهية — أكثر من سطر */
    public function viewings(): HasMany
    {
        return $this->hasMany(ClientViewing::class)->orderByDesc('scheduled_at')->orderByDesc('id');
    }

    /** سجل التعديلات (الأحدث أولاً) */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(ClientAuditLog::class)->orderByDesc('id');
    }

    /** عقارات العميل (تظهر في شاشته) */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'client_property')
            ->withPivot('relation', 'notes')
            ->withTimestamps();
    }

    /** الطلبات المميزة — تبويب في شاشة طلبات التواصل */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ===== Accessors =====

    /** "+965 55112233" */
    public function getFullPhoneAttribute(): string
    {
        return PhoneNumber::format($this->phone_code, $this->phone);
    }

    /** أرقام فقط لرابط واتساب */
    public function getWhatsappNumberAttribute(): string
    {
        return PhoneNumber::digits($this->phone_code, $this->phone);
    }

    /** نص منسق لنسخ بيانات العميل ومشاركتها في رسالة عادية. */
    public function shareText(): string
    {
        $needs = $this->needs->map(fn (ClientPropertyNeed $need) => '• '.$need->describe())->implode("\n");

        $viewings = $this->viewings->map(function (ClientViewing $viewing) {
            return '• '.collect([
                $viewing->property?->reference_code,
                $viewing->scheduled_at?->format('Y-m-d H:i'),
                $viewing->in_person ? 'حضوري' : 'غير حضوري',
                ClientFields::outcomeLabel($viewing->outcome),
                $viewing->notes,
            ])->filter()->implode(' — ');
        })->implode("\n");

        $interactionLog = $this->interactions->map(function ($interaction) {
            $parts = collect([
                $interaction->occurred_at?->format('Y-m-d'),
                ClientFields::enumLabel('type', $interaction->type),
                $interaction->stage?->name,
                $interaction->notes,
                $interaction->user ? 'بواسطة '.$interaction->user->name : null,
            ])->filter()->implode(' — ');

            return '• '.$parts;
        })->implode("\n");

        return collect([
            'بيانات العميل: '.$this->name,
            'الهاتف: '.$this->full_phone,
            $this->email ? 'البريد الإلكتروني: '.$this->email : null,
            $this->nationality ? 'الجنسية: '.$this->nationality : null,
            $this->social_status ? 'الحالة الاجتماعية: '.ClientFields::enumLabel('social_status', $this->social_status) : null,
            $this->household_size ? 'عدد الأفراد: '.$this->household_size : null,
            $this->workplace ? 'مكان العمل: '.$this->workplace : null,
            $this->preferred_contact ? 'طريقة التواصل: '.ClientFields::enumLabel('preferred_contact', $this->preferred_contact) : null,
            $needs ? "احتياج العقار:\n".$needs : null,
            $this->stage ? 'الحالة: '.$this->stage->name : null,
            $this->agent ? 'مندوب المبيعات: '.$this->agent->name : null,
            $this->recordedBy ? 'سجّل البيانات: '.$this->recordedBy->name : null,
            $viewings ? "المعاينات:\n".$viewings : null,
            $this->notes ? 'الملاحظات: '.$this->notes : null,
            $interactionLog ? "سجل التواصل:\n".$interactionLog : null,
        ])->filter()->implode("\n");
    }
}
