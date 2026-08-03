<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use Illuminate\Support\Carbon;

/**
 * 🔏 التوقيع الرقميّ للشهادة — **مصدرٌ واحد للاشتقاق والتحقّق** (8 · 8.1 · 12.5-هـ).
 *
 * الدستور يعِد في 8.1 بأنّ صفحة التحقّق «تتيح للجهات والشركات التحقّق من **صحّة**
 * وصلاحيّة أيّ شهادة». والصحّة غير الصلاحيّة: الصلاحيّة حالةٌ مخزَّنة (سارية ·
 * منتهية · ملغاة)، أمّا **الصحّة** فلا تُعرَف إلّا بإعادة اشتقاق التوقيع من بيانات
 * الشهادة نفسها ومقارنته بالمخزَّن. وكان الحقل `hash` يُكتَب ولا يقرؤه أحد —
 * فالتوقيع دعوى في الفوتر لا ضمانة، وصفٌّ بتوقيعٍ مخترَع كان يُعلَن «ساريًا
 * وبياناته مطابقة لسجلّنا».
 *
 * ثلاث قواعد يقوم عليها هذا الصنف:
 *
 * **1) بمفتاح التطبيق لا بلا مفتاح.** `hash('sha256', …)` المكشوف يقدر أيّ أحدٍ
 * يعرف الحقول الداخلة فيه أن ينتجه بنفسه — فهو بصمةُ محتوًى لا توقيعَ جهة.
 * `hash_hmac` بمفتاح `app.key` هو ما يجعل التوقيع **شهادةً من المنصّة**.
 *
 * **2) يغطّي ما يُعرَض لا الكود وحده.** الحمولة تضمّ **اللقطة المجمَّدة** (اسم
 * الحائز · اسم الشهادة · الدولة · جهة الاعتماد) و**لقطة القالب** — فتبديل اسمٍ
 * في `data_snapshot` يكسر التوقيع ولا يمرّ صامتًا. ولا تضمّ **الحالة**: الانتهاء
 * والإلغاء تغيّراتٌ مشروعة بعد الإصدار (13.4-ق) ولو دخلت الحمولة لصارت كلّ شهادةٍ
 * منتهيةٍ «مزوَّرة».
 *
 * **3) المقارنة بـ`hash_equals`.** المقارنة الحرفيّة تنتهي عند أوّل حرفٍ مختلف،
 * وفرقُ الزمن يُسرّب موضع الاختلاف فيُبنى التوقيع حرفًا حرفًا. و`==` أسوأ: مقارنة
 * نصَّين رقميَّين في PHP تجري **عدديًّا** (`'0e1' == '0e2'`)، فتوقيعٌ سداسيٌّ من
 * صيغة `0e…` يساوي غيره.
 */
class CertificateSignature
{
    /**
     * إصدار صيغة الحمولة — يدخل في التوقيع نفسه.
     * فلو تغيّرت الصيغة يومًا لم يلتبس توقيعُ صيغةٍ بتوقيع أخرى.
     */
    public const VERSION = 'v1';

    /** حالات التحقّق الثلاث — والفرق بينها معلومةٌ يحتاجها المتحقِّق */
    public const MATCH = 'match';

    public const MISMATCH = 'mismatch';

    public const UNSIGNED = 'unsigned';

    /** التوقيع الذي **يجب** أن تحمله هذه الشهادة بحسب بياناتها الآن */
    public function for(Certificate $certificate): string
    {
        return hash_hmac('sha256', $this->payload($certificate), $this->key());
    }

    /** هل التوقيع المخزَّن هو الذي تشتقّه بيانات الشهادة؟ — مقارنة آمنة زمنيًّا */
    public function matches(Certificate $certificate): bool
    {
        $stored = (string) $certificate->hash;

        if ($stored === '' || $this->key() === '') {
            return false;
        }

        return hash_equals($this->for($certificate), $stored);
    }

    /**
     * الحكم المفصَّل: مطابق · لا يطابق · بلا توقيع أصلًا.
     *
     * «بلا توقيع» ليس «لا يطابق»: الأوّل صفٌّ ما وُقِّع قطّ (استيرادٌ ناقص مثلًا)،
     * والثاني توقيعٌ **موجود** ولا تشتقّه البيانات — وهو وحده ما يشير إلى عبث.
     */
    public function verdict(Certificate $certificate): string
    {
        if ((string) $certificate->hash === '') {
            return self::UNSIGNED;
        }

        return $this->matches($certificate) ? self::MATCH : self::MISMATCH;
    }

    /**
     * يوقّع الشهادة بتوقيعها الصحيح ويحفظه.
     *
     * ⚠️ لا تُستدعى إلّا من **مسار الإصدار المعتمَد** أو من ترحيلٍ مُعلَن: توقيعُ
     * صفٍّ بعد كتابته يدويًّا يعني مباركة ما فيه، وذلك نقضٌ لغرض التوقيع.
     */
    public function seal(Certificate $certificate): Certificate
    {
        $certificate->forceFill(['hash' => $this->for($certificate)])->save();

        return $certificate;
    }

