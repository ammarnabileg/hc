<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Admin\System\SettingKeyScanner;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * ردمُ الفجوة: مفاتيح **يقرؤها الكود** ولم يعلنها أيّ مجال في سيدره.
 *
 * كلّ صفّ هنا كان قيمةً مكتوبةً في الكود كافتراضيّ `setting('…', 'قيمة')` —
 * والمالك لا يراها في لوحته فلا يقدر على تغييرها، وهي عين ما تمنعه 2.13-ب.
 * **والقيمة الافتراضيّة هنا مطابقةٌ حرفيًّا لما يقرؤه الكود اليوم**، فلا يتغيّر
 * سلوكٌ قائم: الفرق الوحيد أنّ المفتاح صار **ظاهرًا وقابلًا للتعديل**.
 *
 * ⚠️ هذا ملفُّ ردمٍ لا موطنٌ دائم: لو المفتاح يخصّ مجالك، انقله لميثود
 * `settings()` بسيدر مجالك — `SettingDefinitionsSeeder` يزرعها من هناك.
 * ويُكتَب بـ`firstOrCreate` فلا يدهس قيمةً عدّلها المالك.
 */
class SettingGapSeeder extends Seeder
{
    /** شرح ما التقطته الشبكة — يقول للمالك إنّ اللافتة لسّه محتاجة صياغة */
    private const AUTO_HINT = 'التُقِط تلقائيًّا من الكود بافتراضيّه — لسّه محتاج لافتةً عربيّةً في سيدر مجاله.';

    public function run(): void
    {
        $this->write(self::rows());

        Cache::forget('settings');

        $auto = $this->write($this->fromCode());

        Cache::forget('settings');

        $this->command?->info('ردم فجوة الإعدادات: '.count(self::rows()).' مفتاحًا منسَّقًا'.
            ($auto > 0 ? " + {$auto} التقطتها شبكة الأمان من الكود" : '').'.');
    }

