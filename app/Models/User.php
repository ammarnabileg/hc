<?php

namespace App\Models;

use App\Models\Concerns\HasAccessControl;
use App\Services\Images\ShortName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAccessControl, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activated_at' => 'datetime',
            'tracking_consent_at' => 'datetime',
            'last_seen_at' => 'datetime',
            // آخر كشف تلقائيّ للمنطقة الزمنيّة (5)
            'auto_timezone_at' => 'datetime',
            'birthdate' => 'date',
            'sound_enabled' => 'boolean',
            // الحركة تُضبَط من داخل المنصّة لا من تفضيل نظام التشغيل (2.3 · 2.14-ب)
            'motion_enabled' => 'boolean',
            'simple_mode' => 'boolean',
            'advanced_mode' => 'boolean',
            'pinned_pages' => 'array',
            'table_columns' => 'array',
            'last_tabs' => 'array',
            // مقاسات الأفاتار الثلاثة المولَّدة عند الرفع (2.7)
            'avatar_sizes' => 'array',
            // النموّ والخصوصيّة (21.1-ب · 21.1-ج · 21.3-د)
            'profile_completion_rewarded_at' => 'datetime',
            'invite_landing_seen_at' => 'datetime',
            'tracking_scopes' => 'array',
        ];
    }

    // ------------------------------------------------------------ العلاقات

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(WalletBalance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function repScore(): HasOne
    {
        return $this->hasOne(RepScore::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    public function privacySettings(): HasMany
    {
        return $this->hasMany(UserPrivacySetting::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function notificationsFeed(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    // ------------------------------------------------------------ مساعدات

    /** رصيد عملة بعينها */
    public function balance(string $currencyCode): float
    {
        return (float) $this->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    /** الرابط الدائم للبروفايل: /u/CODE */
    public function profileUrl(): string
    {
        return url('/u/'.$this->code);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * اسم العرض المختصر (12.14-ج): أوّل كلمتين — وأدوات الاسم
     * (عبد · أبو · بن · آل · abd · abu …) جزءٌ من الكلمة التالية لا وحدةٌ مستقلّة.
     */
    public function shortName(int $units = 2): string
    {
        // القاعدة واحدة في الواجهة وقوالب الاستوديو والاستخراج — فمصدرها واحد
        return ShortName::of($this->name, $units);
    }
}
