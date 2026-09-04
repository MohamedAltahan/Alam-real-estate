<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** احتياج عقار للعميل: نوع وحدة + مدينة + منطقة (سطر واحد من عدة أسطر) */
class ClientPropertyNeed extends Model
{
    protected $fillable = ['client_id', 'city_id', 'area_id', 'unit_type_id', 'sort_order'];

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

    /** "شقة · حولي · السالمية" */
    public function describe(): string
    {
        return collect([$this->unitType?->name, $this->city?->name, $this->area?->name])
            ->filter()
            ->implode(' · ') ?: '—';
    }
}
