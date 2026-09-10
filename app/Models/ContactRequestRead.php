<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** قراءة مستخدم لطلب تواصل — صف واحد لكل (طلب، مستخدم) */
class ContactRequestRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['contact_request_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
