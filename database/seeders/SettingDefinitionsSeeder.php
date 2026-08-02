<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;

/**
 * 🏆 مسار الإنتاج للقاعدة الذهبيّة 2.13 — **تعريف الإعداد ≠ محتوى العرض**.
 *
 * كان كلّ مجال يزرع إعداداته داخل `<Area>DemoSeeder`، و`DatabaseSeeder` لا
 * يستدعي أيًّا منها؛ فالتنصيب الحقيقيّ يخرج بجدول إعدادات شبه فارغ بينما الكود
 * يقرأ ألوفَ المفاتيح — فتأخذ **الافتراضيّ المكتوب في الكود**، وهو رقمٌ محروق
 * بخطوة إضافيّة، أيْ عين ما تمنعه 2.13. لذلك يجمع هذا السيدر **تعريفات** كلّ
 * المجالات ويزرعها في مسار الإنتاج (`DatabaseSeeder` ⟵ ومنه `Installer::seed`)،
 * ويبقى في سيدرات العرض **محتوى العرض وحده** (تدريبات · مستخدمون · مقالات).
 *
 * ولماذا الاكتشاف الآليّ بدل قائمة مكتوبة؟ لأنّ قائمةً ثابتة تتقادم مع أوّل
 * مجالٍ جديد فتعود الفجوة صامتةً. هنا: أيّ ميثود عامّة اسمها ينتهي بـ`settings`
 * في أيّ `*DemoSeeder` تُعتبَر **تعريفَ إعدادات** ويُستدعى وحده — بلا لمس
 * `run()` فلا تتسرّب بيانات العرض للإنتاج.
 *
 * وقيمة المالك محفوظة: التعريف يُحدَّث، والقيمة المعدَّلة تُستَعاد بعد الزرع
 * (2.13-د) فإعادة التشغيل لا تدهس تخصيصًا.
 */
class SettingDefinitionsSeeder extends Seeder
{
    /** لاحقة اسم ميثود التعريف — الاصطلاح الذي يعتمد عليه الاكتشاف */
    private const DEFINITION_SUFFIX = 'settings';

    public function run(): void
    {
        // القيم قبل الزرع — مرجع استعادة ما عدّله المالك
        $before = Setting::query()->pluck('value', 'key')->all();

        $sources = self::sources();

        foreach ($sources as [$class, $method]) {
            try {
                // معاملة لكلّ مصدر: ألوف الصفوف صفًّا صفًّا خارج المعاملة تعني
                // دقائق على تنصيبٍ حقيقيّ — والمصدر المتعثّر يرتدّ وحده لا الكلّ.
                DB::transaction(fn () => (new $class)->{$method}());
            } catch (\Throwable $e) {
                // تعريفٌ واحد متعثّر لا يوقف الباقي — والسبب يُطبَع ليُصلَح،
                // ويكشفه `settings:coverage` بمفاتيحه الناقصة على أيّ حال.
                $this->command?->warn("    تعثّر {$class}::{$method}() — ".$e->getMessage());
            }
        }

        $this->restoreOwnerValues($before);

        Cache::forget('settings');

        $this->command?->info('تعريفات الإعدادات: '.Setting::count().' مفتاحًا من '.count($sources).' مصدرًا.');
    }

    /**
     * مصادر التعريف: كلّ ميثود عامّة بلا معاملات اسمها ينتهي بـ`settings`
     * داخل `database/seeders/*DemoSeeder.php`.
     *
     * @return list<array{0:class-string,1:string}>
     */
    public static function sources(): array
    {
        $sources = [];

        foreach (glob(database_path('seeders/*DemoSeeder.php')) ?: [] as $file) {
            $class = __NAMESPACE__.'\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                    continue;
                }

                if (! str_ends_with(strtolower($method->getName()), self::DEFINITION_SUFFIX)) {
                    continue;
                }

                $sources[] = [$class, $method->getName()];
            }
        }

        sort($sources);

        return $sources;
    }

    /**
     * استعادة القيم التي كانت في القاعدة قبل الزرع — فالتعريف (اللافتة والنوع
     * والافتراضيّ) يُحدَّث، أمّا **القيمة التي اختارها المالك فلا تُدهَس**.
     *
     * @param  array<string,string|null>  $before
     */
    private function restoreOwnerValues(array $before): void
    {
        if ($before === []) {
            return;
        }

        $after = Setting::query()->pluck('value', 'key')->all();

        DB::transaction(function () use ($before, $after) {
            foreach ($before as $key => $value) {
                if (array_key_exists($key, $after) && $after[$key] !== $value) {
                    Setting::query()->where('key', $key)->update(['value' => $value]);
                }
            }
        });
    }
}
