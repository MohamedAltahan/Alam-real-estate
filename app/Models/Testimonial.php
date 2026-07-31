<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Testimonial extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'title', 'content', 'rating', 'avatar', 'sort_order', 'is_active'];

    public array $translatable = ['title', 'content'];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];
}
