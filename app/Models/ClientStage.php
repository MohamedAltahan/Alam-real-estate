<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ClientStage extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'key', 'color', 'sort_order', 'is_final', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = [
        'is_final' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'stage_id');
    }
}
