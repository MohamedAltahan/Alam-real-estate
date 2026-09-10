<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactRequest extends Model
{
    /**
     * حقول الإدخال العام فقط — status / is_read / handled_by تُدار من الداشبورد،
     * مش من فورم الموقع (حماية من mass-assignment).
     */
    protected $fillable = [
        'name', 'phone', 'email', 'request_type_id', 'subject', 'message', 'property_id',
    ];

    protected $casts = ['is_read' => 'boolean'];

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** العميل الذي أُنشئ من هذا الطلب (بعد التحويل إلى الـ CRM) */
    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    /** قراءات المستخدمين للطلب — حالة «مقروء» لكل مستخدم على حدة */
    public function reads(): HasMany
    {
        return $this->hasMany(ContactRequestRead::class);
    }

    public function isConverted(): bool
    {
        return $this->converted_client_id !== null;
    }

    /**
     * تعليم الطلب كمتواصَل معه — يصبح مقروءاً للجميع.
     * ملاحظة: status / is_read / handled_by ليست في $fillable عمداً (حماية من
     * mass-assignment عبر فورم الموقع)، لذلك تُضبط هنا مباشرة لا عبر update().
     * is_read عمود قديم يُحدَّث للتوافق فقط ولا يُقرأ — القراءة لكل مستخدم في reads().
     */
    public function markContacted(?int $userId = null): void
    {
        $this->status = 'contacted';
        $this->is_read = true;
        $this->handled_by = $userId ?? $this->handled_by;
        $this->save();
    }

    // ===== القراءة لكل مستخدم =====

    /** غير مقروء لهذا المستخدم: لم يُتواصل معه بعد ولم يفتحه هو */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->where('status', '!=', 'contacted')
            ->whereDoesntHave('reads', fn (Builder $reads) => $reads->where('user_id', $user->id));
    }

    /** تحميل قراءة المستخدم الحالي فقط مع القائمة (بلا استعلام لكل بطاقة) */
    public function scopeWithReadBy(Builder $query, User $user): Builder
    {
        return $query->with(['reads' => fn ($reads) => $reads->where('user_id', $user->id)]);
    }

    /** «تم التواصل» مقروء للجميع، وإلا حسب صف قراءة المستخدم */
    public function isReadBy(User $user): bool
    {
        if ($this->status === 'contacted') {
            return true;
        }

        $reads = $this->relationLoaded('reads')
            ? $this->reads
            : $this->reads()->where('user_id', $user->id)->get();

        return $reads->contains(fn (ContactRequestRead $read) => (int) $read->user_id === (int) $user->id);
    }

    public function markReadBy(User $user): void
    {
        $this->reads()->firstOrCreate(['user_id' => $user->id], ['read_at' => now()]);
    }

    /** «قراءة الكل» لمستخدم واحد — حلقة firstOrCreate بدل insertOrIgnore (غير مدعوم في أوراكل) */
    public static function markAllReadFor(User $user): void
    {
        static::unreadFor($user)->pluck('id')->each(fn ($id) => ContactRequestRead::firstOrCreate(
            ['contact_request_id' => (int) $id, 'user_id' => $user->id],
            ['read_at' => now()],
        ));
    }
}
