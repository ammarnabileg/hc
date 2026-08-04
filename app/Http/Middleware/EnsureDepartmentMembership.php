<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Services\Volunteer\Org\DepartmentScope;
use App\Support\Access\AccessEngine;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ **شاشة «قسمي» تُحرَس بالعضويّة لا بمشي النطاق** (24.4-7).
 *
 * ⚠️ وهذا **ليس نقضًا لأ-3** ولا «قاعدة كيانٍ خاصّة» تحلّ محلّ المحرّك — بل هو
 * تفريقٌ بين شاشتين مختلفتَي **المصدر**:
 *
 *  · **«الهيكل التنظيميّ»** (`/volunteer/org/node/{membership}`) شجرةٌ تُمشى
 *    بسلسلة الإشراف: مَن فوقي ومَن تحتي. فحقّها **بالنطاق** — يبقى على
 *    `permission:org_chart.view` بالهدف كما أصلحه أ-3، ولا يُلمَس.
 *
 *  · **«قسمي»** (24.4-7) نصُّها: «**مَن يراها: كلّ عضو في الكيان — القسم كاملًا
 *    حتى لو كنتُ في فرعيّ**»، وبوب-أب الكارت جزءٌ منها («ضغطة كارت ⟵ بوب-أب
 *    ملفّ عضو مختصر»). فمصدر الحقّ فيها **عضويّة القسم** لا سلسلة الإشراف:
 *    الزميلان في قسمٍ واحد ليس أحدهما فوق الآخر، ومع ذلك يعرف كلٌّ منهما زميله
 *    بنصّ الدستور. وبعد أن صار النطاق يُقاس على الطلب (أ-3) صار
 *    `org_chart.view@SELF` — سقف الكوردنيتور المنصوص في 12.2.3-ب-16 — يعني
 *    «نفسه» حرفيًّا، فأُغلقت الشاشة في وجه أصحابها أنفسهم: الكوردنيتور لم يعد
 *    يفتح بوب-أب زميله ولا يطلب رقمه (13.4-م-2).
 *
 * فالحارس هنا يجمع الشرطين بلا أن يُرخي أيًّا منهما:
 *  1) **مفتاح الشاشة شرطٌ أوّل** — مَن لا يملك `org_chart.view` أصلًا لا يفتحها
 *     (12.2.1)، فلا بابَ خلفيًّا يلتفّ على الصلاحيّة باسم العضويّة.
 *  2) ثمّ **إمّا** نطاقُه يغطّي الهدف فعلًا (أبلاين مخوَّل · أدمن) — وهذا
 *     **المحرّك نفسه** بلا تعديل — **أو** الهدف **داخل كيان المشاهِد** فهو
 *     زميله بنصّ 24.4-7.
 *
 * ومَن هو خارج القسم ولا يغطّيه نطاقُه ⟵ **403**؛ فالبيانات تبقى محصورة بالكيان.
 *
 * ⚠️ ولا يمسّ هذا **13.4-م** في شيء: بيانات التواصل تبقى مقنّعة، و«اطلب إظهار
 * الرقم» طلبٌ على **البيانات** لا على الشخص، ويقرّرها `ContactVisibility` وحدها
 * بعد المرور من هنا. الحارس يقول «هذه شاشتك»، لا «كلّ ما فيها مكشوف لك».
 */
class EnsureDepartmentMembership
{
    public function __construct(
        private readonly AccessEngine $access,
        private readonly DepartmentScope $scope,
    ) {}

    public function handle(Request $request, Closure $next, string $permission = 'org_chart.view'): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // (1) مفتاح الشاشة أوّلًا — بلا نظرٍ للهدف: العضويّة لا تصنع صلاحيّة
        abort_unless($this->access->allows($user, $permission), 403, (string) setting('volunteer_org.membership_guard.handle_msg', 'ليس لديك صلاحيّة الوصول لهذه الصفحة.'));

        $target = $request->route('membership');

        if (! $target instanceof Membership) {
            return $next($request);
        }

        // (2-أ) نطاقُه يغطّي هذه العضويّة فعلًا ⟵ المحرّك كما هو (12.2.1-ب · أ-3)
        if ($this->access->allowsOnRecord($user, $permission, $target)) {
            return $next($request);
        }

        // (2-ب) أو الهدف داخل كيان المشاهِد ⟵ زميلٌ في القسم بنصّ 24.4-7
        if ($this->sharesDepartment($request, $target)) {
            return $next($request);
        }

        abort(403, (string) setting('volunteer_org.membership_guard.handle_denied', 'ده مش عضو في قسمك.'));
    }

    /**
     * هل الهدف داخل **جذر** كيانٍ يحمل المشاهِد فيه عضويّةً حيّة؟
     *
     * والقياس على **الجذر** لا على الكيان المباشر لأنّ النصّ يقول «القسم كاملًا
     * حتى لو كنتُ في فرعيّ» — فعضو «التصميم» يرى عضو «المونتاج» لأنّ القسم واحد.
     */
    private function sharesDepartment(Request $request, Membership $target): bool
    {
        $entity = $target->entity;

        if (! $entity) {
            return false;
        }

        $root = $this->scope->rootOf($entity);

        return $this->scope->rootsFor($request->user())->has($root->id);
    }
}
