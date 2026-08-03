<?php

namespace App\Services\Growth;

use App\Models\User;
use App\Services\Ads\Consent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ **سلسلة الاكتساب المتّصلة** (21.2-ح):
 *
 *   «⭐ UTM موحّد على كلّ رابط تولّده المنصّة (دعوات · مشاركات · صور · مقالات).
 *    ⭐ **لوحة مصادر الاكتساب** في الإحصائيّات: **المصدر ⟵ التسجيل ⟵ التفعيل ⟵
 *    الشراء** — فلا يُصرَف على قناةٍ لا نعرف عائدها.»
 *
 * `UtmBuilder` يسم الروابط عند **الخروج**، وهذا الصنف يلتقط الوسم عند **الدخول**
 * ويُعبّره الوصلات الأربع. وكانت السلسلة تنقطع عند أوّل وصلة: الحدث يقرأ الـUTM
 * من الطلب الجاري، و`POST /register` بلا query — فيُسجَّل المسجّل بمصدرٍ `NULL`
 * (المقيس: `visits=2 · registered=0`). فالمصدر يُحفَظ هنا في الجلسة عند الزيارة
 * ثمّ **يُثبَّت على المستخدم** لحظة إنشائه، فيبقى بعد التفعيل وبعد الشراء.
 *
 * ⛔ **والحارس الحاكم قبل أيّ سطر (21.3-د · 2.9):** الالتقاط كلّه تحت غرض
 *    **«قياس داخليّ»** (`analytics`) — لا تحت الإعلان. فبلا موافقةٍ صريحة عليه
 *    **لا يُقرأ ولا يُكتَب شيء**: لا جلسة ولا عمود. و«الصمت ليس موافقة».
 */
class AcquisitionSource
{
    /**
     * معايير الـUTM الأربعة — **عين ما يولّده `UtmBuilder`** وما يخزّنه
     * `tracking_events`، فلا حقل مخترَع لا يذكره النصّ (21.2-ح).
     */
    public const PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'];

    /** بادئة عمود المستخدم: `utm_source` ⟵ `acquisition_utm_source` */
    public const COLUMN_PREFIX = 'acquisition_';

    /** المصدر المعلَّق بين الزيارة والتسجيل — ولا يوجد إلّا بعد الموافقة */
    public const SESSION_KEY = 'growth.acquisition';

    private ?bool $schemaReady = null;

    public function __construct(private readonly Consent $consent) {}

    /** تفعيل/إيقاف الحلقة على حدة (21.1-هـ · 2.13) */
    public function enabled(): bool
    {
        return (bool) setting('growth.acquisition.enabled', true);
    }

    /** أوّل مصدرٍ يفوز أم آخره؟ إعدادٌ لا قرارٌ محروق (2.13 · 21.2-ي «معايير UTM ومصادرها») */
    public function firstTouch(): bool
    {
        return (string) setting('growth.acquisition.attribution', 'first') !== 'last';
    }

    /**
     * ⛔ البوّابة الواحدة: **لا التقاط بلا موافقةٍ على القياس الداخليّ**.
     * وهي المكان الوحيد الذي يُسأل فيه عن الإذن، فلا يتسرّب التقاطٌ من مسارٍ نسي الفحص.
     */
    public function allowed(?User $user = null, ?Request $request = null): bool
    {
        return $this->enabled() && $this->consent->allowsAnalytics($user, $request);
    }

    /**
     * الوصلة الأولى: **الزيارة**. تُنادى مرّةً لكلّ طلبٍ يعرض صفحة.
     *
     * @return array<string,string> المصدر المعلَّق بعد هذا الطلب (فارغ = لم يُلتقَط شيء)
     */
    public function capture(?Request $request = null): array
    {
        $request ??= request();

        if (! $request instanceof Request || ! $this->allowed($request->user(), $request)) {
            return [];
        }

        $session = $request->hasSession() ? $request->session() : null;
        $held = $this->sanitize((array) ($session?->get(self::SESSION_KEY) ?? []));
        $incoming = $this->fromRequest($request);

        // «أوّل مَن دعا يفوز» ما لم يقلب المالك الإعداد إلى `last`
        if ($incoming !== [] && ! ($this->firstTouch() && $held !== [])) {
            $held = $incoming;
            $session?->put(self::SESSION_KEY, $held);
        }

        // مستخدمٌ داخلٌ ولم يُثبَّت عليه مصدر بعد (وافق متأخّرًا مثلًا) — يُثبَّت الآن
        if ($user = $request->user()) {
            $this->attach($user, $request);
        }

        return $held;
    }

