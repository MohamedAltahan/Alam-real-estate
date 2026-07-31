<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Faq extends Model
{
    use HasTranslations;

    protected $fillable = ['question', 'answer', 'sort_order', 'is_active'];

    public array $translatable = ['question', 'answer'];

    protected $casts = ['is_active' => 'boolean'];
}