    /** @param  list<array{0:string,1:string,2:string,3:string,4:string,5:string}>  $rows */
    private function write(array $rows): int
    {
        $written = 0;

        foreach ($rows as [$key, $group, $label, $type, $default, $hint]) {
            $written += Setting::firstOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'hint' => $hint,
            ])->wasRecentlyCreated ? 1 : 0;
        }

        return $written;
    }

    /**
     * 🛡️ شبكة الأمان: مفتاحٌ يقرؤه الكود ولم يعلنه أحد — **بافتراضيّه المكتوب
     * في موضع القراءة نفسه**، فلا يتغيّر سلوكٌ قائم بحرفٍ واحد.
     *
     * لماذا شبكة لا قائمة؟ لأنّ القائمة المكتوبة بيدٍ تتقادم مع كلّ ميزة جديدة،
     * فتعود الفجوة صامتةً بينما الحارس يمرّ. وهي **شبكة لا بديل**: لافتتها
     * مشتقّة من المفتاح، والأولى أن يعلن المجالُ مفتاحه بلافتةٍ يفهمها المالك.
     *
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string}>
     */
    private function fromCode(): array
    {
        $scanner = app(SettingKeyScanner::class);
        $seeded = Setting::query()->pluck('key')->all();
        $defaults = $scanner->codeDefaults();

        // المجموعة تتبع البادئة: مفاتيح `question_bank.*` تسكن حيث تسكن أخواتها،
        // فلا تنقسم البادئة على مجموعتين ولا تظهر مجموعةٌ يتيمة بلا تاب (2.13-و).
        $groupOfPrefix = Setting::query()
            ->get(['key', 'group'])
            ->groupBy(fn (Setting $s) => explode('.', $s->key)[0])
            ->map(fn ($rows) => $rows->countBy('group')->sortDesc()->keys()->first());

        // ولو البادئة نفسها مجموعةٌ لها تابٌ مسجَّل، فهي موطنها الطبيعيّ
        $registered = app(SettingsRegistry::class)->groupToTab();

        $rows = [];
        $unresolved = [];

        foreach ($scanner->missing($seeded) as $key => $places) {
            if (! array_key_exists($key, $defaults)) {
                // بلا افتراضيّ حرفيّ: لا نخمّن — التخمين يغيّر السلوك
                $unresolved[] = $key;

                continue;
            }

            $prefix = explode('.', $key)[0];
            $group = $groupOfPrefix[$prefix] ?? (isset($registered[$prefix]) ? $prefix : null);

            if ($group === null) {
                $unresolved[] = $key;

                continue;
            }

            $default = $defaults[$key];

            $rows[] = [$key, $group, self::derivedLabel($key), self::inferType($default), $default, self::AUTO_HINT];
        }

        if ($unresolved !== []) {
            $this->command?->warn('  مفاتيح تحتاج إعلانًا يدويًّا في سيدر مجالها: '.implode(' · ', $unresolved));
        }

        return $rows;
    }

    /** النوع من شكل القيمة — والنصّ الطويل في Textarea لا في سطر */
    private static function inferType(string $default): string
    {
        return match (true) {
            $default === '1' || $default === '0' => 'bool',
            is_numeric($default) => 'number',
            str_starts_with($default, '[') || str_starts_with($default, '{') => 'json',
            mb_strlen($default) > 40 => 'text',
            default => 'string',
        };
    }

    /** `cv.field.job_title_en_label` ⟵ «cv › field › job title en label» */
    private static function derivedLabel(string $key): string
    {
        return str_replace(['.', '_'], [' › ', ' '], $key);
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string,5:string}> */
    public static function rows(): array
    {
        return array_merge(
            self::certificates(),
            self::exams(),
            self::growth(),
            self::complaints(),
            self::cv(),
            self::volunteering(),
            self::misc(),
            self::notificationMatrix(),
        );
    }

    // ---------------------------------------------------------------- الشهادات (9)

    private static function certificates(): array
    {
        $rows = [
            ['certificates.labels.accreditation', 'الاعتماد'],
            ['certificates.labels.accredited_by', 'معتمدة من'],
            ['certificates.labels.all', 'الكلّ'],
            ['certificates.labels.apply', 'طبّق'],
            ['certificates.labels.arabic', 'عربيّة'],
            ['certificates.labels.certificate', 'الشهادة'],
            ['certificates.labels.certificate_plural', 'شهادة'],
            ['certificates.labels.code', 'الكود'],
            ['certificates.labels.country', 'الدولة'],
            ['certificates.labels.download', 'تحميل'],
            ['certificates.labels.download_copy', 'تنزيل النسخة'],
            ['certificates.labels.empty', 'أوّل شهادة على بُعد تدريب واحد.'],
            ['certificates.labels.empty_action', 'روح لتدريباتي'],
            ['certificates.labels.english', 'إنجليزيّة'],
            ['certificates.labels.expired_at', 'انتهى العمل بها'],
            ['certificates.labels.holder', 'الحائز'],
            ['certificates.labels.issued', 'شهادتك صدرت'],
            ['certificates.labels.issued_at', 'تاريخ الإصدار'],
            ['certificates.labels.language', 'لغة النسخة'],
            ['certificates.labels.learning', 'تعلّمي'],
            ['certificates.labels.my_certificates', 'شهاداتي'],
            ['certificates.labels.open', 'افتح'],
            ['certificates.labels.print', 'نسخة للطباعة'],
            ['certificates.labels.print_hint', 'اطبع الصفحة أو احفظها PDF من متصفّحك.'],
            ['certificates.labels.qr_alt', 'رمز التحقّق'],
            ['certificates.labels.report', 'أبلغ عن شهادة مشبوهة'],
            ['certificates.labels.report_contact', 'وسيلة تواصل (اختياريّة)'],
            ['certificates.labels.report_note', 'إيه اللي مريب؟'],
            ['certificates.labels.report_submit', 'ابعت البلاغ'],
            ['certificates.labels.search', 'بحث'],
            ['certificates.labels.search_hint', 'بالكود أو الاسم'],
            ['certificates.labels.share', 'شارك شهادتك'],
            ['certificates.labels.share_linkedin', 'مشاركة على لينكدإن'],
            ['certificates.labels.share_text', 'نصّ المنشور — عدّله زيّ ما تحبّ'],
            ['certificates.labels.type', 'النوع'],
            ['certificates.labels.verify_page', 'صفحة التحقّق'],
            ['certificates.labels.year', 'السنة'],
            ['certificates.verify.not_found_badge', 'غير موجودة'],
        ];

        return self::texts($rows, 'certificates', 'نصّ ظاهر في شاشات الشهادات والتحقّق العامّ.');
    }

    // ---------------------------------------------------------------- الامتحانات (8)

    private static function exams(): array
    {
        $rows = [
            ['exams.labels.answered', 'المُجاب'],
            ['exams.labels.answered_one', 'مُجاب'],
            ['exams.labels.attempts', 'المحاولات المتبقّية'],
            ['exams.labels.available_at', 'محاولتك الجاية متاحة'],
            ['exams.labels.back_to_learning', 'ارجع لتدريباتي'],
            ['exams.labels.back_to_questions', 'ارجع للأسئلة'],
            ['exams.labels.balance_after', 'رصيدك بعد'],
            ['exams.labels.balance_before', 'رصيدك قبل'],
            ['exams.labels.duration', 'مدّة الامتحان'],
            ['exams.labels.go_review', 'مراجعة والتسليم'],
            ['exams.labels.minute', 'دقيقة'],
            ['exams.labels.next', 'التالي'],
            ['exams.labels.not_this_time', 'مش المرّة دي'],
            ['exams.labels.notice', 'انتبه'],
            ['exams.labels.numeric_answer', 'الإجابة الرقميّة'],
            ['exams.labels.of', 'من'],
            ['exams.labels.open_question', 'افتح السؤال'],
            ['exams.labels.pass_score', 'درجة النجاح'],
            ['exams.labels.passed', 'ناجح'],
            ['exams.labels.previous', 'السابق'],
            ['exams.labels.price', 'سعر الدخول'],
            ['exams.labels.question', 'سؤال'],
            ['exams.labels.result', 'نتيجة الامتحان'],
            ['exams.labels.text_answer', 'إجابتك'],
            ['exams.labels.time_left', 'الوقت المتبقّي'],
            ['exams.labels.try_again', 'جرّب تاني'],
            ['exams.labels.unanswered', 'بلا إجابة'],
            ['exams.labels.unlocked', 'اللي فتحه نجاحك'],
            ['exams.labels.what_you_need', 'اللي محتاج تعرفه'],
            ['exams.labels.your_score', 'درجتك من 100'],
        ];

        return self::texts($rows, 'exams', 'نصّ ظاهر في شاشة الامتحان والنتيجة.');
    }

    // ---------------------------------------------------------------- النموّ (21)

    private static function growth(): array
    {
        $rows = [
            ['growth.articles.more_label', 'اقرأ كمان'],
            ['growth.articles.search_placeholder', 'دوّر على موضوع…'],
            ['growth.og.course_meta', 'صفحة التدريب · معاينة أوّل درس'],
            ['growth.og.free_label', 'مجّانيّ'],
            ['growth.og.path_meta', 'مسار تعلّم متكامل'],
            ['growth.og.profile_meta', 'بروفايل على المنصّة'],
            ['growth.preview.badge', 'درس معاينة مجّانيّ'],
            ['growth.preview.empty', 'المنهج لسّه بيتجهّز.'],
            ['growth.preview.open_label', 'شوف الدرس'],
            ['growth.preview.register_note', 'التسجيل مجّانيّ والتفعيل باعتماد إداريّ — بلا أيّ رسوم.'],
            ['growth.preview.upsell', 'عجبك الدرس؟ باقي التدريب بيتفتح بعد التسجيل — والتسجيل مجّانيّ.'],
            ['growth.profile_completion.already', 'مكافأة إكمال الملفّ اتصرفت قبل كده — وبتتصرف مرّة واحدة بس.'],
            ['growth.profile_completion.bar_done_hint', 'المكافأة اتصرفت — كمّل الباقي علشان بطاقاتك تطلع كاملة.'],
            ['growth.profile_completion.complete', 'ملفّك كامل — تمام كده.'],
            ['growth.profile_completion.cta', 'روح كمّل بياناتك'],
            ['growth.profile_completion.fields_label', 'اللي لسّه ناقص'],
            ['growth.profile_completion.progress_label', 'نسبة الاكتمال'],
            ['growth.profile_completion.promise', 'أول ما توصل 100% هتاخد {tickets} تذاكر — مرّة واحدة.'],
            ['growth.volunteer_kit.link_hint', 'كلّ مَن يسجّل من الرابط ده بيتحسبلك — والرابط موسوم علشان نعرف عائد كلّ قناة.'],
            ['growth.volunteer_kit.link_label', 'رابط دعوتك'],
            ['growth.volunteer_kit.scripts_label', 'نصوص جاهزة'],
            ['growth.volunteer_kit.templates_empty', 'مافيش قوالب متاحة لك دلوقتي.'],
            ['growth.volunteer_kit.templates_label', 'قوالب الصور'],
        ];

        return self::texts($rows, 'growth', 'نصّ ظاهر في صفحات النموّ العامّة وحقيبة المتطوّع.');
    }

    // ---------------------------------------------------------------- الشكاوى (11)

    private static function complaints(): array
    {
        $rows = [
            ['complaints.field.attachment_label', 'مرفق (اختياريّ)'],
            ['complaints.field.body_label', 'نصّ الشكوى أو المقترح'],
            ['complaints.field.body_placeholder', 'اكتب رسالتك هنا…'],
            ['complaints.field.reason_label', 'السبب'],
            ['complaints.field.reason_placeholder', 'اختر السبب'],
            ['complaints.field.submit_label', 'إرسال'],
            ['complaints.field.title_label', 'العنوان المختصر'],
            ['complaints.field.title_placeholder', 'مثال: اقتراح تحسين المنصّة'],
            ['complaints.field.type_label', 'النوع'],
            ['complaints.field.wants_contact_label', 'هل ترغب في التواصل معك؟'],
            ['complaints.field.wants_contact_no', 'لا'],
            ['complaints.field.wants_contact_yes', 'نعم'],
            ['complaints.status.answered_label', 'تمّ الردّ'],
            ['complaints.status.closed_label', 'مغلقة'],
            ['complaints.status.in_review_label', 'قيد المراجعة'],
            ['complaints.status.open_label', 'مفتوحة'],
            ['complaints.type.complaint_label', 'شكوى'],
            ['complaints.type.suggestion_label', 'مقترح'],

            // شاشة الأدمن وأسباب الشكاوى
            ['complaints.admin.queue_label', 'الشكاوى'],
            ['complaints.admin.section_label', 'التوجيه والدعم'],
            ['complaints.reasons.add_label', 'إضافة سبب'],
            ['complaints.reasons.defaults_hint', 'الافتراضيّ:'],
            ['complaints.reasons.in_use_suffix', 'تذكرة مرتبطة'],
            ['complaints.reasons.new_placeholder', 'سبب جديد'],
            ['complaints.reasons.page_subtitle', 'دي القائمة اللي بيختار منها المستخدم — عدّلها زيّ ما تحبّ.'],
            ['complaints.reasons.page_title', 'أسباب الشكاوى والمقترحات'],
            ['complaints.reasons.remove_label', 'حذف السبب'],
            ['complaints.reasons.save_label', 'حفظ الأسباب'],
        ];

        return self::texts($rows, 'complaints', 'نصّ ظاهر في فورم الشكاوى والمقترحات وحالاتها.');
    }

    // ---------------------------------------------------------------- السيرة الذاتيّة (21.2)

    private static function cv(): array
    {
        $rows = [
            ['cv.field.certificate_url_label', 'رابط الشهادة'],
            ['cv.field.course_date_label', 'تاريخ الحصول'],
            ['cv.field.course_name_label', 'اسم الدورة'],
            ['cv.field.course_serial_label', 'رقم الشهادة (اختياريّ)'],
            ['cv.field.course_url_label', 'رابط الشهادة (اختياريّ)'],
            ['cv.field.description_en_label', 'Description (English) — optional'],
            ['cv.field.organization_label', 'المنظمة'],
            ['cv.field.provider_label', 'جهة الإصدار'],
            ['cv.field.volunteer_role_label', 'الدور'],
            ['cv.row.reorder_label', 'اسحب لإعادة الترتيب'],
            ['cv.courses.add_label', 'إضافة دورة جديدة'],
            ['cv.courses.empty_hint', 'الدورات اللي خدتها بره المنصّة كمان بتتحسب.'],
            ['cv.volunteering.add_label', 'إضافة تجربة تطوّعيّة'],
            ['cv.volunteering.empty_hint', 'أيّ مبادرة أو عمل مجتمعيّ بيفرق — سجّله.'],
            ['cv.section.courses_label', 'الدورات التدريبيّة'],
            ['cv.section.trainings_label', 'تدريبات المنصّة المكتملة'],
            ['cv.section.volunteering_label', 'الخبرة التطوّعيّة'],
            ['cv.step.courses_label', 'الدورات التدريبيّة'],
            ['cv.step.volunteering_label', 'الخبرة التطوّعيّة'],
            ['cv.section.certificate_fallback', 'شهادة'],
            ['cv.section.certificate_number_prefix', 'رقم'],
            ['cv.export.confirm_label', 'أكّد وحمّل النسخة النظيفة'],
            ['cv.export.confirm_notice', 'دي معاينة بعلامة مائيّة. التحميل النهائيّ بالقالب ده هيخصم :price تذكرة (رصيدك :before ⟵ :after).'],
            ['cv.template.selected_paid_message', 'اتغيّر القالب — المعاينة بعلامة مائيّة، و:price تذكرة هتتخصم عند التحميل.'],
        ];

        return array_merge(
            self::texts($rows, 'cv', 'عنوان قسم أو حقل في أداة بناء السيرة الذاتيّة.'),
            [
                ['cv.photo.size_px', 'cv', 'مقاس صورة السيرة (بكسل)', 'number', '300', '300 = مربّع 300×300 بكسل.'],
                ['cv.watermark.opacity_percent', 'cv', 'شفافيّة العلامة المائيّة على المعاينة (%)', 'number', '10', '10 = خفيفة تكفي للحماية ولا تمنع القراءة.'],
            ],
        );
    }

    // ---------------------------------------------------------------- صفحة التطوّع العامّة

    private static function volunteering(): array
    {
        return [
            ['volunteering.landing.title', 'volunteer_page', 'عنوان صفحة «تطوّع معنا» العامّة', 'string', 'تطوّع معنا', 'أوّل ما يقرؤه الزائر قبل التسجيل.'],
            ['volunteering.landing.body', 'volunteer_page', 'السطر التعريفيّ لصفحة التطوّع العامّة', 'text', 'انضمّ لفريقٍ بيتعلّم وبيبني.', ''],
            ['volunteering.landing.cta', 'volunteer_page', 'نصّ زرّ صفحة التطوّع العامّة', 'string', 'ابدأ المسار التأهيليّ', 'الفعل الرئيسيّ الواحد للصفحة (2.15).'],
            ['volunteering.landing.sections', 'volunteer_page', 'أقسام صفحة التطوّع العامّة', 'json', '[]', 'قائمة كتل المحتوى — فاضية = الصفحة بالهيرو وحده.'],
        ];
    }

    // ---------------------------------------------------------------- متفرّقات

    private static function misc(): array
    {
        return [
            // كروت KPI في البروفايل: المفتاح يُركَّب وقت التشغيل من مفتاح الكرت،
            // فلا يمسكه فحصُ النصّ — ولافتاتها كانت محروقة في `ProfileTabs`.
            ['account.profile.kpi.level_label', 'account', 'لافتة كرت مستوى الحساب', 'string', 'مستوى الحساب + XP', ''],
            ['account.profile.kpi.tickets_label', 'account', 'لافتة كرت رصيد التذاكر', 'string', 'رصيد التذاكر', ''],
            ['account.profile.kpi.streak_label', 'account', 'لافتة كرت ستريك نادي الخامسة', 'string', 'ستريك نادي الخامسة', ''],
            ['account.profile.kpi.certificates_label', 'account', 'لافتة كرت الشهادات', 'string', 'الشهادات', ''],
            ['account.profile.kpi.courses_label', 'account', 'لافتة كرت التدريبات', 'string', 'التدريبات (مكتملة/جارية)', ''],
            ['account.profile.kpi.rank_label', 'account', 'لافتة كرت ترتيب الليدر بورد', 'string', 'ترتيب الليدر بورد', ''],
            ['account.profile.kpi.ambassador_label', 'account', 'لافتة كرت لقب السفير', 'string', 'لقب السفير', ''],

            ['account.profile.experience.empty_action', 'account', 'زرّ الحالة الفارغة للخبرات في البروفايل', 'string', 'ابدأ سيرتك', ''],
            ['account.profile.experience.empty_message', 'account', 'رسالة البروفايل بلا سيرة ذاتيّة', 'string', 'لسّه مفيش سيرة ذاتيّة هنا.', 'الحالة الفارغة تشجّع ولا تعاتب (2.17).'],
            ['account.profile.experience.hidden_message', 'account', 'رسالة إخفاء الخبرات على البروفايل', 'string', 'الخبرات مش متاحة على البروفايل ده.', 'تظهر لمّا يمنع صاحب البروفايل إظهار خبراته.'],

            ['admin.users.country_pin_hint', 'admin_users', 'شرح تثبيت الدولة في صفحة المستخدم', 'text', 'الكشف التلقائيّ بيتبع مكانه دلوقتي — والتثبيت اليدويّ بيعلو عليه ومابيتدهسش.', ''],
            ['admin.users.referral_gift_note', 'admin_users', 'شرح توقيت صرف هديّة الإحالة', 'text', 'الهديّة بتتصرف للطرفين بعد قبول الحساب — مش وقت التسجيل.', ''],
            ['admin.users.sessions_hint', 'admin_users', 'شرح بلوك الجلسات في صفحة المستخدم', 'text', 'الأجهزة المفتوح عليها الحساب دلوقتي — وإنهاء الجلسات بيقفلها كلّها.', ''],

            ['backups.schedule.kind', 'backups', 'نوع النسخة المجدولة الافتراضيّ', 'string', 'full', 'full = نسخة كاملة (قاعدة البيانات + الملفّات).'],
            ['celebrations.labels.close', 'gamification_celebrations', 'نصّ زرّ إغلاق الاحتفال', 'string', 'تمام', ''],
            ['learning.paths.friends_limit', 'paths', 'عدد الزملاء الظاهرين على المسار', 'number', '12', '12 = اثنا عشر وجهًا قبل «وغيرهم».'],
            ['learning.social.active_window_minutes', 'learning', 'نافذة «يتعلّم الآن» (دقيقة)', 'number', '30', '30 = مَن كان نشطًا خلال آخر نصف ساعة.'],

            ['store.currency.default', 'store', 'العملة الافتراضيّة للتسعير', 'string', 'coins', 'العملة التي يُسعَّر بها ما لم يُنَصّ على غيرها.'],
            ['store.currencies', 'store', 'العملات المتاحة في المتجر', 'json', '["coins","tickets","xp"]', 'ترتيبها هو ترتيب ظهورها في شاشات التسعير.'],
            ['library.modal.invoice_fees', 'library', 'لافتة الرسوم في إيصال المكتبة', 'string', 'الرسوم', ''],
            ['library.modal.invoice_method', 'library', 'لافتة طريقة الدفع في إيصال المكتبة', 'string', 'طريقة الدفع', ''],
            ['ux.lists.per_page', 'ux', 'عدد صفوف الصفحة الافتراضيّ للقوائم', 'number', '25', '25 = خمسة وعشرون صفًّا قبل الترقيم.'],
            ['notifications.rate_limit.exempt_categories', 'notifications', 'فئات الإشعارات المعفاة من حدّ الهدوء', 'json', '["account","security","certificate"]', 'ما يخصّ الحساب والأمان والشهادات يصل دائمًا ولا يتأجّل.'],
            ['platform.identity.logo_path', 'platform', 'شعار المنصّة على العلامة المائيّة', 'media', '', 'فاضي = بلا شعار على النسخة المعاينة.'],

            ['ux.advanced_mode.enabled', 'ux', 'إتاحة الوضع المتقدّم', 'bool', '1', 'إطفاؤه يُبقي الجميع على الوضع المبسّط (2.15).'],
            ['ux.advanced_mode.label', 'ux', 'تسمية الوضع المتقدّم', 'string', 'وضع متقدّم', ''],
            ['ux.advanced_mode.roles', 'ux', 'الأدوار التي يتاح لها الوضع المتقدّم', 'json', '[]', 'فاضية = متاح للجميع؛ وإلّا لا بدّ من أحد الأدوار.'],
            ['ux.first_time.content', 'ux', 'خطوات «أوّل مرّة» لكلّ شاشة', 'json', '[]', 'شاشة ⟵ خطواتها. الفاضي يقع على القالب الافتراضيّ.'],
            ['ux.first_time.default_template', 'ux', 'قالب «أوّل مرّة» الافتراضيّ', 'json', '[]', 'يُستعمَل لأيّ شاشة بلا خطواتٍ خاصّة بها.'],

            ['attestations.public.opened_message', 'attestations', 'رسالة فتح رابط الإفادة العامّ', 'string', 'الرابط شغّال ✓', ''],
            ['attestations.public.closed_message', 'attestations', 'رسالة قفل رابط الإفادة العامّ', 'string', 'الرابط اتقفل ✓', ''],
            ['attestations.public.slug_length', 'attestations', 'طول الرابط العامّ للإفادة (حروف)', 'number', '12', '12 = اثنا عشر حرفًا عشوائيًّا يصعب تخمينها.'],
            ['attestations.public.toggle_label', 'attestations', 'تسمية مفتاح الرابط العامّ للإفادة', 'string', 'شغّل الرابط العامّ للإفادة', ''],
            ['attestations.public.hint', 'attestations', 'شرح الرابط العامّ للإفادة', 'text', 'الرابط مقفول لحدّ ما تشغّله بنفسك — وتقدر تقفله في أيّ وقت.', ''],
            ['attestations.public.error_message', 'attestations', 'رسالة تعذّر تغيير حالة الرابط', 'text', 'مقدرناش نغيّر الحالة — راجع النت وجرّب تاني.', 'رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب).'],

            ['onboarding.placement.xp_reason', 'onboarding', 'سبب نقاط الخبرة في الاختبار التمهيديّ', 'string', 'إجابة صحيحة في الاختبار التمهيديّ', 'يظهر في كشف حساب النقاط.'],
            ['onboarding.placement.tickets_reason', 'onboarding', 'سبب التذاكر في الاختبار التمهيديّ', 'string', 'مكافأة سؤال في الاختبار التمهيديّ', 'يظهر في كشف حساب المحفظة.'],

            // المهل المجمَّدة أثناء الصيانة — كانت خريطةً محروقة في `MaintenanceService`
            ['system.maintenance.frozen_targets', 'maintenance', 'الجداول والأعمدة المجمَّدة أثناء الصيانة', 'json', self::frozenTargets(), 'جدول ⟵ أعمدة المهل التي تُزاح بمدّة الصيانة. الفاضي = الخريطة المدمَجة.'],
        ];
    }

    /**
     * مصفوفة الإشعارات: `notifications.matrix.<النوع>.<القناة>` — مفتاحٌ مركَّب
     * وقت التشغيل من `notifications.types` و`notifications.channels`. نولّدها
     * **من الإعدادين نفسيهما** لا من قائمةٍ ثانية، فلو أضاف الأدمن نوعًا جديدًا
     * بقيت المصفوفة على مصدرٍ واحد بلا تكرارٍ يفترق.
     *
     * والافتراضيّ كما يقرؤه القالب بالحرف: **الجرس مفعَّل والباقي مطفأ**.
     *
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string}>
     */
    private static function notificationMatrix(): array
    {
        $types = (array) setting('notifications.types', []);
        $channels = (array) setting('notifications.channels', []);

        $rows = [];

        foreach ($types as $type => $typeLabel) {
            foreach ($channels as $channel => $channelLabel) {
                $rows[] = [
                    "notifications.matrix.{$type}.{$channel}",
                    'notifications',
                    "إشعار «{$typeLabel}» عبر «{$channelLabel}»",
                    'bool',
                    $channel === 'bell' ? '1' : '0',
                    'إطفاؤه يمنع هذا الإشعار على هذه القناة وحدها.',
                ];
            }
        }

        return $rows;
    }

    /** الخريطة المدمَجة في `MaintenanceService::frozenTargets()` — منقولةٌ حرفيًّا */
    private static function frozenTargets(): string
    {
        return (string) json_encode([
            'enrollments' => ['deadline_at'],
            'tasks' => ['deadline_at', 'merge_window_at'],
            'task_submissions' => ['fix_due_at'],
            'task_contributions' => ['internal_deadline_at', 'owner_review_due_at'],
            'contribution_checkpoints' => ['response_due_at'],
            'objections' => ['sla_due_at'],
            'escalations' => ['window_due_at'],
            'escalation_steps' => ['due_at'],
            'arbitrations' => ['window_due_at'],
            'placement_requests' => ['respond_due_at'],
            'consent_requests' => ['request_expires_at', 'consent_expires_at', 'cooldown_until'],
            'meetings' => ['attendance_closes_at'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * لافتة النصّ الظاهر = النصّ نفسه بين قوسين: «نصّ ‹تاريخ الإصدار›».
     * لماذا؟ لأنّ لافتةً مثل «لافتة الحقل رقم 12» لا تعرّف الأدمن بشيء، أمّا
     * النصّ الافتراضيّ فيقول له **أين يظهر هذا الحقل** من أوّل نظرة.
     *
     * @param  list<array{0:string,1:string}>  $rows
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string}>
     */
    private static function texts(array $rows, string $group, string $hint): array
    {
        return array_map(function (array $row) use ($group, $hint) {
            [$key, $value] = $row;

            // النصّ الطويل في Textarea لا في سطر واحد
            $type = mb_strlen($value) > 40 ? 'text' : 'string';
            $label = mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value;

            return [$key, $group, 'نصّ ‹'.$label.'›', $type, $value, $hint];
        }, $rows);
    }
}