    /**
     * الوصلة الثانية: **التسجيل** — المصدر يعبر الفورم ويستقرّ على الحساب.
     *
     * ولماذا من الجلسة لا من حقلٍ مخفيّ في الفورم؟ لأنّ حقلًا مخفيًّا يقبل ما
     * يكتبه المتصفّح، فيصير الالتقاط **قابلًا للتزوير وقابلًا للتخطّي**: يكفي أن
     * يُرسِل زائرٌ رافض حقولَ `utm_*` مع الفورم ليُقاس رغم رفضه. والجلسة لا
     * تُملأ أصلًا إلّا بعد الموافقة (`capture()`)، فالحارس يبقى واحدًا.
     */
    public function attach(User $user, ?Request $request = null): bool
    {
        $request ??= request();

        if (! $this->allowed($user, $request) || ! $this->schemaReady()) {
            return false;
        }

        // أوّل مصدرٍ نُسِب لهذا الحساب لا يُدهَس بمصدرٍ لاحق
        if ($this->firstTouch() && $this->sourceOf($user) !== []) {
            return false;
        }

        $source = $this->pending($request);

        if ($source === []) {
            return false;
        }

        // الأربعة تُكتَب معًا — فلا يبقى `utm_campaign` من مصدرٍ سابق فوق مصدرٍ جديد
        $columns = [];

        foreach (self::PARAMS as $param) {
            $columns[self::COLUMN_PREFIX.$param] = $source[$param] ?? null;
        }

        // `saveQuietly` — النسبة قيدُ قياسٍ لا حدثٌ يوقظ مراقبي الحساب
        $user->forceFill($columns)->saveQuietly();

        return true;
    }

    /**
     * المصدر المثبَّت على الحساب — تقرؤه الوصلتان الثالثة (التفعيل) والرابعة (الشراء).
     *
     * @return array<string,string>
     */
    public function sourceOf(User $user): array
    {
        if (! $this->schemaReady()) {
            return [];
        }

        $source = [];

        foreach (self::PARAMS as $param) {
            $value = $user->getAttribute(self::COLUMN_PREFIX.$param);

            if (is_string($value) && $value !== '') {
                $source[$param] = $value;
            }
        }

        return isset($source['utm_source']) ? $source : [];
    }

    /**
     * المصدر المعلَّق الآن: من الجلسة، وإلّا من الطلب الجاري نفسه — فمن سجّل
     * على رابطٍ يحمل وسمًا في الـquery لا يحتاج جلسةً وسيطة.
     *
     * @return array<string,string>
     */
    public function pending(?Request $request = null): array
    {
        $request ??= request();

        if (! $request instanceof Request || ! $this->allowed($request->user(), $request)) {
            return [];
        }

        $session = $request->hasSession() ? $request->session() : null;
        $held = $this->sanitize((array) ($session?->get(self::SESSION_KEY) ?? []));

        return $held !== [] ? $held : $this->fromRequest($request);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<string,string> */
    private function fromRequest(Request $request): array
    {
        $raw = [];

        foreach (self::PARAMS as $param) {
            $raw[$param] = $request->query($param);
        }

        return $this->sanitize($raw);
    }

    /**
     * تنظيف: نصوصٌ قصيرة فقط، **ولا شيء بلا `utm_source`** — لأنّه مفتاح صفّ
     * اللوحة، ووسمٌ بلا مصدر لا يجيب على «مِن أين جاء؟».
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,string>
     */
    private function sanitize(array $raw): array
    {
        $max = max(8, (int) setting('growth.acquisition.max_length', 128));
        $clean = [];

        foreach (self::PARAMS as $param) {
            $value = $raw[$param] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = mb_substr(trim($value), 0, $max);

            if ($value !== '') {
                $clean[$param] = $value;
            }
        }

        return isset($clean['utm_source']) ? $clean : [];
    }

    /** الأعمدة قد لا تكون هُجِّرت بعد على قاعدةٍ قديمة — فلا تسقط الصفحة لأجل قياس */
    private function schemaReady(): bool
    {
        return $this->schemaReady ??= Schema::hasColumn('users', self::COLUMN_PREFIX.'utm_source');
    }
}
