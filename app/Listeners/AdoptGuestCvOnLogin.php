<?php

namespace App\Listeners;

use App\Http\Controllers\Trainee\CvController;
use App\Models\User;
use App\Services\Library\CvBuilder;
use Illuminate\Auth\Events\Login;

/**
 * مسودّة الزائر تعبر لحظة إنشاء الحساب (الدستور 21.2-ج).
 *
 * «منشئ CV بقالبٍ واحد مجّانيّ **بلا تسجيل**، **والتحميل يطلب إنشاء حساب**» —
 * فالأداة بابُ اكتسابٍ لا هديّة، وقيمتها كلّها في الخطوة الأخيرة: أن يجد الداخلُ
 * ما كتبه زائرًا في حسابه الجديد. ولولا هذا لكان الطلبُ عقوبةً: يملأ سيرته كاملة
 * ثمّ يُطلَب منه حسابٌ ليجد بعده **صفحةً بيضاء** — فيُغلق البابُ على من دخل منه.
 *
 * ⭐ **لماذا حدث الدخول لا المتحكّم؟** لأنّ `Auth::login()` في متحكّم المصادقة هو
 * اللحظة الوحيدة التي يجتمع فيها الطرفان: جلسةُ الزائر لم تُمسَح بعد، والحساب صار
 * موجودًا. وهو نفس الموضع الذي تثبَّت فيه وجهةُ الدعوة (`SettleGrowthOnLogin`) —
 * فلا نزرع نداءً ثانيًا في متحكّمٍ تحت يد غيرنا.
 *
 * وأيّ دخولٍ لا التسجيل وحده: من ملأ سيرته زائرًا ثمّ **دخل على حسابٍ قديم**
 * أَولى بها كذلك — والحارس في `CvBuilder::adoptGuestDraft()` يمنع طمس سيرةٍ
 * مكتوبة، والمفتاح يُسحَب (`pull`) فلا يُنقَل مرّتين.
 */
class AdoptGuestCvOnLogin
{
    public function __construct(private readonly CvBuilder $builder) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $session = request()?->hasSession() ? request()->session() : null;

        if (! $session) {
            return;
        }

        $draft = (array) $session->pull(CvController::GUEST_KEY, []);

        if ($draft === []) {
            return;
        }

        $this->builder->adoptGuestDraft($user, $draft);
    }
}
