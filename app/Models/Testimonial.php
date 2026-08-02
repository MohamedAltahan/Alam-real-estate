<?php

namespace App\Models;

use App\Concerns\InteractsWithWebImages;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

class Testimonial extends Model implements HasMedia
{
    use HasTranslations;
    use InteractsWithWebImages;

    protected $fillable = ['name', 'title', 'content', 'rating', 'sort_order', 'is_active'];

    public array $translatable = ['title', 'content'];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->imageUrl('avatar');
    }
}
