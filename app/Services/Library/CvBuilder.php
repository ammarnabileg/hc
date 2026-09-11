<?php

namespace App\Services\Library;

use App\Models\Certificate;
use App\Models\Currency;
use App\Models\Cv;
use App\Models\CvTemplate;
use App\Models\Enrollment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Images\AvatarProcessor;
use App\Services\Onboarding\HolderIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFactory;

/**
 * منشئ السيرة الذاتيّة (9 · 24.5): خطوات بحفظ تلقائيّ + معاينة حيّة + قوالب بالتذاكر.
 *
 * لماذا البيانات في عمود JSON واحد: الأقسام متكرّرة وقابلة للتوسّع (21)،
 * فلا نُنشئ جدولًا لكلّ قسم ونُقيّد التوسّع لاحقًا.
 */
class CvBuilder
{
    /**
     * حقول كلّ خطوة — بيضاء صراحةً فلا يُحقَن ما ليس منها.
     *
     * والحقول `*_en` هي **ثنائيّة اللغة بصفر تكلفة** (9): حقلُ لغةٍ ثانية اختياريّ
     * لكلّ نصٍّ حرّ، ولو تُرك فارغًا ظهر النصّ الأصليّ كما هو — بلا أيّ API ترجمة.
     */
    private const FIELDS = [
        'profile' => ['job_title', 'job_title_en', 'company', 'years', 'stage', 'major', 'native_language', 'summary', 'summary_en', 'city', 'email', 'phone'],
        'experience' => ['title', 'company', 'city', 'from', 'to', 'current', 'description', 'description_en'],
        // 💖 الخبرة التطوّعيّة: الدور · المنظمة · المدينة · من · إلى · الوصف (9)
        'volunteering' => ['role', 'organization', 'city', 'from', 'to', 'current', 'description', 'description_en'],
        'education' => ['degree', 'institution', 'major', 'from', 'to', 'current', 'gpa'],
        // 🎓 الدورات التدريبيّة: الاسم · الجهة · التاريخ · رقم الشهادة · رابطها (9)
        'courses' => ['name', 'provider', 'date', 'serial', 'url'],
        'languages' => ['language', 'level'],
    ];

    /** الأقسام المتكرّرة التي يجوز إعادة ترتيبها بالسحب (9) */
    public const REPEATERS = ['experience', 'volunteering', 'education', 'courses', 'languages'];

    /** الخطوات الخمس بالترتيب المعتمَد (24.5) */
    public function steps(): array
    {
        return [
            'profile' => (string) setting('cv.step.profile_label', 'البيانات'),
            'experience' => (string) setting('cv.step.experience_label', 'الخبرات'),
            'volunteering' => (string) setting('cv.step.volunteering_label', 'الخبرة التطوّعيّة'),
            'education' => (string) setting('cv.step.education_label', 'التعليم'),
            'courses' => (string) setting('cv.step.courses_label', 'الدورات التدريبيّة'),
            'skills' => (string) setting('cv.step.skills_label', 'المهارات واللغات'),
            'certificates' => (string) setting('cv.step.certificates_label', 'الشهادات'),
        ];
    }

    public function forUser(User $user): Cv
    {
        $cv = Cv::firstOrCreate(
            ['user_id' => $user->id],
            ['data' => $this->blank(), 'cv_template_id' => $this->freeTemplate()?->id],
        );

        if ($cv->cv_template_id === null) {
            $cv->cv_template_id = $this->freeTemplate()?->id;
            $cv->save();
        }

        return $cv;
    }

    public function blank(): array
    {
        return [
            'profile' => [],
            'experience' => [],
            'volunteering' => [],
            'education' => [],
            'courses' => [],
            'skills' => '',
            'languages' => [],
            'hidden_certificates' => [],
            // ⭐ الربط التلقائيّ بالتدريبات المكتملة مفتوحٌ افتراضيًّا (9)
            'pull' => ['profile' => true, 'certificates' => true, 'trainings' => true, 'photo' => true],
            'purchased_templates' => [],
            // ثنائيّة اللغة: لغة العرض المختارة في المنشئ والمخرَج (9)
            'lang' => 'ar',
        ];
    }

