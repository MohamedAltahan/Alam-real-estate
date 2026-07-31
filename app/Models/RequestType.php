<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class RequestType extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'key', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = ['is_active' => 'boolean'];

    public function contactRequests(): HasMany
    {
        return $this->hasMany(ContactRequest::class);
    }
}
