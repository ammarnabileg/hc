<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ تقليم كتالوج قوالب الـCV إلى التصاميم الموجودة فعلًا (9).
 *
 * كان الكتالوج يعرض خمسة أسماء وتحتها تصميمان اثنان فقط
 * (`resources/views/cv/templates/classic.blade.php` و`modern.blade.php`):
 *
 *   • «تنفيذيّ»  ⟵ `view_path = modern`   ⇒ مطابق لـ«مودرن» بايتًا ببايت
 *   • «مبدع»    ⟵ `view_path = modern`   ⇒ مطابق لـ«مودرن»، **وبسعرٍ أعلى بتذكرة**
 *   • «أكاديميّ» ⟵ `view_path = classic`  ⇒ مطابق لـ«كلاسيك»
 *
 * فمن دفع تذاكره في «مبدع» استلم مخرَج «مودرن» الأرخص نفسه: وعدٌ مدفوع بلا
 * منتجٍ خلفه. والقرار تقليم الكتالوج لا اختراع ثلاثة تصاميم وهميّة.
 *
 * وهذه الترحيلة **لا تبتر** الصفوف القائمة: كلّ سيرةٍ تشير إلى اسمٍ محذوف
 * تُنقَل إلى القالب الذي كانت **تُعرَض به أصلًا**، فلا يتغيّر شكل مخرَجها ولا
 * حرف. وكذلك حقوق الشراء المخزَّنة في `cvs.data->purchased_templates`:
 * من دفع في «مبدع» يملك «مودرن» بعدها — وهو ما كان يستلمه فعلًا — فلا يضيع
 * ما دفعه. ولولا هذا النقل لأفرغ `nullOnDelete` عمودَ القالب بصمت وسقطت
 * السِّيَر إلى `classic` عبر تراجع `CvBuilder::sheet()`.
 */
return new class extends Migration
{
    /** الاسم المحذوف ⟵ اسم القالب الذي كان يُعرَض به فعلًا */
    private const REMAP = [
        'تنفيذيّ' => 'مودرن',
        'أكاديميّ' => 'كلاسيك',
        'مبدع' => 'مودرن',
    ];

    public function up(): void
    {
        foreach (self::REMAP as $removed => $target) {
            $removedId = DB::table('cv_templates')->where('name', $removed)->value('id');
            $targetId = DB::table('cv_templates')->where('name', $target)->value('id');

            if (! $removedId || ! $targetId) {
                continue;
            }

            // 1) السِّيَر التي تختار القالب المحذوف تنتقل إلى تصميمه نفسه
            DB::table('cvs')->where('cv_template_id', $removedId)->update([
                'cv_template_id' => $targetId,
            ]);

            // 2) حقوق الشراء المخزَّنة داخل JSON — من دفع لا يخسر ما دفعه
            $this->remapPurchases((int) $removedId, (int) $targetId);

            DB::table('cv_templates')->where('id', $removedId)->delete();
        }
    }

    /**
     * `purchased_templates` مصفوفة مُعرّفاتٍ داخل `cvs.data` (JSON) يقرأها
     * `CvBuilder::owns()`. تُقرَأ صفًّا صفًّا في PHP لا بدوالّ JSON الخاصّة
     * بمحرّكٍ بعينه، فتمرّ الترحيلة على SQLite وMySQL سواء.
     */
    private function remapPurchases(int $removedId, int $targetId): void
    {
        DB::table('cvs')->whereNotNull('data')->orderBy('id')->chunkById(200, function ($rows) use ($removedId, $targetId) {
            foreach ($rows as $row) {
                $data = json_decode((string) $row->data, true);

                if (! is_array($data)) {
                    continue;
                }

                $owned = array_map('intval', (array) ($data['purchased_templates'] ?? []));

                if (! in_array($removedId, $owned, true)) {
                    continue;
                }

                $owned[] = $targetId;

                $data['purchased_templates'] = array_values(array_unique(
                    array_filter($owned, fn (int $id) => $id !== $removedId),
                ));

                DB::table('cvs')->where('id', $row->id)->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    /**
     * لا تراجع: إعادة بعث ثلاثة أسماء بلا تصاميم تُرجع الوعد المدفوع بلا
     * منتج — وهو العيب نفسه الذي جاءت الترحيلة لإزالته. ومن أراد قالبًا
     * جديدًا يضيفه من شاشة الأدمن متى وُجد تصميمٌ حقيقيّ له (نصّ 9).
     */
    public function down(): void {}
};
