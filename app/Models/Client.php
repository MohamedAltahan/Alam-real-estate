<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'area_id', 'type_id',
        'stage_id', 'agent_id', 'source_id', 'rating', 'notes',
        'desired_unit_type_id', 'social_status', 'nationality', 'household_size',
        'workplace', 'in_person', 'visit_times', 'preferred_contact',
        'property_address', 'recorded_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'household_size' => 'integer',
        'in_person' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

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

    public function desiredUnitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'desired_unit_type_id');
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

    /** عقارات العميل (تظهر في شاشته) */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'client_property')
            ->withPivot('relation', 'notes')
            ->withTimestamps();
    }

    /** نص منسق لنسخ بيانات العميل ومشاركتها في رسالة عادية. */
    public function shareText(): string
    {
        $socialLabels = ['single' => 'أعزب', 'married' => 'متزوج', 'family' => 'عائلة', 'company' => 'شركات'];
        $contactLabels = ['whatsapp' => 'واتساب', 'call' => 'اتصال'];
        $interactionLabels = ['call' => 'مكالمة', 'meeting' => 'مقابلة', 'whatsapp' => 'واتساب', 'email' => 'بريد إلكتروني'];
        $propertyRefs = $this->properties->pluck('reference_code')->filter()->implode('، ');
        $interactionLog = $this->interactions->map(function ($interaction) use ($interactionLabels) {
            $parts = collect([
                $interaction->occurred_at?->format('Y-m-d'),
                $interactionLabels[$interaction->type] ?? $interaction->type,
                $interaction->stage?->name,
                $interaction->notes,
                $interaction->user ? 'بواسطة '.$interaction->user->name : null,
            ])->filter()->implode(' — ');

            return '• '.$parts;
        })->implode("\n");

        return collect([
            'بيانات العميل: '.$this->name,
            'الهاتف: '.$this->phone,
            $this->email ? 'البريد الإلكتروني: '.$this->email : null,
            $this->nationality ? 'الجنسية: '.$this->nationality : null,
            $this->social_status ? 'الحالة الاجتماعية: '.($socialLabels[$this->social_status] ?? $this->social_status) : null,
            $this->household_size ? 'عدد الأفراد: '.$this->household_size : null,
            $this->workplace ? 'مكان العمل: '.$this->workplace : null,
            $this->preferred_contact ? 'طريقة التواصل: '.($contactLabels[$this->preferred_contact] ?? $this->preferred_contact) : null,
            ! is_null($this->in_person) ? 'حضوري: '.($this->in_person ? 'نعم' : 'لا') : null,
            $this->visit_times ? 'أوقات الزيارة: '.$this->visit_times : null,
            $this->desiredUnitType ? 'نوع الوحدة المطلوبة: '.$this->desiredUnitType->name : null,
            $this->area ? 'المنطقة المطلوبة: '.$this->area->name : null,
            $this->property_address ? 'عنوان العقار المطلوب: '.$this->property_address : null,
            $this->type ? 'نوع العميل: '.$this->type->name : null,
            $this->stage ? 'الحالة: '.$this->stage->name : null,
            $this->agent ? 'مسؤول العقار: '.$this->agent->name : null,
            $this->recordedBy ? 'سجّل البيانات: '.$this->recordedBy->name : null,
            $propertyRefs ? 'العقارات المرتبطة: '.$propertyRefs : null,
            $this->notes ? 'الملاحظات: '.$this->notes : null,
            $interactionLog ? "سجل التواصل:\n".$interactionLog : null,
        ])->filter()->implode("\n");
    }
}
