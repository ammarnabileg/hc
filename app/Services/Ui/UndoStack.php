<?php

namespace App\Services\Ui;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * تراجُع خلال 5 ثوانٍ بعد الأفعال القابلة للتراجع (2.15-د).
 *
 * > «التأكيد للأفعال غير القابلة للتراجع فقط؛ وما عداها يُنفَّذ فورًا مع
 * > **Undo لمدّة 5 ثوانٍ** في الـToast.»
 *
 * **كيف؟** نلتقط **صورة الحقول قبل التغيير** ونحفظها في الكاش بمهلة الإعداد
 * `ux.undo.seconds`. التراجع يعيد الحقول كما كانت — لا أكثر. ولماذا لا نخزّن
 * «عمليّة عكسيّة» عامّة؟ لأنّها ستحتاج كودًا قابلًا للتنفيذ في الكاش وهو باب
 * خطر، والصورة أبسط وأأمن وتكفي كلّ الأفعال القابلة للتراجع فعلًا.
 */
class UndoStack
{
    /** مدّة التراجع — إعداد لا رقم محروق (2.13) */
    public function seconds(): int
    {
        return max(1, (int) setting('ux.undo.seconds', 5));
    }

    /**
     * التقاط الحالة قبل الفعل وإرجاع رمز التراجع.
     *
     * @param  array<int,string>  $fields  الحقول التي سيغيّرها الفعل
     */
    public function capture(User $actor, Model $model, array $fields, string $label): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->key($token), [
            'user_id' => $actor->id,
            'model' => $model::class,
            'id' => $model->getKey(),
            'before' => collect($fields)->mapWithKeys(fn ($f) => [$f => $model->getAttribute($f)])->all(),
            'label' => $label,
        ], now()->addSeconds($this->seconds() + 2));

        return $token;
    }

    /**
     * تنفيذ التراجع — ويرجع رسالةً للـToast، أو null لو انتهت المهلة.
     *
     * @return array{ok:bool,message:string}
     */
    public function undo(User $actor, string $token): array
    {
        $payload = Cache::pull($this->key($token));

        if (! is_array($payload)) {
            // ماذا حدث + ماذا تفعل (2.17-ب)
            return ['ok' => false, 'message' => 'مهلة التراجع خلصت — تقدر تعدّل من الشاشة عادي.'];
        }

        if ((int) $payload['user_id'] !== (int) $actor->id) {
            return ['ok' => false, 'message' => 'الفعل ده مش بتاعك — ارجع للشاشة وجرّب من هناك.'];
        }

        /** @var class-string<Model> $class */
        $class = $payload['model'];
        $model = $class::query()->find($payload['id']);

        if (! $model) {
            return ['ok' => false, 'message' => 'العنصر مبقاش موجود — مفيش حاجة نرجّعها.'];
        }

        $model->forceFill((array) $payload['before'])->save();

        return ['ok' => true, 'message' => 'رجّعناها زيّ ما كانت ✓'];
    }

    private function key(string $token): string
    {
        return 'ui:undo:'.$token;
    }
}
