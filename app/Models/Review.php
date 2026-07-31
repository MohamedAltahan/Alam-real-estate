<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    protected $fillable = [
        'reviewable_type', 'reviewable_id',
        'reviewer_name', 'rating', 'comment', 'is_published', 'created_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_published' => 'boolean',
    ];

    /** العنصر المُقيَّم — عقار أو وكيل */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** الموظف اللي أضاف التقييم من الداشبورد */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