    /** دمج خطوة واحدة في بيانات السيرة (الحفظ التلقائيّ بين الخطوات) */
    public function merge(array $data, string $step, array $payload): array
    {
        $data = array_replace($this->blank(), $data);

        // مفتاح تبديل AR/EN في المنشئ — يُحفَظ مع أيّ خطوة (9)
        if (isset($payload['lang'])) {
            $data['lang'] = $payload['lang'] === 'en' ? 'en' : 'ar';
        }

        switch ($step) {
            case 'profile':
                $data['profile'] = $this->pick($payload['profile'] ?? [], self::FIELDS['profile']);
                break;

            case 'experience':
                $data['experience'] = $this->rows($payload['experience'] ?? [], self::FIELDS['experience']);
                break;

            case 'volunteering':
                $data['volunteering'] = $this->rows($payload['volunteering'] ?? [], self::FIELDS['volunteering']);
                break;

            case 'education':
                $data['education'] = $this->rows($payload['education'] ?? [], self::FIELDS['education']);
                break;

            case 'courses':
                $data['courses'] = $this->rows($payload['courses'] ?? [], self::FIELDS['courses']);
                break;

            case 'skills':
                $data['skills'] = mb_substr(trim((string) ($payload['skills'] ?? '')), 0, (int) setting('cv.skills.max_chars', 600));
                $data['languages'] = $this->rows($payload['languages'] ?? [], self::FIELDS['languages']);
                break;

            case 'certificates':
                $data['hidden_certificates'] = array_values(array_map('intval', (array) ($payload['hidden_certificates'] ?? [])));
                break;
        }

        return $data;
    }

