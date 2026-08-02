<?php

namespace App\Services\Library;

use App\Models\Cv;
use App\Models\CvTemplate;
use App\Models\User;

/**
 * لحظة الخصم وأثر القالب في المخرَج (الدستور 9).
 *
 * نصّ الدستور صريحٌ في ترتيب اللحظات:
 *  1) **معاينة مجّانيّة بالبيانات مع علامة مائيّة = لوجو المنصّة (أو اسمها) قبل الخصم.**
 *  2) **الاستخراج النهائيّ: PDF متوافق مع ATS، ويُخصَم عدد تذاكر القالب.**
 *
 * فكان الخصم يقع عند **اختيار** القالب — أي قبل أن يرى المستخدم مخرَجًا أصلًا —
 * وكانت المعاينة نسخةً نظيفة قابلة للطباعة بلا علامة، فسقط الشرطان معًا.
 *
 * وهنا نضبطهما: الاختيار مجّانيّ، والمعاينة موسومة ما دام القالب غير مدفوع،
 * والخصم **ذرّيّ** لحظة الاستخراج النهائيّ وبتأكيد صريح — والخصم نفسه يبقى
 * في `CvBuilder::purchase()` كما هو (مثبَتُ السلامة) فلا يُعاد بناؤه هنا.
 */
class CvExport
{
    public function __construct(
        private readonly CvBuilder $builder,
        private readonly PageWatermark $watermark,
    ) {}

    /** القالب المختار للسيرة — وإن غاب فالمجّانيّ (لا «كلاسيك» محروق) */
    public function template(Cv $cv): ?CvTemplate
    {
        $template = $cv->cv_template_id ? CvTemplate::find($cv->cv_template_id) : null;

        return $template && $template->is_active ? $template : $this->builder->freeTemplate();
    }

    /**
     * قرار الاستخراج: نظيفٌ بخصم، أم معاينةٌ موسومة قبله؟
     *
     * @return array{
     *     clean: bool, template: ?CvTemplate, price: float, balance: float,
     *     data: array, notice: ?string, watermark: ?array
     * }
     */
    public function resolve(User $user, Cv $cv, array $data, bool $confirmed): array
    {
        $template = $this->template($cv);
        $price = $template?->priceTickets() ?? 0.0;
        $owned = $template === null || $this->builder->owns($template, $data);

        if ($owned) {
            return $this->clean($template, $price, $this->builder->ticketBalance($user), $data);
        }

        $balance = $this->builder->ticketBalance($user);

        // ⭐ لا خصم بلا تأكيد صريح: المعاينة الموسومة أوّلًا، والرصيد قبل/بعد ظاهر (24.5)
        if (! $confirmed) {
            return $this->marked($template, $price, $balance, $data, str_replace(
                [':price', ':before', ':after'],
                [(string) $price, (string) $balance, (string) max(0, $balance - $price)],
                (string) setting('cv.export.confirm_notice', 'دي معاينة بعلامة مائيّة. التحميل النهائيّ بالقالب ده هيخصم :price تذكرة (رصيدك :before ⟵ :after).'),
            ));
        }

        $result = $this->builder->purchase($user, $template);

        if (! $result['ok']) {
            return $this->marked($template, $price, $result['balance'], $data, $result['message']);
        }

        // القالب صار ملكه — والبيانات تُحفَظ بمعرفة المتحكّم (كتابةٌ واحدة)
        $data['purchased_templates'] = array_values(array_unique(array_merge(
            array_map('intval', (array) ($data['purchased_templates'] ?? [])),
            [$template->id],
        )));

        return $this->clean($template, $price, $result['balance'], $data);
    }

    /**
     * طبقة العلامة المائيّة — لوجو المنصّة أو اسمها (9)، من `PageWatermark`
     * ولا تُكتَب ثانيةً هنا.
     *
     * @return array{logo:?string, text:string, repeat:int, opacity:int}
     */
    public function mark(): array
    {
        return $this->watermark->platformMark() + [
            'repeat' => $this->watermark->repeatCount(),
            'opacity' => max(1, min(60, (int) setting('cv.watermark.opacity_percent', 10))),
        ];
    }

    private function clean(?CvTemplate $template, float $price, float $balance, array $data): array
    {
        return [
            'clean' => true,
            'template' => $template,
            'price' => $price,
            'balance' => $balance,
            'data' => $data,
            'notice' => null,
            'watermark' => null,
        ];
    }

    private function marked(?CvTemplate $template, float $price, float $balance, array $data, string $notice): array
    {
        return [
            'clean' => false,
            'template' => $template,
            'price' => $price,
            'balance' => $balance,
            'data' => $data,
            'notice' => $notice,
            'watermark' => $this->mark(),
        ];
    }
}
