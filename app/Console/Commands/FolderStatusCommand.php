<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * `php artisan docs:status` — تنفيذ القاعدة الرئيسيّة **2.12**:
 * «كلّ مجلّد لازم يحتوي ملفّ `md` يوثّق حالته بالتفصيل ويُحدَّث باستمرار».
 *
 * **لماذا مولِّد لا كتابة يدويّة؟** لأنّ المشروع فيه أكثر من مئتَي مجلّد، ووثيقةٌ
 * تُكتَب مرّةً بيدٍ ثمّ تُترَك تخالف القاعدة نفسها («يبقى موجزًا ومحدَّثًا دائمًا»).
 * فالحلّ: **ما يمكن اشتقاقه من الكود يُشتقّ** (الجرد · البنود الحاكمة · التبعيّات
 * · آخر مَن لمس المجلّد من `git`)، **وما لا يُشتقّ يبقى بيد الإنسان** (المتبقّي
 * والجاري الآن) داخل بلوكات لا يمسّها المولِّد أبدًا.
 *
 * `--check` يفحص بلا كتابة ويخرج بكود 1 — فيكسر الـCI لو مجلّدٌ بلا وثيقة أو
 * وثيقةٌ جردُها قديم، بدل أن تتعفّن الوثائق بصمت.
 */
class FolderStatusCommand extends Command
{
    protected $signature = 'docs:status
                            {--check : افحص بلا كتابة — يخرج بكود 1 لو في وثيقة ناقصة أو قديمة}
                            {--path= : ولّد لمجلّد بعينه فقط}';

    protected $description = 'توليد/تحديث وثيقة التقدّم `_STATUS.md` داخل كلّ مجلّد (القاعدة 2.12)';

    /** اسم الوثيقة كما نصّت عليه القاعدة */
    public const FILE = '_STATUS.md';

    /**
     * الجذور التي تسري عليها القاعدة — بنيةُ المشروع لا قيمةُ ميزة، فمكانها
     * الكود لا شاشة الإعدادات (مثل `SettingsCoverageCommand` تمامًا).
     */
    private const ROOTS = [
        'app/Services',
        'app/Http/Controllers',
        'resources/views',
        'routes/parts',
        'database',
    ];

    /** أقصى ما يُسرَد من ملفّات في الجرد — الوثيقة موجزة بنصّ القاعدة */
    private const MAX_ITEMS = 12;

    private const AUTO_START = '<!-- تلقائيّ:بداية:%s -->';

    private const AUTO_END = '<!-- تلقائيّ:نهاية:%s -->';

