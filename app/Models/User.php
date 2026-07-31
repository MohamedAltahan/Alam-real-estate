<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Translatable\HasTranslations;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasTranslations;

    protected $fillable = [
        'name', 'email', 'password',
        'phone', 'civil_id', 'avatar', 'job_title', 'status',
        'is_agent', 'bio', 'languages', 'response_time', 'preferences',
    ];

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

    /** هل هو وكيل ظاهر بالموقع؟ */
    public function scopeAgents($query)
    {
        return $query->where('is_agent', true);
    }
}
