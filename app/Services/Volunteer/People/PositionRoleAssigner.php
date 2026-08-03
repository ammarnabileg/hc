<?php

namespace App\Services\Volunteer\People;

use App\Models\Membership;
use App\Models\Position;
use App\Models\Role;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **دور البوزشن: يُمنَح لحظة التسكين ويُسحَب لحظة انتهاء العضويّة** (13.4-هـ · 13.4-س · 12.2.3-ب).
 *
 * القاعدة الحاكمة في 12.2.3: «**الدور يحدّد «ماذا» والعضويّة تحدّد «أين»**».
 * وكان المبنيّ ينشئ العضويّة («أين») **بلا دور** («ماذا») — فالمتطوّع يمرّ بالرحلة
 * المنصوصة كاملةً ثمّ يجد **403 على كلّ تابّ** في لوحة التطوّع، بينما 13.4-هـ يعد
 * بأنّ زرّ التطوّع «يتحوّل لوحة التطوّع».
 *
 * ولذلك ثلاثة قيود تحكم هذا الملفّ:
 *
 *  1) **الإسناد داخل العضويّة نفسها** — يُكتَب `role_user.membership_id`، فالدور
 *     لا يسري إلّا في سياق تلك العضويّة (قفص العضويّة في `AccessEngine`)، ولا
 *     يصير سلطةً عابرةً للكيانات.
 *
 *  2) **مَن له عضويّتان ببوزشنين له صفّان** — ولذلك لا نمرّ بـ`assignRole()`:
 *     هي تنادي `syncWithoutDetaching` الذي يُفهرِس بالدور وحده، فيُحرّك
 *     `membership_id` للصفّ القائم بدل أن يفتح صفًّا ثانيًا. والقيد الفريد على
 *     الجدول `(role_id, user_id, membership_id)` يسمح بالصفّين أصلًا.
 *
 *  3) **السحب بالعضويّة لا بالمستخدم** — فإنهاء إحدى العضويّتين يسحب دورَها
 *     وحدَه، وتبقى الأخرى بدورها كما هي.
 *
 * و«أخوكم» (13.4-ص) عنصرٌ شرفيّ **بلا صلاحيّات** — فبوزشنه لا يقابله دور.
 */
class PositionRoleAssigner
{
    /**
     * رتبة البوزشن ⟵ دور 12.2.3-ب المقابل.
     *
     * الرتبة لا المفتاح هي المرجع الاحتياطيّ: البوزشنز الستّة المزروعة مفاتيحُها
     * مطابقةٌ لمفاتيح الأدوار، لكنّ الأدمن يملك إضافة بوزشنٍ باسمٍ آخر — فيُقرَأ
     * بمرتبته في السلّم لا باسمه، ولا يبقى بلا دور.
     */
    private const ROLE_BY_RANK = [
        1 => 'coordinator',
        2 => 'team_leader',
        3 => 'supervisor',
        4 => 'director',
        5 => 'track_supervisor',
        6 => 'volunteer_gm',
    ];

    /** دور 12.2.3-ب المقابل لهذا البوزشن — و«أخوكم» بلا دور (13.4-ص) */
    public function roleFor(?Position $position): ?Role
    {
        if (! $position || $position->is_honorary) {
            return null;
        }

        $role = Role::query()
            ->where('key', $position->key)
            ->where('layer', 'volunteer')
            ->first();

        if ($role) {
            return $role;
        }

        $fallback = self::ROLE_BY_RANK[(int) $position->rank] ?? null;

        return $fallback ? Role::query()->where('key', $fallback)->first() : null;
    }

    /** منح دور البوزشن **داخل هذه العضويّة** — لحظة التسكين (13.4-هـ) */
    public function grant(Membership $membership, ?int $assignedBy = null): ?Role
    {
        $position = $membership->relationLoaded('position')
            ? $membership->position
            : Position::query()->find($membership->position_id);

        $role = $this->roleFor($position);

        if (! $role) {
            return null;
        }

        $keys = [
            'role_id' => $role->id,
            'user_id' => $membership->user_id,
            'membership_id' => $membership->id,
        ];

        if (! DB::table('role_user')->where($keys)->exists()) {
            DB::table('role_user')->insert($keys + [
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->forget($membership);

        return $role;
    }

    /**
     * سحب ما أُسنِد **داخل هذه العضويّة** — لحظة انتهائها (13.4-س).
     *
     * الشرط على `membership_id` لا على المستخدم: فالمُقصى لا يبقى حاملًا ما لم
     * يعد يستحقّه، وصاحبُ العضويّتين لا يُجرَّد من الثانية حين تنتهي الأولى.
     * وأدوار المنصّة (بلا عضويّة) لا يمسّها هذا السحب أصلًا.
     *
     * @return int عدد الصفوف المسحوبة
     */
    public function revoke(Membership $membership): int
    {
        $removed = DB::table('role_user')
            ->where('user_id', $membership->user_id)
            ->where('membership_id', $membership->id)
            ->delete();

        $this->forget($membership);

        return $removed;
    }

    private function forget(Membership $membership): void
    {
        $user = $membership->relationLoaded('user') ? $membership->user : $membership->user()->first();

        if ($user) {
            app(AccessEngine::class)->forget($user);

            return;
        }

        app(AccessEngine::class)->forget();
    }
}
