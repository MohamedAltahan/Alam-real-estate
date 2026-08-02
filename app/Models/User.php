<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Concerns\InteractsWithWebImages;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Translatable\HasTranslations;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasTranslations;
    use InteractsWithWebImages;

    protected $fillable = [
        'name', 'email', 'password',
        'phone', 'civil_id', 'job_title', 'status',
        'is_agent', 'bio', 'languages', 'response_time', 'preferences',
        'rating', 'reviews_count',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->imageUrl('avatar');
    }

    /** حقول قابلة للترجمة (spatie translatable) */
    public array $translatable = ['bio'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_agent' => 'boolean',
            'languages' => 'array',
            'preferences' => 'array',
            'rating' => 'decimal:2',
            'reviews_count' => 'integer',
            // ملاحظة: bio مُدار بواسطة HasTranslations — بدون cast
        ];
    }

    // ===== العلاقات =====

    /** العملاء اللي هذا المستخدم وكيلهم */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'agent_id');
    }

    /** العقارات اللي هذا المستخدم وكيلها */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'agent_id');
    }

    /** تفاعلات التواصل اللي سجّلها */
    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class, 'user_id');
    }

    /** تقييمات هذا الوكيل (polymorphic) */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /** يعيد حساب المتوسط وعدد التقييمات المنشورة — يُستدعى بعد أي تغيير عليها */
    public function refreshRating(): void
    {
        $published = $this->reviews()->published();

        $this->forceFill([
            'reviews_count' => $published->count(),
            'rating' => ($avg = $published->avg('rating')) ? round((float) $avg, 2) : null,
        ])->save();
    }

    /** هل هو وكيل ظاهر بالموقع؟ */
    public function scopeAgents($query)
    {
        return $query->where('is_agent', true);
    }
}