    /** مؤشّر اكتمال % — يوضّح الناقص ويحفّز الإكمال بلا منع (9 · 24.5) */
    public function completion(array $data): int
    {
        $weights = (array) setting('cv.completion.weights', [
            'profile' => 30, 'experience' => 25, 'education' => 20, 'skills' => 15, 'languages' => 10,
        ]);

        $filled = [
            'profile' => collect($data['profile'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty(),
            'experience' => ! empty($data['experience']),
            'education' => ! empty($data['education']),
            'skills' => trim((string) ($data['skills'] ?? '')) !== '',
            'languages' => ! empty($data['languages']),
        ];

        $total = 0;

        foreach ($weights as $key => $weight) {
            if ($filled[$key] ?? false) {
                $total += (int) $weight;
            }
        }

        return max(0, min(100, $total));
    }

    /** ما نقص في السيرة — يُعرَض قبل التحميل بلا منع (24.5) */
    public function missing(array $data): array
    {
        $labels = [
            'profile' => (string) setting('cv.missing.profile_label', 'بياناتك المهنيّة'),
            'experience' => (string) setting('cv.missing.experience_label', 'خبرة عمل واحدة على الأقلّ'),
            'education' => (string) setting('cv.missing.education_label', 'مؤهّلك التعليميّ'),
            'skills' => (string) setting('cv.missing.skills_label', 'مهاراتك'),
            'languages' => (string) setting('cv.missing.languages_label', 'لغة واحدة على الأقلّ'),
        ];

        $missing = [];

        if (collect($data['profile'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
            $missing[] = $labels['profile'];
        }

        foreach (['experience', 'education', 'languages'] as $key) {
            if (empty($data[$key])) {
                $missing[] = $labels[$key];
            }
        }

        if (trim((string) ($data['skills'] ?? '')) === '') {
            $missing[] = $labels['skills'];
        }

        return $missing;
    }

    /**
     * السحب التلقائيّ من المنصّة: الشهادات وبيانات البروفايل (9) — مع إمكانيّة الإخفاء.
     *
     * @return array{profile:array,certificates:Collection}
     */
    public function pulled(?User $user, array $data): array
    {
        $pull = (array) ($data['pull'] ?? []);
        $hidden = (array) ($data['hidden_certificates'] ?? []);

        // المستخدم المحذوف Soft لا كيان له — فنُرجِع مغلّفًا فارغًا بدل الانفجار (10.0)
        if (! $user || ! $user->exists) {
            return ['profile' => [], 'certificates' => collect(), 'trainings' => collect(), 'photo' => null];
        }

        /*
         | ⭐ السيرة تُسحَب من **بيانات الشهادات والإفادات** (2.5-ج) لا من `name`
         | الواحد: الاسم بالعربيّ واللقب للنسخة العربيّة، والاسم بالإنجليزيّ لحقل
         | اللغة الثانية المبنيّ أصلًا في القالب (9) — فتخرج السيرة بلغتين بلا ترجمة.
         */
        $profile = ($pull['profile'] ?? true) ? array_filter([
            'name' => HolderIdentity::nameIn($user, 'ar'),
            'name_en' => HolderIdentity::nameIn($user, 'en'),
            'title' => $user->title,
            'code' => $user->code,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country?->name_ar,
            'governorate' => $user->governorate?->name_ar,
            'address' => $user->address_line,
        ], fn ($v) => filled($v)) : [];

        $certificates = ($pull['certificates'] ?? true)
            ? Certificate::query()
                ->where('user_id', $user->id)
                ->where('status', 'valid')
                ->whereNotIn('id', $hidden)
                ->with('certificate_type')
                ->orderByDesc('issued_at')
                ->get()
            : collect();

        return [
            'profile' => $profile,
            'certificates' => $certificates,
            // ⭐ «التدريبات المكتملة تُضاف تلقائيًّا» (9) — من مصدر التسجيلات الواحد
            'trainings' => ($pull['trainings'] ?? true) ? $this->completedTrainings($user) : collect(),
            // الصورة الشخصيّة: لو موجودة تُعرَض، ولو غابت **لا تُحسَب في العرض** بلا Placeholder (9)
            'photo' => ($pull['photo'] ?? true) ? $this->photoPath($user) : null,
        ];
    }

    /**
     * التدريبات المكتملة — تُضاف للسيرة تلقائيًّا (9)، ومصدرها جدول التسجيلات
     * لا حسابٌ موازٍ.
     */
    public function completedTrainings(User $user): Collection
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->where('status', 'completed')->orWhere('progress_percent', '>=', 100))
            ->with('course:id,name_ar,name_en')
            ->orderByDesc('updated_at')
            ->get();
    }

    /** مسار الصورة الشخصيّة بالمقاس المناسب — وتغييرها من تاب البيانات الأساسيّة (9) */
    public function photoPath(User $user): ?string
    {
        return app(AvatarProcessor::class)->pick($user, (int) setting('cv.photo.size_px', 300));
    }

    /**
     * ثنائيّة اللغة بصفر تكلفة (9): نأخذ حقل اللغة الثانية إن كُتِب،
     * وإلّا فالنصّ الأصليّ كما هو — بلا أيّ API ترجمة.
     */
    public static function text(array $row, string $field, string $lang = 'ar'): string
    {
        if ($lang === 'en') {
            $second = trim((string) ($row[$field.'_en'] ?? ''));

            if ($second !== '') {
                return $second;
            }
        }

        return trim((string) ($row[$field] ?? ''));
    }

    /** لغة العرض المختارة — عربيّة افتراضًا */
    public static function lang(array $data): string
    {
        return ($data['lang'] ?? 'ar') === 'en' ? 'en' : 'ar';
    }

    /** كلّ الشهادات (للإخفاء/الإظهار في الخطوة الخامسة) */
    public function allCertificates(User $user): Collection
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->with('certificate_type')
            ->orderByDesc('issued_at')
            ->get();
    }

    // ------------------------------------------------------------------ القوالب

    /**
     * ورقة المعاينة المشتركة — نقطة بناء `[$sheet]` **الوحيدة** التي يقرأ
     * منها preview/download (`CvController`) **ومعاينة محرّر الديكور المرئيّ
     * للأدمن** (`CvTemplateAdminController::decorPreview` — 12.7-ب المرحلة
     * 2/2) معًا، فلا يتكرّر منطق اختيار ملفّ العرض والتراجع للقالب الافتراضيّ
     * في أكثر من مكانٍ واحد (كانت هذه الميثود دالّةً خاصّة داخل `CvController`
     * وحده قبل أن يحتاجها الأدمن أيضًا بقالبٍ مُحدَّد صراحةً بدل قالب المستخدم
     * المختار).
     *
     * @return array<string, mixed>
     */
    public function sheet(User $user, array $data, ?CvTemplate $template): array
    {
        $key = preg_replace('/[^a-z0-9_\-]/', '', (string) ($template?->view_path ?? '')) ?: 'classic';
        $view = 'cv.templates.'.$key;

        if (! ViewFactory::exists($view)) {
            $view = 'cv.templates.classic';
        }

        return [
            'view' => $view,
            'user' => $user,
            'data' => $data,
            'pulled' => $user->exists ? $this->pulled($user, $data) : ['profile' => [], 'certificates' => collect()],
            'templateName' => $template?->name ?? (string) setting('cv.template.default_name', 'كلاسيك'),
            // ⭐ الطبقة الزخرفيّة (Drag-drop المرحلة 1 · 12.7-ب) — مُنقّاة مسبقًا
            // عند الحفظ في `CvTemplateDecor::sanitize()`، فتُقرأ هنا كما هي.
            'decorLayers' => $template?->decorLayers() ?? [],
        ];
    }

    public function templates(): Collection
    {
        return CvTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** القالب المجّانيّ الواحد — باب الدخول (21.2-ج) */
    public function freeTemplate(): ?CvTemplate
    {
        return CvTemplate::query()
            ->where('is_active', true)
            ->where('is_free', true)
            ->orderBy('sort_order')
            ->first();
    }

    public function owns(CvTemplate $template, array $data): bool
    {
        return $template->is_free
            || in_array($template->id, array_map('intval', (array) ($data['purchased_templates'] ?? [])), true);
    }

    public function ticketBalance(User $user): float
    {
        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'tickets'))
            ->value('balance');
    }

