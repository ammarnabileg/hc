<?php

use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Services\Admin\Content\TemplateDesigner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ 12.5-ب — «**تصميم افتراضيّ جاهز لكلّ نوع شهادة** … والأدمن **يعدّله** أو
 * يستبدله بخلفيّته».
 *
 * المرصود: صفوف `certificate_templates` بـ`layers = []`. والراسم يتخطّى الطبقة
 * الفارغة، فيفتح الأدمن الراسم على **صندوقٍ خالٍ** يبدأ منه من الصفر — لا على
 * تصميمٍ يعدّله. فتُملأ هذه الصفوف مرّةً بتصميم **نوعها**.
 *
 * ⚠️ **وما لا تلمسه هذه الهجرة — وهو الأهمّ:** `certificates.template_snapshot`.
 * «تجميد نسخة التصميم (Versioning) — لو اتغيّر القالب لاحقًا تفضل القديمة
 * بشكلها» (12.5-ج). فالشهادة الصادرة ترسم من لقطتها المجمَّدة لا من صفّ القالب،
 * ولمسُ اللقطة يغيّر شكل ورقةٍ سُلِّمت **ويكسر توقيعها الرقميّ** لأنّ بصمة
 * `template_snapshot` داخلةٌ في حمولة التوقيع (`CertificateSignature::payload`)
 * — فتُوسَم شهادةٌ سارية «مزوَّرة». فلا `certificates` ولا `hash` هنا بحال.
 *
 * والصفّ الذي فيه طبقةٌ واحدة **لا يُلمَس**: قد يكون الأدمن قد أفرغه إلّا منها
 * عمدًا — والفراغ التامّ وحده هو أثر الإنشاء الناقص.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_templates') || ! Schema::hasTable('certificate_types')) {
            return;
        }

        $designer = app(TemplateDesigner::class);
        $types = CertificateType::query()->get()->keyBy('id');

        CertificateTemplate::query()->orderBy('id')->chunkById(200, function ($templates) use ($designer, $types) {
            foreach ($templates as $template) {
                if (! $designer->isBlank($template)) {
                    continue;
                }

                $template->forceFill([
                    'layers' => $designer->defaultLayers(
                        (string) ($template->language ?: 'ar'),
                        $types->get($template->certificate_type_id),
                    ),
                    // رقم النسخة يتقدّم: القالب تغيّر فعلًا، والشهادات الصادرة
                    // تحتفظ برقم نسختها المجمَّد داخل لقطتها.
                    'version' => (int) $template->version + 1,
                ])->saveQuietly();
            }
        });
    }

    /**
     * لا تراجع: إعادة القوالب إلى الفراغ تعيد العطب نفسه، وليست في ذلك
     * استعادةُ حالةٍ بل إتلافُ تصميمٍ قد يكون الأدمن عدّله بعد الترقية.
     */
    public function down(): void {}
};
