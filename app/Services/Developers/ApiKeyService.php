<?php

namespace App\Services\Developers;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * مفاتيح API الخارجيّة (12.15-أ · 12.15-ج).
 *
 * ⛔ **قيدان لا يُمَسّان:**
 * 1) المفتاح الكامل **يُعرَض نصًّا صريحًا مرّة واحدة فقط** لحظة الإنشاء/التدوير
 *    — ثمّ **Hash فقط** في القاعدة (`Hash::make()`، نفس أسلوب توكن استرجاع
 *    كلمة المرور)، فلا طريق لاستعادته بعدها ولو من قاعدة البيانات نفسها.
 * 2) `resolve()` **لا يُطابِق أبدًا** مفتاحًا `status != 'active'` أو
 *    `expires_at` في الماضي — وهو الحارس الذي يُثبِته اختبار الـMutation.
 */
class ApiKeyService
{
    /**
     * القائمة المقفولة للـScopes (12.15-أ) — لا Scope حرّ يُكتَب يدويًّا.
     * توسيعها لاحقًا لا يكسر التوافق (مفاتيح قديمة تبقى بصلاحيّاتها كما مُنِحت).
     *
     * @var list<string>
     */
    public const SCOPES = [
        'read:courses',
        'read:certificates',
        'read:users_basic',
    ];

    /** تسميات الـScopes كما يقرؤها المسؤول — عبر `setting()` لا نصًّا محروقًا (2.13-أ) */
    public static function scopeLabels(): array
    {
        return [
            'read:courses' => (string) setting('developers.scopes.read_courses', 'قراءة قائمة التدريبات المنشورة'),
            'read:certificates' => (string) setting('developers.scopes.read_certificates', 'التحقّق من حالة شهادة'),
            'read:users_basic' => (string) setting('developers.scopes.read_users_basic', 'قراءة بيانات مستخدم أساسيّة'),
        ];
    }

    /**
     * يولّد مفتاحًا جديدًا، يخزّن Hash فقط، ويُرجع النصّ الصريح **مرّة واحدة**.
     *
     * @param  list<string>  $scopes
     * @return array{record: ApiKey, plain_key: string}
     */
    public function create(string $name, array $scopes, User $actor, ?int $rateLimit = null, ?Carbon $expiresAt = null): array
    {
        $secret = Str::random(40);
        $prefix = 'sk_'.Str::random(8);

        $key = ApiKey::create([
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => Hash::make($secret),
            'scopes' => array_values(array_intersect($scopes, self::SCOPES)),
            'rate_limit_per_minute' => $rateLimit,
            'expires_at' => $expiresAt,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);

        AuditTrail::log($actor, 'api_key.created', $key, [], ['name' => $name, 'scopes' => $key->scopes]);

        return ['record' => $key, 'plain_key' => $prefix.'.'.$secret];
    }

    /**
     * تدوير: يُبطل القديم فورًا ويُصدر مفتاحًا جديدًا بنفس الاسم والصلاحيّات
     * والانتهاء وحدّ المعدّل (12.15-أ).
     *
     * @return array{record: ApiKey, plain_key: string}
     */
    public function rotate(ApiKey $key, User $actor): array
    {
        $fresh = $this->create($key->name, $key->scopes, $actor, $key->rate_limit_per_minute, $key->expires_at);

        $this->revoke($key, $actor, 'api_key.rotated');

        return $fresh;
    }

    /** إبطال فوريّ — لا رجعة فيه (12.15-أ) */
    public function revoke(ApiKey $key, User $actor, string $action = 'api_key.revoked'): void
    {
        $old = $key->only(['status']);

        $key->fill([
            'status' => 'revoked',
            'revoked_by' => $actor->id,
            'revoked_at' => now(),
        ])->save();

        AuditTrail::log($actor, $action, $key, $old, ['status' => 'revoked']);
    }

    /**
     * التحقّق وقت الطلب — يُستخدَم في `AuthenticateApiKey` وحده.
     *
     * الصيغة المتوقَّعة: `"sk_XXXXXXXX.السرّ"` — نفصل البادئة للفهرسة السريعة
     * ثمّ `Hash::check()` على الباقي، ولا مطابقة نصّيّة مباشرة أبدًا.
     *
     * ⛔ **لا تُطابِق أبدًا** مفتاحًا مُبطَلًا أو منتهيًا — هذا هو الحارس الأمنيّ
     * الذي يُثبِته اختبار الـMutation (`isActive()`).
     */
    public function resolve(string $providedKey): ?ApiKey
    {
        if (! str_contains($providedKey, '.')) {
            return null;
        }

        [$prefix, $secret] = explode('.', $providedKey, 2);

        if ($prefix === '' || $secret === '') {
            return null;
        }

        $key = ApiKey::query()->where('key_prefix', $prefix)->first();

        if (! $key || ! Hash::check($secret, $key->key_hash)) {
            return null;
        }

        return $key->isActive() ? $key : null;
    }
}
