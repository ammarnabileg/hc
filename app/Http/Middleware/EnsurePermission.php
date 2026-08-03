<?php

namespace App\Http\Middleware;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس المسارات: `permission:tasks.approve`.
 * الرفض 403 — والعناصر التي لا يملكها المستخدم تُخفى من الواجهة أصلًا (2.15-أ-7).
 *
 * **دلالة الفاصلة = «أيٌّ من»** (`permission:complaints.list,complaints.view`) —
 * وهي مقصودة للمسار الذي يقبل أكثر من طريقٍ للوصول.
 *
 * ⭐ لكنّها كانت **بابًا خلفيًّا داخل اللوحة**: مسار `admin/guidance` محروسٌ بـ
 * `announcements.list,announcements.view`، و`announcements.view@SELF` صلاحيّةُ
 * **قراءةٍ شخصيّة** يحملها كلّ متدرّب («استقبال منشورات التعليمات كفيد» — 12.2.2)،
 * فيكفي أضعفُ المفتاحين لفتح شاشةِ إدارة. القاعدة الآن:
 *
 *   داخل `admin.*` — إن كان في المجموعة **مفتاحٌ إداريّ** فهو وحده الذي يفتح،
 *   وتسقط منها مفاتيحُ الصفحات العامّة. فالمفتاح الإداريّ (`announcements.list`)
 *   هو الشرط، لا أضعف ما في السطر.
 *
 * وإن لم يكن في المجموعة أيّ مفتاح إداريّ (مسار إدارةٍ محروسٌ بمفتاحٍ عامّ وحده)
 * تبقى الدلالة كما هي، ويظلّ **باب اللوحة** (`admin.panel`) هو الحارس الأوّل.
 *
 * ⭐⭐ **والنطاق يُقيَّم على الطلب لا على المبدأ (12.2.1-ب):** كان الحارس ينادي
 * `allows($user, $key)` **بلا هدف** مهما كان في المسار من هدف، و`covers()` تُرجع
 * `true` حين لا هدف — فكانت حمايةُ النطاق كلّها **تسقط عند الباب**: صاحب
 * `org_chart.view@SELF` يفتح `/volunteer/org/node/{membership}` لعضويّةٍ أجنبيّة
 * ويقرأ بياناتها. فصار الهدف — حين يحمله المسار — يصل إلى التقييم فعلًا.
 */
class EnsurePermission
{
    public function __construct(private readonly AccessEngine $access) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $target = $this->targetOf($request);

        foreach ($this->effectiveKeys($request, $permissions) as $permission) {
            if ($this->access->allowsOnRecord($user, $permission, $target)) {
                return $next($request);
            }
        }

        abort(403, 'ليس لديك صلاحيّة الوصول لهذه الصفحة.');
    }

    /**
     * ⭐ هدف الطلب = **آخر** نموذجٍ مربوطٍ في بارامترات المسار (الأخصّ)، بشرطين:
     * أن يكون **موضوع المسار** (انظر `isSubject`)، وأن يكون **قابلًا للقياس على
     * سلّم النطاقات**.
     *
     * ولماذا شرطُ القياس؟ لأنّ النطاقات الستّة معرَّفة في 12.2.1-ب على **الأشخاص
     * والكيانات**: «نفسه · داونلاينه · مَن تحته · الكيان · المسار». فالسجلّ الذي
     * يعرّف **صاحبه** (`user_id`) أو **كيانه** (`entity_id`) — ومعه `User` و
     * `Membership` و`Entity` أنفسها — يُقاس. أمّا `owner_id` وحده فهو في هذا
     * المستودع **مُنشِئ السجلّ** لا موضوعه (حرب تركيز · اجتماع)، والفعل فيه فعلُ
     * **مشارِك** لا فعلُ اطّلاعٍ على بيانات غيره — فقياسه بـSELF يقلب المعنى ويمنع
     * الانضمام لحرب غيرك. وما لا يُقاس هنا يبقى على حارسه في المجال وعلى
     * `ScopeFilter` في بيانات القوائم.
     *
     * والحدّ آمنٌ في الاتّجاهين: لا نُمرّر هدفًا لا معنى للنطاق عليه، ولا نترك
     * هدفًا **له** معنًى بلا تقييم.
     */
    private function targetOf(Request $request): ?Model
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        $found = null;

        foreach ($route->parameters() as $name => $parameter) {
            if ($parameter instanceof Model && $this->isSubject((string) $name, $parameter) && $this->isScopable($parameter)) {
                $found = $parameter;
            }
        }

        return $found;
    }

    /**
     * ⭐ **الهدف هو موضوع المسار لا كلّ سجلٍّ مربوطٍ فيه.**
     *
     * والعلامة اسمُ البارامتر: السجلّ الذي يُفتَح يُسمّى باسم نموذجه (`{user}` ·
     * `{membership}` · `{entity}` · `{task}`)، أمّا المربوط باسمٍ آخر فهو **طرفٌ
     * داخل الإجراء** لا سجلٌّ يُطَّلع عليه — `{opponent}` في «تحدَّ فلانًا» و`{party}`
     * في «تواصل مع طرف التحكيم». وقياس النطاق على الخصم يقلب المعنى: يمنع صاحبَ
     * `wars_matches.create@SELF` أن يتحدّى أحدًا لأنّ الخصم ليس هو.
     */
    private function isSubject(string $parameter, Model $model): bool
    {
        return $parameter === Str::snake(class_basename($model));
    }

    private function isScopable(Model $model): bool
    {
        if ($model instanceof User || $model instanceof Membership || $model instanceof Entity) {
            return true;
        }

        $attributes = $model->getAttributes();

        return array_key_exists('user_id', $attributes) || array_key_exists('entity_id', $attributes);
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function effectiveKeys(Request $request, array $permissions): array
    {
        if (count($permissions) < 2 || ! $this->insidePanel($request)) {
            return $permissions;
        }

        $administrative = array_values(array_intersect($permissions, $this->access->adminPermissionKeys()));

        return $administrative === [] ? $permissions : $administrative;
    }

    private function insidePanel(Request $request): bool
    {
        $name = (string) $request->route()?->getName();
        $prefix = (string) config('access.panel.route_prefix', 'admin.');

        return str_starts_with($name, $prefix) || $request->is('admin', 'admin/*');
    }
}
