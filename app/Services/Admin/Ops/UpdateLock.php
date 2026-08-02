<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * قفل التحديث (2.11-ب): **تحديثان في آنٍ واحد = قاعدة نصف مرحَّلة**.
 *
 * لماذا صفّ في قاعدة البيانات لا ملفّ ولا كاش؟ لأنّ القفل لازم يكون **ذرّيًّا**
 * ومرئيًّا لكلّ عمّال الويب مهما تعدّدت العمليّات والخوادم — والفهرس الفريد على
 * الاسم هو أبسط ذرّيّة نملكها بلا مكتبة ولا خدمة خارجيّة.
 *
 * وللقفل **مهلة**: لو انقطع التيّار وسط تحديث فالصفّ يبقى، ولولا المهلة لبقيت
 * المنصّة مقفولة عن التحديث للأبد بلا مَن يحرّرها.
 */
class UpdateLock
{
    public const NAME = 'updates.migrate';

    /**
     * محاولة الإمساك بالقفل — يرجع التوكن عند النجاح و`null` لو في تحديث شغّال.
     * ولا نفحص «هل هو محجوز؟» ثمّ نكتب: بين الفحص والكتابة تقع المسابقة كلّها،
     * فنكتب مباشرةً ونترك الفهرس الفريد يحكم.
     */
    public function acquire(?User $actor, string $name = self::NAME): ?string
    {
        $this->releaseExpired();

        $token = (string) Str::uuid();
        $minutes = max(1, (int) setting('updates.lock_ttl_minutes', 30));

        try {
            DB::table('update_locks')->insert([
                'name' => $name,
                'token' => $token,
                'locked_by' => $actor?->id,
                'locked_at' => now(),
                'expires_at' => now()->addMinutes($minutes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            return null;
        }

        return $token;
    }

    /** فكّ القفل بتوكنه وحده — فلا يفكّ تشغيلٌ قفلَ تشغيلٍ آخر */
    public function release(string $token, string $name = self::NAME): bool
    {
        return DB::table('update_locks')->where('name', $name)->where('token', $token)->delete() > 0;
    }

    public function current(string $name = self::NAME): ?object
    {
        $this->releaseExpired();

        return DB::table('update_locks')
            ->leftJoin('users', 'users.id', '=', 'update_locks.locked_by')
            ->where('update_locks.name', $name)
            ->select(['update_locks.*', 'users.name as holder_name', 'users.code as holder_code'])
            ->first();
    }

    public function isLocked(string $name = self::NAME): bool
    {
        return $this->current($name) !== null;
    }

    /** الأقفال التي انتهت مهلتها ليست أقفالًا — تُزال قبل أيّ قراءة أو كتابة */
    public function releaseExpired(): int
    {
        return DB::table('update_locks')->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
    }
}