    public function handle(): int
    {
        $folders = $this->folders();

        if ($folders === []) {
            $this->warn('مافيش مجلّدات مطابقة.');

            return self::SUCCESS;
        }

        $written = 0;
        $stale = [];

        foreach ($folders as $folder) {
            $path = base_path($folder.'/'.self::FILE);
            $current = is_file($path) ? (string) file_get_contents($path) : null;
            $next = $this->document($folder, $current);

            // نقارن **المضمون** لا تاريخ التوليد: وثيقةٌ لم يتغيّر جردها ليست قديمة،
            // فلا نلوّث الديف بسطر تاريخٍ يتحرّك كلّ يوم بلا سبب.
            if ($current !== null && $this->contentOf($current) === $this->contentOf($next)) {
                continue;
            }

            if ($this->option('check')) {
                $stale[] = $folder.($current === null ? ' — بلا وثيقة' : ' — الجرد قديم');

                continue;
            }

            file_put_contents($path, $next);
            $written++;
        }

        if ($this->option('check')) {
            if ($stale === []) {
                $this->info('كلّ مجلّد له وثيقة تقدّم محدَّثة ✓ ('.count($folders).' مجلّدًا)');

                return self::SUCCESS;
            }

            $this->error('وثائق ناقصة أو قديمة — مخالفة 2.12 ('.count($stale).'):');

            foreach (array_slice($stale, 0, 40) as $line) {
                $this->line('  <fg=yellow>'.$line.'</>');
            }

            $this->line('الإصلاح: <options=bold>php artisan docs:status</>');

            return self::FAILURE;
        }

        $this->info('اتكتب/اتحدّث '.$written.' ملفًّا من '.count($folders).' مجلّدًا ✓');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ المجلّدات

    /** @return array<int,string> مسارات نسبيّة لجذر الريبو */
    private function folders(): array
    {
        $only = trim((string) $this->option('path'), '/');

        return self::targets($only !== '' ? [$only] : null);
    }

    /**
     * المجلّدات التي تسري عليها 2.12 — عامّة كي يفحصها الاختبار بنفس القائمة
     * التي يولّد بها الأمر، فلا يفترق الحارس عن المولِّد.
     *
     * @param  array<int,string>|null  $roots
     * @return array<int,string>
     */
    public static function targets(?array $roots = null): array
    {
        $roots ??= self::ROOTS;
        $found = [];

        foreach ($roots as $root) {
            if (! is_dir(base_path($root))) {
                continue;
            }

            $found[] = $root;

            foreach (self::descend(base_path($root)) as $child) {
                $found[] = ltrim(str_replace(base_path().'/', '', $child), '/');
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    /** @return array<int,string> */
    private static function descend(string $absolute): array
    {
        $out = [];

        foreach ((array) glob($absolute.'/*', GLOB_ONLYDIR) as $child) {
            $out[] = $child;
            $out = array_merge($out, self::descend($child));
        }

        return $out;
    }

    // ------------------------------------------------------------------ الوثيقة

    private function document(string $folder, ?string $current): string
    {
        $manualTodo = $this->block($current, 'المتبقّي') ?? $this->seedTodo($folder);
        $manualNow = $this->block($current, 'الجاري') ?? $this->seedNow();

        return implode("\n", [
            '# 🗂️ `'.$folder.'` — حالة المجلّد',
            '',
            '> وثيقة **القاعدة 2.12** (توثيق التقدّم داخل كلّ مجلّد).',
            '> الأقسام المحصورة بين علامتَي `تلقائيّ:بداية` و`تلقائيّ:نهاية` يعيد كتابتها',
            '> `php artisan docs:status` من الكود نفسه — فلا تُحرّرها بيدك.',
            '> أمّا **المتبقّي** و**الجاري الآن** فبيدك أنت، ولا يمسّهما المولِّد أبدًا:',
            '> حدّثهما **قبل كلّ جلسة عمل وبعدها** كما تنصّ القاعدة.',
            '',
            '## 🎯 الغرض/المطلوب',
            $this->wrap('الغرض', $this->purpose($folder)),
            '',
            '## ✅ المُنجَز',
            $this->wrap('المنجز', $this->done($folder)),
            '',
            '## ⬜ المتبقّي',
            $this->wrapManual('المتبقّي', $manualTodo),
            '',
            '## 🔄 الجاري الآن',
            $this->wrapManual('الجاري', $manualNow),
            '',
            '## 🔗 التبعيّات والملفّات المهمّة',
            $this->wrap('التبعيات', $this->links($folder)),
            '',
            '## 🕒 آخر تحديث',
            $this->wrap('التحديث', $this->stamp($folder)),
            '',
        ]);
    }

    /** الوثيقة بلا بلوك «آخر تحديث» — لمقارنة المضمون وحده */
    private function contentOf(string $document): string
    {
        return (string) preg_replace(
            '/'.preg_quote(sprintf(self::AUTO_START, 'التحديث'), '/').'.*?'.preg_quote(sprintf(self::AUTO_END, 'التحديث'), '/').'/su',
            '',
            $document,
        );
    }

    private function wrap(string $key, string $body): string
    {
        return sprintf(self::AUTO_START, $key)."\n".rtrim($body)."\n".sprintf(self::AUTO_END, $key);
    }

    private function wrapManual(string $key, string $body): string
    {
        return '<!-- بيدك:بداية:'.$key." -->\n".rtrim($body)."\n<!-- بيدك:نهاية:".$key.' -->';
    }

    /** قراءة بلوك الإنسان من الوثيقة القائمة — فلا يضيع ما كتبه أحد */
    private function block(?string $document, string $key): ?string
    {
        if ($document === null) {
            return null;
        }

        $pattern = '/<!-- بيدك:بداية:'.preg_quote($key, '/').' -->\n(.*?)\n<!-- بيدك:نهاية:'.preg_quote($key, '/').' -->/su';

        return preg_match($pattern, $document, $matches) === 1 ? rtrim($matches[1]) : null;
    }

    // ------------------------------------------------------------------ الأقسام

    /**
     * الغرض: من خريطة مكتوبة بيدٍ للمجالات الأصليّة، ويرثه الفرعُ من أصله
     * موصوفًا بدوره (`partials` أجزاء · `tabs` تبويبات …). وما لا أصل له يُشتقّ
     * من أوّل سطر في توثيق أوّل صنف داخله — أصدق من جملةٍ عامّة مكرّرة.
     */
    private function purpose(string $folder): string
    {
        $lines = ['- **الوظيفة:** '.$this->purposeText($folder)];

        $clauses = $this->clauses($folder);

        if ($clauses !== []) {
            $lines[] = '- **البنود الحاكمة (من تعليقات الكود نفسه):** '.implode(' · ', $clauses);
        }

        $lines[] = '- **قواعد سارية على كلّ ما هنا:** الصلاحيّة على كلّ مسار (12.2.1) · '
            .'المحظور يُخفى لا يُعطَّل (2.15-أ-7) · لا رقم ولا نصّ محروق (2.13) · '
            .'🔒 الماليّات لمالك المنصّة وحده (12.7).';

        return implode("\n", $lines);
    }

    private function purposeText(string $folder): string
    {
        $map = $this->purposeMap();

        if (isset($map[$folder])) {
            return $map[$folder];
        }

        $name = basename($folder);
        $parent = dirname($folder);
        $parentText = $map[$parent] ?? null;

        $role = $this->roleOf($name);

        if ($parentText !== null && $role !== null) {
            return $role.' — ضمن: '.$parentText;
        }

        if ($parentText !== null) {
            return '«'.$this->humanize($name).'» ضمن: '.$parentText;
        }

        $derived = $this->firstDocLine($folder);

        if ($derived !== null) {
            return $derived;
        }

        if ($role !== null) {
            return $role.' لمجلّد `'.$parent.'`.';
        }

        return str_starts_with($folder, 'resources/views')
            ? 'شاشات وأجزاء واجهة «'.$this->humanize($name).'».'
            : 'كود «'.$this->humanize($name).'».';
    }

    /** دور المجلّد من اسمه — الأسماء المتكرّرة في المشروع لها معنى ثابت */
    private function roleOf(string $name): ?string
    {
        return match ($name) {
            'partials' => 'أجزاء بليد قابلة لإعادة الاستعمال داخل شاشات المجلّد الأب',
            'components' => 'مكوّنات بليد خاصّة بالمجلّد الأب',
            'tabs' => 'تبويبات الشاشة — تُحمَّل كسولًا (2.15-أ-10)',
            'tables' => 'جداول الشاشة',
            'modals' => 'بوانل وبوب-أبات الشاشة (التفاصيل في بانل لا صفحة جديدة)',
            'migrations' => 'مخطّط قاعدة البيانات — المايجريشنز القائمة لا تُعدَّل، والنقص يُضاف بمايجريشن جديد',
            'seeders' => 'بذور البيانات: إعدادات كلّ مجال بقيمها الافتراضيّة (2.13) وبيانات عرض عربيّة واقعيّة',
            'factories' => 'مصانع النماذج للاختبارات',
            default => null,
        };
    }

    /**
     * خريطة المجالات — الأصل الذي يرث منه كلّ فرع.
     *
     * @return array<string,string>
     */
    private function purposeMap(): array
    {
        return [
            'app/Services' => 'منطق الأعمال لكلّ المجالات — الكنترولر ينسّق والخدمة تقرّر.',
            'app/Services/Account' => 'الحساب الشخصيّ: البيانات والتفضيلات والخصوصيّة (10).',
            'app/Services/Admin' => 'خدمات لوحة الإدارة بمجالاتها (12).',
            'app/Services/AdminScreens' => 'شاشات القسم 24 الناقصة: بنك الأسئلة · الريفيرال · التقارير المجدولة · مرآة الاجتماعات (24.1-3 · 24.2 · 24.3-خامسًا).',
            'app/Services/Ads' => 'التتبّع الإعلانيّ: الأحداث الثمانية بلحظاتها · البكسل وConversions API · والموافقة شرطٌ لازم (21.3).',
            'app/Services/Certificates' => 'إصدار الشهادات والتحقّق العامّ منها (8).',
            'app/Services/Dashboard' => 'لوحة المتدرّب: ما يهمّه الآن في شاشة واحدة (2.15).',
            'app/Services/Engagement' => 'الإتاحة الزمنيّة ونادي الخامسة والسلاسل (5 · 7.2).',
            'app/Services/Events' => 'الفعاليّات والحضور (11).',
            'app/Services/Gamification' => 'التلعيب: XP والمستويات والشارات والحروب والتحدّيات (13 · 15).',
            'app/Services/Growth' => 'النموّ والسيو والمعاينة المجّانيّة وحلقات الدعوة (21.1 · 21.2).',
            'app/Services/Home' => 'الصفحة العامّة وواجهة الزائر (1).',
            'app/Services/Images' => 'توليد الصور على الخادم بلا مكتبات خارجيّة.',
            'app/Services/Learning' => 'التعلّم: التدريبات والدروس والامتحانات والتقدّم (3 · 4).',
            'app/Services/Library' => 'المكتبة والسيرة الذاتيّة وتوليد PDF متوافق مع ATS بلا مكتبات (9).',
            'app/Services/Notifications' => 'الإشعارات: مصفوفة حدث × قناة، ومركز الإشعارات بتبويباته (2.8).',
            'app/Services/Referral' => 'الدعوات والسفراء — الطرف الذي يخصّ المستخدم (21.2).',
            'app/Services/Security' => 'الأمان: الصيانة واسترجاع كلمة السرّ وOTP واحتواء الحساب (2.9 · 22).',
            'app/Services/Setup' => 'التنصيب والتحديث ومهاجرة المخطّط (2.11).',
            'app/Services/Store' => 'المتجر: الكتالوج والسلّة والتسعير والشراء والملكيّة (16 · 17 · 20).',
            'app/Services/Ui' => 'خدمات الواجهة المشتركة (2.15 · 2.16 · 2.17).',
            'app/Services/Volunteer' => 'التطوّع بكلّ طبقاته: المهام والاجتماعات والأداء والتقدير (13.4).',
            'app/Services/Wallet' => 'المحفظة والعمليّات الماليّة: الشحن والسحب والعمولة والقفل (19).',

            'app/Http/Controllers' => 'المتحكّمات — تتحقّق وتنسّق وترجع View، والقرار في الخدمة لا هنا.',
            'app/Http/Controllers/Admin' => 'متحكّمات لوحة الإدارة (12).',
            'app/Http/Controllers/AdminScreens' => 'متحكّمات شاشات القسم 24 الناقصة (24).',
            'app/Http/Controllers/Auth' => 'الدخول والتسجيل واستعادة كلمة السرّ (22).',
            'app/Http/Controllers/Growth' => 'الصفحات العامّة للنموّ والسيو (21.1).',
            'app/Http/Controllers/Setup' => 'شاشات التنصيب والتحديث (2.11).',
            'app/Http/Controllers/Trainee' => 'شاشات المتدرّب: التعلّم والمحفظة والمتجر والحساب.',
            'app/Http/Controllers/Ui' => 'شاشات الواجهة المشتركة والسيرة الذاتيّة (9).',
            'app/Http/Controllers/Volunteer' => 'لوحة التطوّع بكلّ تبويباتها (13.4-ح).',

            'resources/views' => 'كلّ واجهات المنصّة بالعربيّة — البساطة أوّلًا والموبايل شرط قبول (2.15).',
            'resources/views/admin' => 'شاشات لوحة الإدارة باثنَي عشر قسمًا في السايد بار (12.0).',
            'resources/views/components' => 'المكوّنات المشتركة الجاهزة — لا يُعاد بناؤها ولا تُعدَّل من مجال واحد (قاعدة البناء §1).',
            'resources/views/layouts' => 'اللياوتات: الهيدر والسايد بار وحاوية المحتوى وفعل الموبايل.',
            'resources/views/partials' => 'أجزاء مشتركة بين الشاشات كلّها.',
            'resources/views/volunteer' => 'شاشات لوحة التطوّع بثلاثة عشر عنصرًا في سايد بارها (13.4-ح).',
            'resources/views/cv' => 'بنّاء السيرة الذاتيّة وصفحتها العامّة (9).',

            'routes/parts' => 'مسارات كلّ مجال في ملفّه — و`routes/web.php` يحمّلها تلقائيًّا. والصلاحيّة إلزاميّة على كلّ مسار (12.2.1).',

            'database' => 'المخطّط والبذور والمصانع.',
        ];
    }

    /** أوّل سطر من توثيق أوّل صنف في المجلّد — وصفٌ مكتوبٌ أصلًا لا مخترَع */
    private function firstDocLine(string $folder): ?string
    {
        foreach ($this->files($folder, 'php') as $file) {
            $body = (string) file_get_contents(base_path($folder.'/'.$file));

            if (preg_match('#/\*\*(.*?)\*/\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s#su', $body, $matches) !== 1) {
                continue;
            }

            foreach (preg_split('/\R/u', $matches[1]) ?: [] as $line) {
                $clean = trim(preg_replace('/^\s*\*\s?/u', '', $line) ?? '');

                if ($clean !== '') {
                    return rtrim($clean, '.').'.';
                }
            }
        }

        return null;
    }

    /**
     * بنود الدستور المذكورة في تعليقات المجلّد — نقرأها من الكود لا من ذاكرتنا،
     * فالوثيقة تبقى صادقةً مع ما يفعله الكود فعلًا.
     *
     * @return array<int,string>
     */
    private function clauses(string $folder): array
    {
        $found = [];

        foreach (array_merge($this->files($folder, 'php'), $this->files($folder, 'blade.php')) as $file) {
            $body = (string) file_get_contents(base_path($folder.'/'.$file));

            foreach (preg_split('/\R/u', $body) ?: [] as $line) {
                if (! preg_match('#(//|\*|\{\{--|\|)#', $line)) {
                    continue; // الأرقام خارج التعليقات ليست إحالات لبنود
                }

                if (preg_match_all('/\(\s*([0-9]{1,2}(?:\.[0-9]{1,2})+(?:-[^\s()·]+)*(?:\s*·\s*[0-9]{1,2}(?:\.[0-9]{1,2})+(?:-[^\s()·]+)*)*)\s*\)/u', $line, $matches)) {
                    foreach ($matches[1] as $match) {
                        foreach (preg_split('/\s*·\s*/u', $match) ?: [] as $clause) {
                            $found[trim($clause)] = true;
                        }
                    }
                }
            }
        }

        $clauses = array_keys($found);

        usort($clauses, fn ($a, $b) => version_compare($a, $b));

        return array_slice($clauses, 0, self::MAX_ITEMS);
    }

    /** المُنجَز = الجرد الحقيقيّ: ما يملكه المجلّد الآن، بعناوينه لا بأسمائه فقط */
    private function done(string $folder): string
    {
        $subfolders = array_map('basename', (array) glob(base_path($folder).'/*', GLOB_ONLYDIR));
        $php = $this->files($folder, 'php');
        $blade = array_values(array_filter($php, fn ($f) => str_ends_with($f, '.blade.php')));
        $classes = array_values(array_filter($php, fn ($f) => ! str_ends_with($f, '.blade.php')));

        $lines = [];
        $isRoutes = str_starts_with($folder, 'routes');

        if ($classes !== [] && $isRoutes) {
            // ملفّات المسارات ليست أصنافًا — المفيد فيها عدد المسارات وبادئة أسمائها
            $lines[] = '- **ملفّات مسارات ('.count($classes).'):**';

            foreach ($classes as $file) {
                $lines[] = '  - `'.$file.'` — '.$this->routeSummary($folder.'/'.$file);
            }
        } elseif ($classes !== [] && basename($folder) === 'migrations') {
            // المايجريشنز تُقرأ من آخرها: الأقدم تاريخٌ مغلَق، والأحدث هو ما يُكمَل عليه
            $recent = array_slice(array_reverse($classes), 0, self::MAX_ITEMS);

            $lines[] = '- **مايجريشنز ('.count($classes).') — والقائم منها لا يُعدَّل (قاعدة البناء §1). الأحدث:**';

            foreach ($recent as $file) {
                $lines[] = '  - `'.$file.'`';
            }

            $lines[] = $this->more(count($classes));
        } elseif ($classes !== []) {
            $lines[] = '- **أصناف ('.count($classes).'):**';

            foreach (array_slice($classes, 0, self::MAX_ITEMS) as $file) {
                $summary = $this->summaryOf($folder.'/'.$file);
                $lines[] = '  - `'.$file.'`'.($summary !== null ? ' — '.$summary : '');
            }

            $lines[] = $this->more(count($classes));
        }

        if ($blade !== []) {
            $lines[] = '- **شاشات/أجزاء بليد ('.count($blade).'):**';

            foreach (array_slice($blade, 0, self::MAX_ITEMS) as $file) {
                $title = $this->bladeTitle($folder.'/'.$file);
                $lines[] = '  - `'.$file.'`'.($title !== null ? ' — '.$title : '');
            }

            $lines[] = $this->more(count($blade));
        }

        $others = array_values(array_filter(
            array_map('basename', (array) glob(base_path($folder).'/*')),
            fn ($f) => is_file(base_path($folder.'/'.$f)) && ! str_ends_with($f, '.php') && $f !== self::FILE,
        ));

        if ($others !== []) {
            $lines[] = '- **ملفّات أخرى ('.count($others).'):** '.implode(' · ', array_map(
                fn ($f) => '`'.$f.'`',
                array_slice($others, 0, self::MAX_ITEMS),
            ));
        }

        if ($subfolders !== []) {
            sort($subfolders);
            $lines[] = '- **مجلّدات فرعيّة ('.count($subfolders).'):** '.implode(' · ', array_map(
                fn ($f) => '`'.$f.'/`',
                $subfolders,
            )).' — ولكلٍّ منها وثيقتها.';
        }

        if ($lines === []) {
            $lines[] = '- المجلّد فاضي لسّه — أوّل ملفّ فيه يبني هذا الجرد تلقائيًّا.';
        }

        return implode("\n", array_filter($lines, fn ($l) => $l !== ''));
    }

    /**
     * ملخّص ملفّ مسارات: كم مسارًا، وبأيّ بادئة اسم، وكم منها بحارس صلاحيّة.
     * ولماذا نعدّ الحرّاس؟ لأنّ «الصلاحيّة على كلّ مسار» (12.2.1) قاعدة تُفحَص
     * بالعين كثيرًا — فليكن الرقم مكتوبًا أمام مَن يفتح المجلّد.
     */
    private function routeSummary(string $relative): string
    {
        $body = (string) @file_get_contents(base_path($relative));

        preg_match_all("/->name\(\s*'([^']+)'/u", $body, $names);
        $count = count($names[1] ?? []);

        $prefixes = [];

        if (preg_match_all("/->name\(\s*'([a-z0-9_-]+)\.'\s*\)/u", $body, $groups)) {
            $prefixes = array_values(array_unique($groups[1]));
        }

        $guards = preg_match_all("/'permission:/u", $body);

        return $count.' مسارًا'
            .($prefixes !== [] ? ' · البادئة `'.implode('.` · `', $prefixes).'.`' : '')
            .' · '.$guards.' حارس صلاحيّة.';
    }

    private function more(int $total): string
    {
        return $total > self::MAX_ITEMS ? '  - … و'.($total - self::MAX_ITEMS).' غيرها.' : '';
    }

    /** التبعيّات: أخوة المجال في بقيّة الطبقات + اختباراته — كما يحتاجها مَن يُكمِل */
    private function links(string $folder): string
    {
        $area = $this->areaOf($folder);
        $lines = [];

        if ($area !== null) {
            $siblings = array_values(array_filter([
                'app/Services/'.$area,
                'app/Http/Controllers/'.$area,
                'resources/views/'.Str::kebab($area),
                'routes/parts',
                'tests/Feature/'.$area,
            ], fn ($p) => is_dir(base_path($p)) && $p !== $folder));

            if ($siblings !== []) {
                $lines[] = '- **الطبقات الأخرى لنفس المجال:** '.implode(' · ', array_map(fn ($p) => '`'.$p.'`', $siblings));
            }

            $tests = is_dir(base_path('tests/Feature/'.$area))
                ? count($this->files('tests/Feature/'.$area, 'php'))
                : 0;

            $lines[] = '- **الاختبارات:** '.($tests > 0
                ? $tests.' ملفّ Feature في `tests/Feature/'.$area.'` — شغّلها بـ`php artisan test tests/Feature/'.$area.'`.'
                : 'مافيش مجلّد اختبارات بنفس اسم المجال — راجع `tests/Feature` قبل ما تفتح ملفًّا جديدًا.');
        }

        $lines[] = '- **المرجع الحاكم:** `دستور اساسي.md` · **وكيف نكتب:** `docs/BUILD.md`.';

        if (dirname($folder) !== '.') {
            $lines[] = '- **المجلّد الأب:** `'.dirname($folder).'/'.self::FILE.'`.';
        }

        return implode("\n", $lines);
    }

    private function areaOf(string $folder): ?string
    {
        foreach (['app/Services/', 'app/Http/Controllers/'] as $prefix) {
            if (str_starts_with($folder, $prefix)) {
                return explode('/', Str::after($folder, $prefix))[0];
            }
        }

        return null;
    }

    /** آخر تحديث + مَن — من `git` لا من نيّةٍ حسنة */
    private function stamp(string $folder): string
    {
        $touch = $this->git(['log', '-1', '--format=%ad|%an', '--date=short', '--', $folder]);
        $line = '- **آخر توليد لهذه الوثيقة:** '.now()->format('Y-m-d').' — `php artisan docs:status`.';

        if ($touch === null || ! str_contains($touch, '|')) {
            return $line."\n".'- **آخر لمسة للمجلّد:** لسّه ما اتسجّلتش في `git`.';
        }

        [$date, $author] = explode('|', $touch, 2);

        return $line."\n".'- **آخر لمسة للمجلّد:** '.trim($date).' — '.trim($author).'.';
    }

    private function git(array $arguments): ?string
    {
        $process = new Process(array_merge(['git'], $arguments), base_path());
        $process->run();

        $out = trim($process->getOutput());

        return $process->isSuccessful() && $out !== '' ? $out : null;
    }

    // ------------------------------------------------------------------ البذور

    private function seedTodo(string $folder): string
    {
        return '- ما فيش فجوة معروفة في هذا المجلّد وقت إنشاء الوثيقة.'."\n"
            .'- لو فتحت فجوة (وعدٌ في الواجهة بلا تنفيذ · إعدادٌ بلا شاشة · مسارٌ بلا صلاحيّة) اكتبها هنا فورًا.';
    }

    private function seedNow(): string
    {
        return '- **الحالة:** مافيش شغل جارٍ.'."\n"
            .'- **آخر نقطة وصلنا لها:** —'."\n"
            .'- **الخطوة الجاية:** —';
    }

    // ------------------------------------------------------------------ أدوات

    /** @return array<int,string> */
    private function files(string $folder, string $extension): array
    {
        $names = [];

        foreach ((array) glob(base_path($folder).'/*.'.$extension) as $file) {
            $name = basename($file);

            if ($name === self::FILE) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }

    private function summaryOf(string $relative): ?string
    {
        $body = (string) @file_get_contents(base_path($relative));

        if (preg_match('#/\*\*(.*?)\*/\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s#su', $body, $matches) !== 1) {
            return null;
        }

        foreach (preg_split('/\R/u', $matches[1]) ?: [] as $line) {
            $clean = trim(preg_replace('/^\s*\*\s?/u', '', $line) ?? '');

            if ($clean !== '') {
                return $this->shorten($clean);
            }
        }

        return null;
    }

    private function bladeTitle(string $relative): ?string
    {
        $body = (string) @file_get_contents(base_path($relative));

        if (preg_match("/@section\(\s*'title'\s*,\s*'([^']+)'/u", $body, $matches) === 1) {
            return $this->shorten($matches[1]);
        }

        if (preg_match('/\{\{--\s*(.+?)\s*--\}\}/u', $body, $matches) === 1) {
            return $this->shorten($matches[1]);
        }

        return null;
    }

    private function shorten(string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return Str::limit($clean, 110);
    }

    private function humanize(string $name): string
    {
        return str_replace(['-', '_'], ' ', $name);
    }
}
