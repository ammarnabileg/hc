<?php

use App\Models\Governorate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هويّة المحافظة = **اسمها الإنجليزيّ**، لا العربيّ (12.7-د · 2.11-د).
 *
 * الجدول وُلد بـ`unique(country_id, name_ar)`، بينما آليّة المصدر كلّها تعرّف
 * المحافظة بـ`slug(name_en)` — «فتغيير الاسم العربيّ يبقى **تعديلًا** لا حذفًا
 * وإضافة». فالمفتاحان يتنازعان، وثمنُ النزاع مقيس:
 *
 *  1) **سبع محافظات من المصدر لا تدخل أبدًا** — لأنّ اسمها العربيّ يطابق اسم
 *     محافظةٍ أخرى في نفس الدولة (تورّي/فرنسا-لوار/كلايبيدا/بانيفيزيس/فافو/
 *     جزر البليار/نافارا)، فيصطدم `updateOrCreate` بالقيد ويكتب فوق الصفّ
 *     القائم بدل إنشاء صفٍّ جديد.
 *  2) **وجدول الفروق لا يهدأ**: تلك السبع تظهر «مضافة» في كلّ فحص إلى الأبد،
 *     وكلّ دمجٍ يقلب `name_en` بين الاسمين المتنافسين. فالمالك يرى فروقًا
 *     كاذبة دائمًا، وقاعدة «الدمج يهدأ حين يطابق المصدر» تسقط.
 *
 * فالقيد ينتقل إلى `(country_id, name_en)` — نفس ما تعتمده الآليّة.
 *
 * ⛔ **ولا صفَّ يُحذَف ولا يُخفى ولا يُمَسّ ارتباطُ مستخدم**: هذه هجرة **فهارس**
 *    لا بيانات. والقيد الجديد يُبنى بعد التحقّق من عدم وجود تكرارٍ فيه؛ ولو وُجد
 *    (بياناتٌ قديمة يدويّة) **تُفَضّ التسمية بلاحقةٍ رقميّة ولا يُحذَف صفّ**.
 *
 * ⚠️ وتاريخها **بعد آخر هجرة في الشجرة** عن قصد: هجرةٌ تُؤرَّخ قبل ما تعدّله
 *    تعمل على تنصيبٍ قائم ثمّ يبطُل أثرُها على تنصيبٍ جديد لأنّها تسبق الجدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('governorates')) {
            return;
        }

        $this->deduplicate();

        Schema::table('governorates', function (Blueprint $table) {
            // الاسم الافتراضيّ للقيد القديم كما ولّده الباني
            $table->dropUnique('governorates_country_id_name_ar_unique');
        });

        Schema::table('governorates', function (Blueprint $table) {
            $table->unique(['country_id', 'name_en']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('governorates')) {
            return;
        }

        Schema::table('governorates', function (Blueprint $table) {
            $table->dropUnique('governorates_country_id_name_en_unique');
        });

        Schema::table('governorates', function (Blueprint $table) {
            $table->unique(['country_id', 'name_ar']);
        });
    }

    /**
     * فضّ أيّ تكرارٍ في `(country_id, name_en)` قبل بناء القيد — **بالتسمية لا
     * بالحذف**. صفٌّ مرتبطٌ بمستخدمٍ لا يجوز أن يختفي لأجل فهرس.
     */
    private function deduplicate(): void
    {
        $seen = [];

        Governorate::query()->orderBy('id')->each(function (Governorate $row) use (&$seen) {
            $name = trim((string) $row->name_en) !== '' ? trim((string) $row->name_en) : trim((string) $row->name_ar);
            $key = $row->country_id.'|'.mb_strtolower($name);

            if (! isset($seen[$key])) {
                $seen[$key] = 1;

                if ($name !== $row->name_en) {
                    $row->forceFill(['name_en' => $name])->saveQuietly();
                }

                return;
            }

            $seen[$key]++;
            $row->forceFill(['name_en' => $name.' ('.$seen[$key].')'])->saveQuietly();
        });
    }
};