    /**
     * ⭐ **ترحيل الشهادات القائمة** (يُنادى من مايجريشن التوقيع مرّةً واحدة).
     *
     * الحقل `hash` عاش عمره كلّه بلا قارئ، فكُتِب بصيغٍ شتّى: توقيعٌ بمفتاح
     * التطبيق من `CertificateIssuer`، وبصمةٌ **بلا مفتاح** من مسار التطوّع القديم،
     * وقيمٌ مزروعةٌ في سيدرات العرض. فلو بدأ التحقّق من غير ترحيل لظهرت شهاداتٌ
     * **صحيحة** — صدرت من محرّكنا لأصحابها — بوسم «التوقيع لا يطابق»، وهو اتّهامٌ
     * باطل لا يقلّ ضررًا عن التزوير الذي نحرسه.
     *
     * فتُختَم كلّ الصفوف القائمة **مرّةً واحدة** بخطّ أساسٍ مُعلَن: ما كُتِب قبل
     * وجود المتحقِّق يُصدَّق مرّةً لأنّه كُتِب بيد الخادم نفسه، وما يُكتَب بعده
     * لا يمرّ إلّا موقَّعًا من `CertificateIssuer`. ومن هذه اللحظة أيّ صفٍّ يُدَسّ
     * أو يُعبَث بلقطته يسقط في «لا يطابق».
     *
     * @return int عدد الصفوف التي غُيِّر توقيعها
     */
    public function backfill(): int
    {
        $sealed = 0;

        Certificate::query()
            ->with('certificate_type')
            ->orderBy('id')
            ->chunkById(200, function ($certificates) use (&$sealed) {
                foreach ($certificates as $certificate) {
                    $signature = $this->for($certificate);

                    if ((string) $certificate->hash === $signature) {
                        continue;
                    }

                    $certificate->forceFill(['hash' => $signature])->saveQuietly();
                    $sealed++;
                }
            });

        return $sealed;
    }

    // ------------------------------------------------------------ الحمولة

    /**
     * الحمولة الموقَّعة — نصٌّ قانونيّ الترتيب: كلّ اشتقاقٍ لنفس الشهادة يعطيه
     * حرفًا بحرف، سواءٌ لحظة الإصدار (من الذاكرة) أو لحظة التحقّق (من الجدول).
     */
    public function payload(Certificate $certificate): string
    {
        return implode('|', [
            self::VERSION,
            (string) $certificate->code,
            (string) ((int) $certificate->user_id),
            $this->typeKey($certificate),
            $this->moment($certificate->issued_at),
            $this->digest($certificate->data_snapshot),
            $this->digest($certificate->template_snapshot),
        ]);
    }

    /**
     * مفتاح النوع لا رقمه: الأرقام تتبدّل بإعادة البذر بينما المفتاح هو هويّة
     * النوع الثابتة — والرقم يبقى مرساةً أخيرة حين يغيب الصفّ.
     */
    private function typeKey(Certificate $certificate): string
    {
        $type = $certificate->relationLoaded('certificate_type')
            ? $certificate->certificate_type
            : CertificateType::query()->find($certificate->certificate_type_id);

        return (string) ($type?->key ?: '#'.(int) $certificate->certificate_type_id);
    }

    /** لحظة الإصدار بصيغةٍ واحدة (UTC بدقّة الثانية) — فلا يغيّر التوقيعَ فرقُ منطقة */
    private function moment(mixed $moment): string
    {
        if (! $moment instanceof Carbon) {
            $moment = $moment ? Carbon::parse((string) $moment) : null;
        }

        return $moment ? $moment->clone()->utc()->format('Y-m-d\TH:i:s\Z') : '';
    }

    /**
     * بصمة اللقطة: تمرّ على JSON أوّلًا فتتساوى النسخة التي في الذاكرة لحظة
     * الإصدار مع النسخة العائدة من الجدول، ثمّ تُرتَّب مفاتيحها ترتيبًا واحدًا
     * فلا يغيّر التوقيعَ **ترتيبُ** الحقول.
     */
    private function digest(mixed $snapshot): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

        $normalized = json_decode((string) json_encode($snapshot, $flags), true);

        return hash('sha256', (string) json_encode($this->sorted($normalized), $flags));
    }

    /** ترتيب المفاتيح تنازليًّا في العمق — والقوائم تبقى بترتيبها فهو معنًى */
    private function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];

        foreach ($value as $key => $item) {
            $out[$key] = $this->sorted($item);
        }

        if (! array_is_list($out)) {
            ksort($out);
        }

        return $out;
    }

    /**
     * مفتاح التطبيق. وحين يغيب — تنصيبٌ بلا `php artisan key:generate` —
     * لا نصنع توقيعًا بمفتاحٍ فارغ يوهم بالحماية: `matches()` تردّ «لا يطابق»
     * فيُرى العطب بدل أن يُخبَّأ خلف ختمٍ مكشوف.
     */
    private function key(): string
    {
        return (string) config('app.key');
    }
}
