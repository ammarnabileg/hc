<?php

namespace App\Services\Volunteer\Objections;

use App\Models\Objection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * مكتب «الاعتراضات المصعَّدة إليّ» (الدستور 24.4-8).
 *
 * لماذا خدمة مستقلّة؟ لأنّ سؤالَي «مَن يرى هذا الاعتراض؟» و«مَن يبتّ فيه؟»
 * لا يجوز أن يُجابا في القالب ولا في الكنترولر — فالإجابة لو تكرّرت اختلفت،
 * ولو اختلفت انفتح باب: «مَن عنده `objections.list@ALL` يبتّ في اعتراض غيره».
 *
 * القاعدة الواحدة (24.4-8 · 13.4-ط):
 *  - **الرؤية = المكتب.** لا يرى الاعتراضَ إلّا مَن هو `current_handler_id`
 *    عليه فعلًا. والمسؤول المباشر أوّلُ مَن يقع على مكتبه، ومَن فوقه لا يراه
 *    إلّا **بعد** أن يصعد إليه — وهذا معنى «ومَن فوقه بالتصعيد» حرفيًّا.
 *    فلا سلطة عابرة للمكاتب ولا للكيانات مهما اتّسع نطاق المفتاح.
 *  - **البتّ = المكتب + الحياة.** لا يبتّ أحدٌ في اعتراضٍ ليس على مكتبه،
 *    ولا في اعتراضٍ أُغلِق (القرار لا يُعاد).
 */
class ObjectionDesk
{
    public function __construct(private readonly ObjectionService $service) {}

    /**
     * اعتراضات مكتبي — الأساس الذي تُبنى عليه الشاشة والعدّادات معًا،
     * فلا يظهر في العدّاد ما لا يظهر في القائمة.
     */
    public function query(User $user): Builder
    {
        return Objection::query()->where('current_handler_id', $user->id);
    }

    /** هل هذا الاعتراض على مكتبي أصلًا؟ (الرؤية) */
    public function mayView(User $user, Objection $objection): bool
    {
        return (int) $objection->current_handler_id === (int) $user->id;
    }

    /**
     * هل أبتّ فيه؟ (الردّ · التصعيد · القبول والعكس · الرفض)
     * على مكتبي **وساريًا** — والمغلق للقراءة فقط.
     */
    public function mayDecide(User $user, Objection $objection): bool
    {
        return $this->mayView($user, $objection) && $this->service->isActive($objection);
    }

    /** الحصر على الخادم: كلّ فعل بتٍّ يمرّ من هنا أوّلًا */
    public function authorizeDecision(User $user, Objection $objection): void
    {
        abort_unless(
            $this->mayView($user, $objection),
            403,
            setting('volunteer_rep.objection_desk.authorize_decision_1', 'الاعتراض ده على مكتب غيرك.'),
        );

        abort_unless(
            $this->service->isActive($objection),
            403,
            setting('volunteer_rep.objection_desk.authorize_decision_2', 'الاعتراض ده اتقفل — والقرار لا يُعاد.'),
        );
    }
}
