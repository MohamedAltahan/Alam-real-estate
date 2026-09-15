<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** احتياج عقار للعميل: نوع عقار (سكني/تجاري) + نوع وحدة + مدينة + منطقة + مساحة + غرف (سطر واحد من عدة أسطر) */
class ClientPropertyNeed extends Model
{
    protected $fillable = ['client_id', 'city_id', 'area_id', 'unit_type_id', 'category', 'area_size', 'rooms', 'sort_order'];

    protected $casts = [
        'area_size' => 'decimal:2',
        'rooms' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function categoryLabel(): ?string
    {
        return UnitType::CATEGORIES[$this->category] ?? null;
    }

    /** "سكني · شقة · حولي · السالمية · 3 غرف · 120 م²" */
    public function describe(): string
    {
        return collect([
            $this->categoryLabel(),
            $this->unitType?->name,
            $this->city?->name,
            $this->area?->name,
            $this->rooms !== null ? $this->rooms.' غرف' : null,
            $this->area_size !== null ? rtrim(rtrim(number_format((float) $this->area_size, 2, '.', ''), '0'), '.').' م²' : null,
        ])->filter()->implode(' · ') ?: '—';
    }
}
