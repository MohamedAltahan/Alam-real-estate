<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** قالب رسالة واتساب (نص بمتغيّرات مثل {اسم_العميل}) */
class WhatsappTemplate extends Model
{
    protected $fillable = ['key', 'name', 'body'];
}