    /**
     * شراء قالب بالتذاكر — الرصيد قبل/بعد ظاهرٌ في البوب-أب قبل التأكيد (24.5).
     *
     * @return array{ok:bool,message:string,balance:float}
     */
    public function purchase(User $user, CvTemplate $template): array
    {
        $price = $template->priceTickets();
        $balance = $this->ticketBalance($user);

        if ($balance < $price) {
            return [
                'ok' => false,
                'balance' => $balance,
                'message' => (string) setting('cv.template.insufficient_message', 'رصيد التذاكر لا يكفي — اكسب تذاكر أو اختر قالبًا آخر.'),
            ];
        }

        $currency = Currency::where('code', 'tickets')->first();

        if (! $currency) {
            return [
                'ok' => false,
                'balance' => $balance,
                'message' => (string) setting('cv.template.currency_missing_message', 'محفظة التذاكر غير مهيّأة — جرّب بعد قليل.'),
            ];
        }

        $after = $balance - $price;

        DB::transaction(function () use ($user, $currency, $price, $after, $template) {
            WalletBalance::query()
                ->where('user_id', $user->id)
                ->where('currency_id', $currency->id)
                ->update([
                    'balance' => $after,
                    'lifetime_spent' => DB::raw('lifetime_spent + '.$price),
                    'updated_at' => now(),
                ]);

            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                'amount' => -$price,
                'balance_after' => $after,
                'layer' => 'training',
                'source' => 'purchase',
                'reason' => str_replace(':name', $template->name, (string) setting('cv.template.transaction_reason', 'شراء قالب سيرة ذاتيّة: :name')),
                'reference_type' => $template->getMorphClass(),
                'reference_id' => $template->id,
            ]);
        });

        return [
            'ok' => true,
            'balance' => $after,
            'message' => (string) setting('cv.template.purchased_message', 'القالب بقى ملكك — استمتع.'),
        ];
    }

    /**
     * ⭐ تنقية صفوف قسمٍ متكرّر بنفس مخطّط `FIELDS` المعتمَد لخطوات المنشئ —
     * يستعملها استيراد CV (9) بعد أن يعدّل المستخدم صفوف المعاينة، فلا يتكرّر
     * تعريف الحقول البيضاء في مكانٍ ثانٍ ولا يتسرّب حقلٌ غير متوقَّع أو صفّ
     * يتجاوز `cv.section.max_rows` عبر مسارٍ لا يمرّ بـ`merge()`.
     */
    public function sanitizeRows(string $section, mixed $rows): array
    {
        return $this->rows($rows, self::FIELDS[$section] ?? []);
    }

    /** نظير `sanitizeRows()` لحقول البروفايل غير المتكرّرة (9). */
    public function sanitizeProfile(mixed $profile): array
    {
        return $this->pick(is_array($profile) ? $profile : [], self::FIELDS['profile']);
    }

    // ------------------------------------------------------------------ داخليّ

    private function pick(array $input, array $fields): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $value = $input[$field] ?? null;

            if (is_scalar($value)) {
                $clean[$field] = mb_substr(trim((string) $value), 0, (int) setting('cv.field.max_chars', 1000));
            }
        }

        return array_filter($clean, fn ($v) => $v !== '');
    }

    private function rows(mixed $input, array $fields): array
    {
        if (! is_array($input)) {
            return [];
        }

        $max = (int) setting('cv.section.max_rows', 20);

        return collect($input)
            ->take($max)
            ->map(fn ($row) => is_array($row) ? $this->pick($row, $fields) : [])
            ->filter(fn (array $row) => $row !== [])
            ->values()
            ->all();
    }
}
