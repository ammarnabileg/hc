<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Governorate;
use App\Services\Admin\System\CountryDataSync;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 🌍 بيانات الدول في **مسار الإنتاج** (2.5-ج · 12.7-د).
 *
 * البند صريح: **«مطلوب كلّ دول العالم ومحافظاتها كاملة»** ومصدرها المعتمَد
 * `dr5hn`. وكانت القاعدة تخرج من التنصيب بـ**4 دول ومحافظةٍ واحدة** لأنّ تلك
 * الأربع بذرةُ **عرض** (`AvailabilityDemoSeeder` — دولٌ بتوقيتات مختلفة لاختبار
 * «الخامسة صباحًا»)، ولا بذرةَ إنتاجٍ للدول أصلًا. فقائمة الدولة في التسجيل
 * كانت أربعة أسطر، والمحافظة سطرًا واحدًا.
 *
 * ⛔ **ولا تُكتَب هنا دولةٌ بيد.** الزرع يمرّ على **نفس آليّة 12.7-د حرفيًّا**:
 *    `import()` ⟵ `check()` (جدول الفروق) ⟵ `merge()` ⟵ **التحقّق** الذي يقارن
 *    عدد المستخدمين المرتبطين قبل/بعد ويُفجِّر المعاملة عند أيّ فقد. فلا بابَ
 *    ثانٍ للبيانات بقواعد أرخى، ولا قاعدةَ من قواعد الدمج الثلاث تُلتَفّ عليها.
 *
 * ⛔ **ولا شبكة هنا.** التنصيب قد يقع خلف جدارٍ ناريّ أو في CI بلا خروج، وبذرةٌ
 *    تعتمد على مضيفٍ خارجيّ تجعل التنصيب رهينةَ توفّره. فالنسخة **مثبَّتة في
 *    الحزمة** (`database/data/countries.json`)، مولَّدةً من المصدر بالآليّة
 *    نفسها عبر `php artisan countries:check-source --force --dump=…`.
 *    والتحديث بعد التنصيب يبقى **قرار المالك** من تاب «بيانات الدول» — لم
 *    يتغيّر شيء من ذلك.
 *
 * 🔁 **ومعادٌ بلا أثر:** التشغيل الثاني يجد صفر فروق فلا يضيف ولا يعدّل حرفًا،
 *    ولا يمسّ ما عدّله المالك بيده (الاسم العربيّ · الإظهار/الإخفاء).
 *
 * ⚖️ **الإسناد (ODbL v1.0):** مذكورٌ في الملفّ المزروع وفي إعداد
 *    `countries.attribution` الذي تعرضه **صفحة التسجيل العامّة** — حيث تُستهلَك
 *    البيانات فعلًا — لا في تاب الإعدادات وحده.
 */
class CountriesSeeder extends Seeder
{
    public function run(): void
    {
        $sync = app(CountryDataSync::class);
        $path = database_path('data/countries.json');

        if (! is_file($path)) {
            // ملفٌّ ناقص لا يُسقِط التنصيب كلّه، لكنّه يُقال بصوتٍ عالٍ:
            // الصمت هنا يعني قوائم تسجيلٍ فارغة يكتشفها المستخدم لا نحن.
            $this->command?->warn('    نسخة الدول مش موجودة في '.$path.' — القوائم هتفضل زيّ ما هي. ولّدها بـ: php artisan countries:check-source --force --dump=database/data/countries.json');

            return;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            throw new RuntimeException('نسخة الدول المثبَّتة مش JSON صالح: '.$path);
        }

        // نفس الباب الذي يدخل منه المصدر من الشبكة — بكلّ تحقّقاته
        $snapshot = $sync->import($payload);
        $snapshot = $sync->check($snapshot);

        $diff = $sync->diff($snapshot);

        /*
         | ⭐ **المضاف وحده يُدمَج آليًّا.**
         |
         | · «محذوف» لا يُختار أبدًا: المحافظة لا تُخفى أبدًا (قاعدة مالك)،
         |   والدولة ذات المستخدمين محميّة — والزرع ليس مكان قرارٍ كهذا.
         | · «معدَّل» لا يُختار كذلك: إعادة تسمية أو تغيير مفتاح هاتفٍ قرارُ
         |   المالك من الشاشة بعد أن يرى الفرق بعينه (12.7-د)، وبذرةٌ تفرضها
         |   تدهس ما عدّله بيده في كلّ `db:seed`.
         */
        $keys = array_map(fn (array $row) => (string) $row['key'], $diff['added']);

        if ($keys === []) {
            $this->command?->info('بيانات الدول: مطابِقة للنسخة المثبَّتة — مافيش حاجة اتغيّرت.');

            return;
        }

        // والتحقّق داخل `merge()` هو الحارس: أيّ فقدٍ لارتباط مستخدم ⟵ ارتداد كامل
        $report = $sync->merge($snapshot, $keys);

        $this->command?->info(
            'بيانات الدول من المصدر ('.$snapshot->source.'): مضاف '.$report['added']
            .' · معدَّل '.$report['updated']
            .' · مخفيّ '.$report['hidden']
            .' · محميّ من الحذف '.count($report['protected'])
            .' ⟵ الإجماليّ '.Country::count().' دولة و'.Governorate::count().' محافظة.',
        );
    }
}
