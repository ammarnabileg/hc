<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Membership;
use App\Models\Task;

/**
 * ⭐ تصفية المهامّ المفتوحة قبل انتهاء عضويّة (23-0.2-H3): «لا يُنفَّذ [خروجٌ من
 * قسم] ومعه مهامّ مفتوحة تتعلّق بقسمه — تنتقل ملكيّتها لأبلاينه المباشر بنفس
 * ديدلاييناتها، وله خيارات المالك (تنفيذ/تفكيك/إغلاق) بلا أيّ خصم على المنقول.
 * أمّا مساهماته المفتوحة فيستمرّ فيها عاديًّا».
 *
 * وكان هذا البند نصًّا في `clearance_items` (تشيك-ليست يدويّ يعلّمه الأدمن) —
 * **بلا تنفيذٍ فعليّ**: `grep` عبر `app/Services` لا يجد إعادة إسناد
 * `tasks.owner_id` في أيّ مسار. فمهامّ الخارج المفتوحة تبقى معلَّقةً على مالكٍ
 * ما عاد عضوًا في الكيان أصلًا — يتيمةً بلا أن يعلن أحد يُتمَها.
 *
 * ⛔ **الديدلاين لا يُلمَس**: عدم تعديل `deadline_at` هو معنى «بنفس
 * ديدلاييناتها». ⛔ **ولا أثر Rep**: عدم فتح أيّ معاملةٍ هنا هو معنى «بلا أيّ
 * خصم على المنقول». ⛔ **والمساهمات لا تُمَسّ**: هذا الملفّ لا يقرأ
 * `task_contributions` إطلاقًا — «تستمرّ عاديًّا» يعني تُترَك كما هي بالحرف.
 */
class TaskOwnershipTransfer
{
    /**
     * ينقل ملكيّة مهامّ `$leaving` المفتوحة **داخل كيان عضويّته المنتهية وحده**
     * إلى مَن فوقه في نفس تلك العضويّة (`upline_id`).
     *
     * وبلا أبلاين (القمّة نفسها، أو سلسلةٌ منقطعة) لا يُنقَل شيء — فلا نقل
     * ملكيّةٍ إلى مجهول، والقرار حينها بشريّ خارج نطاق هذا الملفّ.
     */
    public function toUpline(Membership $leaving): int
    {
        $upline = $leaving->upline_id ? Membership::query()->find($leaving->upline_id) : null;

        if (! $upline || ! $leaving->entity_id) {
            return 0;
        }

        return Task::query()
            ->where('owner_id', $leaving->user_id)
            ->where('entity_id', $leaving->entity_id)
            ->whereIn('status', TaskStatus::OPEN)
            ->update(['owner_id' => $upline->user_id]);
    }
}
