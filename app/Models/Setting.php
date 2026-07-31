<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    /** value = JSON مرن (نص/رقم/مصفوفة، وممكن {ar,en} للحقول المترجمة بالاتفاق) */
    protected $casts = ['value' => 'array'];

    /** جلب قيمة إعداد بسرعة */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return static::where('group', $group)->where('key', $key)->value('value') ?? $default;
    }
}
