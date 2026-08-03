<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\LearningPath;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات مجال الامتحانات والشهادات (4 · 8 · 12.5 · 24.5).
 * **لا يُسجَّل في DatabaseSeeder** — يُجمَّع مع باقي المجالات لاحقًا.
 */
class ExamDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->traineeExamPermissions();
        $this->templates();
        $this->demoContent();

        Cache::forget('settings');
    }

    /** كلّ رقم ونصّ في المجال إعدادٌ في لوحة الإدارة — لا شيء محروق في الكود (2.13) */
    public function settings(): void
    {
        $rows = [
            // ---------------- الامتحان (4.2 · 24.5)
            ['exams.questions.max', 'exams', 'أقصى عدد أسئلة في الامتحان', 'number', '20'],
            ['exams.timer.warn_seconds', 'exams', 'ثوانٍ تحذير قرب انتهاء الوقت', 'number', '60'],
            // ⭐ 4.2: امتحان التدريب **بتذكرة تُخصَم بمجرّد الدخول**، وامتحان شهادة المسار وحده بالكوينز (16)
            ['exams.wallet.currency_code', 'exams', 'عملة امتحان شهادة المسار', 'string', 'coins'],
            ['exams.wallet.tickets_currency_code', 'exams', 'عملة امتحان التدريب', 'string', 'tickets'],
            ['exams.tickets.course_exam', 'exams', 'تذاكر دخول الامتحان النهائيّ للتدريب', 'number', '1'],
            ['exams.pass_score.default', 'exams', 'درجة النجاح الافتراضيّة (%)', 'number', '70'],
            ['exams.wallet.charge_reason', 'exams', 'وصف معاملة رسوم الامتحان', 'string', 'دخول امتحان'],
            ['exams.certificate_type_map', 'exams', 'ربط نوع المحتوى بنوع الشهادة', 'json', '{"App\\\\Models\\\\Course":"course","App\\\\Models\\\\LearningPath":"path"}'],
            ['exams.qualifying_certificate_type', 'exams', 'نوع شهادة المسار التأهيليّ', 'string', 'qualifying'],
            // ⛔ `volunteer.qualifying.course_id` أُلغي: التأهيليّ **مسار** لا كورس
            // (13.4-ب هو نصّ التعريف الحاكم)، ومفتاحه `volunteer.qualifying.path_id`
            // في سيدر مجال التوظيف — مفتاح واحد لكلّ معنًى (2.13).

            // نصوص الامتحان — محايدة تشجّع ولا تعاتب (2.17-ج)
            ['exams.messages.started', 'exams', 'رسالة بدء الامتحان', 'text', 'بالتوفيق — ركّز وخُد وقتك.'],
            ['exams.messages.autosaved', 'exams', 'رسالة الحفظ التلقائيّ', 'string', 'اتحفظ ✓'],
            ['exams.messages.offline', 'exams', 'رسالة انقطاع الشبكة', 'text', 'الشبكة اتقطعت — إجاباتك محفوظة، وهنكمّل من مكانك أوّل ما ترجع.'],
            ['exams.messages.offline_short', 'exams', 'رسالة انقطاع الشبكة المختصرة', 'string', 'إجاباتك محفوظة — هنبعتها أوّل ما الشبكة ترجع.'],
            ['exams.messages.time_up', 'exams', 'رسالة انتهاء الوقت', 'text', 'خلص الوقت — سلّمنا إجاباتك تلقائيًّا.'],
            ['exams.messages.passed', 'exams', 'رسالة النجاح', 'text', 'مبروك يا [الاسم] 🎉 عدّيت الامتحان.'],
            ['exams.messages.failed', 'exams', 'رسالة الرسوب (محايدة تشجّع)', 'text', 'مش المرّة دي. راجع الدروس وجرّب تاني — ومحاولتك الجاية متاحة حسب قواعد الامتحان.'],
            ['exams.messages.cooldown', 'exams', 'رسالة مهلة المحاولة التالية', 'text', 'لسّه بدري على المحاولة الجاية — استنّى شويّة وراجع الدروس.'],
            ['exams.messages.no_attempts_left', 'exams', 'رسالة نفاد المحاولات', 'text', 'خلصت محاولاتك في الامتحان ده.'],
            ['exams.messages.insufficient_balance', 'exams', 'رسالة عدم كفاية الرصيد', 'text', 'رصيدك مايكفّيش لدخول الامتحان — اشحن محفظتك وارجع.'],
            // التذاكر تُكتسَب ولا تُشحَن (7.1) — فالرسالة تدلّ على طريق الكسب لا على الشحن
            ['exams.messages.insufficient_tickets', 'exams', 'رسالة نقص التذاكر', 'text', 'محتاج تذكرة عشان تدخل الامتحان — كمّل درسًا أو أكمل ستريكك وهترجع تلاقيها.'],
            ['exams.messages.closed', 'exams', 'رسالة الامتحان المقفول', 'text', 'الامتحان ده مقفول دلوقتي.'],
            // ⭐ حاجز الإتاحة (5): الامتحان جزءٌ من التدريب، وسبب القفل وموعد الفتح
            // يأتيان من `AvailabilityService` بساعة المستخدم فيُلحَقان بهذا النصّ (2.17-ج)
            ['exams.messages.course_locked', 'exams', 'رسالة قفل الامتحان خارج إتاحة التدريب (5)', 'text', 'الامتحان جزء من التدريب، والتدريب مقفول دلوقتي.'],
            ['exams.messages.reentry_notice', 'exams', 'نصّ بوب-أب ما قبل امتحان العائد (13.4-ق)', 'text', 'الامتحان ده بيثبت جاهزيّتك دلوقتي. أوّل ما تبدأ، شهادتك التأهيليّة القديمة هتتسجّل «منتهية» — مش هتتمسح، هتفضل في سجلّك بتاريخها، وبالنجاح هتصدرلك شهادة جديدة.'],

            // مسمّيات شاشة الامتحان
            ['exams.labels.before_start', 'exams', 'عنوان ما قبل البدء', 'string', 'قبل ما تبدأ — اطّلع على الشروط'],
            ['exams.labels.confirm_title', 'exams', 'عنوان بوب-أب التأكيد', 'string', 'تأكيد دخول الامتحان'],
            ['exams.labels.ready', 'exams', 'زرّ التأكيد', 'string', 'أنا جاهز'],
            ['exams.labels.not_now', 'exams', 'زرّ التأجيل', 'string', 'مش دلوقتي'],
            ['exams.labels.topup', 'exams', 'زرّ شحن المحفظة', 'string', 'اشحن المحفظة'],
            ['exams.labels.submit', 'exams', 'زرّ التسليم', 'string', 'سلّم الامتحان'],
            ['exams.labels.review', 'exams', 'عنوان شاشة المراجعة', 'string', 'مراجعة قبل التسليم'],
            // ⭐ 4.2: «كل دخول = تذكرة» — فالافتراضيّ **بلا سقفٍ للمحاولات**، وهذا وسمه
            ['exams.labels.attempts_unlimited', 'exams', 'وسم المحاولات بلا حدّ (4.2)', 'string', 'بلا حدّ — كلّ دخول بتذكرة'],

            // ---------------- الشهادات (8 · 12.5)
            ['certificates.numbering.default_prefix', 'certificates', 'بادئة الترقيم الافتراضيّة', 'string', 'HC'],
            ['certificates.numbering.separator', 'certificates', 'فاصل الترقيم', 'string', '-'],
            ['certificates.numbering.padding', 'certificates', 'خانات التسلسل', 'number', '6'],
            ['certificates.render.default_width_px', 'certificates', 'عرض الشهادة (بكسل)', 'number', '1754'],
            ['certificates.render.default_height_px', 'certificates', 'ارتفاع الشهادة (بكسل)', 'number', '1240'],
            ['certificates.render.font_path', 'certificates', 'مسار خطّ الشهادة (TTF على الخادم)', 'string', ''],
            ['certificates.render.date_format', 'certificates', 'صيغة التاريخ', 'string', 'Y/m/d'],
            ['certificates.render.line_height', 'certificates', 'معامل ارتفاع السطر', 'number', '1.6'],
            ['certificates.render.max_chars_per_line', 'certificates', 'أقصى حروف في السطر', 'number', '48'],
            ['certificates.render.qr_size_px', 'certificates', 'مقاس الـQR (بكسل)', 'number', '240'],
            ['certificates.render.qr_margin_modules', 'certificates', 'هامش الـQR (وحدات)', 'number', '4'],
            ['certificates.render.cache_enabled', 'certificates', 'كاش صور الشهادات', 'bool', '1'],
            ['certificates.render.http_cache_seconds', 'certificates', 'كاش المتصفّح للصورة (ثوانٍ)', 'number', '3600'],
            ['certificates.render.paper_color', 'certificates', 'لون خلفيّة التصميم الافتراضيّ', 'color', '#0b1512'],
            ['certificates.render.brand_color', 'certificates', 'لون الهويّة في الشهادة', 'color', '#00d4b8'],
            ['certificates.render.honor_color', 'certificates', 'لون الشرف (الإطار)', 'color', '#d4af37'],
            ['certificates.render.text_color', 'certificates', 'لون النصّ الافتراضيّ', 'color', '#e8f5f2'],
            ['certificates.render.expired_color', 'certificates', 'لون وسم «منتهية»', 'color', '#94a3b8'],
            ['certificates.render.revoked_color', 'certificates', 'لون وسم «ملغاة»', 'color', '#ef4444'],
            ['certificates.render.status_y', 'certificates', 'موضع وسم الحالة رأسيًّا (نسبة)', 'number', '0.13'],
            ['certificates.render.heading', 'certificates', 'عنوان الشهادة', 'string', 'شهادة معتمدة'],
            ['certificates.render.subheading', 'certificates', 'سطر ما قبل الاسم', 'string', 'تشهد المنصّة بأنّ'],
            ['certificates.render.completion_text', 'certificates', 'سطر ما قبل اسم التدريب', 'string', 'قد أتمّ بنجاح'],
            ['certificates.accreditation.default_name', 'certificates', 'اسم الاعتماد الافتراضيّ', 'string', 'اعتماد المنصّة'],
            ['certificates.celebration.key', 'certificates', 'مفتاح احتفال إصدار الشهادة', 'string', 'certificate.issued'],
            ['certificates.expired.reason_newer_exam', 'certificates', 'سبب الانتهاء بامتحانٍ أحدث', 'string', 'انتهى العمل بها بعد دخول صاحبها امتحانًا أحدث'],

            // الحالات الثلاث (13.4-ق): سارية · منتهية · ملغاة
            ['certificates.status.valid_label', 'certificates', 'وسم «سارية»', 'string', 'سارية'],
            ['certificates.status.expired_label', 'certificates', 'وسم «منتهية»', 'string', 'منتهية'],
            ['certificates.status.revoked_label', 'certificates', 'وسم «ملغاة»', 'string', 'ملغاة'],
            ['certificates.status.expired_line', 'certificates', 'سطر الانتهاء في مكتبتي', 'string', 'انتهى العمل بيها في'],
            ['certificates.status.expired_hint', 'certificates', 'توضيح «منتهية»', 'string', 'بعد دخولك امتحانًا أحدث. وهي مش ملغاة.'],
            ['certificates.status.revoked_hint', 'certificates', 'توضيح «ملغاة»', 'string', 'ملغاة — والإلغاء لا يقع إلّا على تزويرٍ مثبَت.'],

            // ---------------- صفحة التحقّق العامّة (8.1 · 21.2-ز)
            ['certificates.verify.title', 'certificates', 'عنوان صفحة التحقّق', 'string', 'التحقّق من الشهادة'],
            ['certificates.verify.intro', 'certificates', 'وصف صفحة التحقّق', 'text', 'اكتب كود الشهادة وتأكّد من صحّتها وصلاحيّتها — بلا تسجيل دخول ولا حساب.'],
            ['certificates.verify.placeholder', 'certificates', 'مثال الكود', 'string', '#HC-2026-000001'],
            ['certificates.verify.submit', 'certificates', 'زرّ التحقّق', 'string', 'تحقّق'],
            ['certificates.verify.not_found', 'certificates', 'نصّ عدم وجود الشهادة', 'text', 'مفيش شهادة بالكود ده في سجلّنا. راجع الكود، ولو شايف إنّ فيه مشكلة بلّغنا.'],
            ['certificates.verify.valid_text', 'certificates', 'نصّ الشهادة السارية', 'text', 'هذه الشهادة سارية وصادرة من المنصّة، وبياناتها مطابقة لسجلّنا.'],
            ['certificates.verify.expired_text', 'certificates', 'نصّ الشهادة المنتهية (13.4-ق)', 'text', 'هذه الشهادة منتهية: صدرت بتاريخ [تاريخ الإصدار] وانتهى العمل بها بتاريخ [تاريخ الانتهاء] بعد دخول صاحبها امتحانًا أحدث. وهي ليست ملغاة ولا مطعونًا في صحّتها.'],
            ['certificates.verify.revoked_text', 'certificates', 'نصّ الشهادة الملغاة', 'text', 'هذه الشهادة ملغاة. الإلغاء لا يقع إلّا على تزويرٍ مثبَت.'],
            ['certificates.verify.footer', 'certificates', 'ذيل صفحة التحقّق', 'text', 'كلّ شهادة عندنا لها كود وQR وتوقيع رقميّ — والتحقّق مفتوح للجميع.'],

            // ---------------- نتيجة التحقّق من التوقيع الرقميّ (8.1 · 12.5-هـ)
            // الحالة الثالثة: صفٌّ موجود وتوقيعُه لا تشتقّه بياناته — لا «سارية» ولا «غير موجودة».
            ['certificates.verify.signature_label', 'certificates', 'لافتة التوقيع الرقميّ', 'string', 'التوقيع الرقميّ'],
            ['certificates.verify.signature_ok', 'certificates', 'وسم تطابق التوقيع', 'string', 'مطابق — البيانات دي هي اللي صدرت'],
            ['certificates.verify.unverified_badge', 'certificates', 'وسم عدم تطابق التوقيع', 'string', 'التوقيع لا يطابق'],
            ['certificates.verify.unverified_title', 'certificates', 'عنوان تعذّر تأكيد الصحّة', 'string', 'ما نقدرش نأكّد صحّة الشهادة دي'],
            ['certificates.verify.unverified_text', 'certificates', 'نصّ عدم تطابق التوقيع', 'text', 'فيه صفّ بالكود ده في سجلّنا، لكن توقيعه الرقميّ مش مطابق للتوقيع اللي بتشتقّه بياناته — يعني البيانات اتغيّرت بعد الإصدار أو الصفّ اتكتب من برّه محرّك الإصدار. عشان كده ما نقدرش نشهد بصحّتها ولا نعرض بياناتها. بلّغنا وهنراجعها.'],
            ['certificates.verify.unsigned_badge', 'certificates', 'وسم الشهادة بلا توقيع', 'string', 'بلا توقيع رقميّ'],
            ['certificates.verify.unsigned_text', 'certificates', 'نصّ الشهادة بلا توقيع', 'text', 'فيه صفّ بالكود ده في سجلّنا لكنّه من غير توقيع رقميّ أصلًا، فما نقدرش نشهد إنّ بياناته هي اللي صدرت. لو استلمت نسخة بالكود ده، بلّغنا وهنراجعها.'],
            ['certificates.report.audit_action', 'certificates', 'اسم حدث بلاغ التزوير في سجلّ التدقيق', 'string', 'certificate.reported'],
            ['certificates.report.thanks', 'certificates', 'رسالة شكر البلاغ', 'text', 'وصلنا بلاغك وهنراجعه — شكرًا إنّك ساعدتنا نحمي قيمة الشهادة.'],

            // بيانات الصفحة المفهرسة (21.1-أ)
            ['certificates.seo.meta_title', 'certificates', 'عنوان الميتا لصفحة الشهادة', 'string', '[الاسم] — [الشهادة] · شهادة معتمدة'],
            ['certificates.seo.meta_description', 'certificates', 'وصف الميتا لصفحة الشهادة', 'text', 'شهادة [الشهادة] الصادرة لـ[الاسم] بتاريخ [التاريخ] — تحقّق من صحّتها هنا.'],
            ['certificates.seo.index_title', 'certificates', 'عنوان صفحة التحقّق بلا كود', 'string', 'التحقّق من الشهادة'],
            ['certificates.seo.index_description', 'certificates', 'وصف صفحة التحقّق بلا كود', 'text', 'تحقّق من صحّة أيّ شهادة صادرة من المنصّة بكودها — بلا تسجيل دخول.'],

            // ---------------- الاحتفال (2.14-3)
            ['celebrations.certificate.title', 'gamification_celebrations', 'عنوان احتفال الشهادة', 'string', 'مبروك يا [الاسم]'],
            ['celebrations.certificate.message', 'gamification_celebrations', 'نصّ احتفال الشهادة', 'text', 'شهادتك الجديدة صدرت — تقدر تشاركها دلوقتي.'],
            ['celebrations.confetti.pieces', 'gamification_celebrations', 'عدد قطع الكونفيتي', 'number', '80'],
            ['celebrations.peak.auto_dismiss_ms', 'gamification_celebrations', 'مدّة إغلاق احتفال الذروة تلقائيًّا', 'number', '9000'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    /**
     * المتدرّب يدخل امتحانه بنطاق SELF — مورد الصلاحيّة اسمه `course_exam`،
     * فنضيفه هنا كي لا يُحجَب صاحب الحساب عن امتحانه.
     */
    private function traineeExamPermissions(): void
    {
        $role = Role::query()->where('key', 'trainee')->first();

        if (! $role) {
            return;
        }

        $permissionIds = Permission::query()
            ->where('resource', 'course_exam')
            ->whereIn('action', ['view', 'export'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $role->id, 'permission_id' => $permissionId, 'scope' => 'SELF'],
                ['effect' => 'allow', 'conditions' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /** قالب افتراضيّ لكلّ نوع شهادة — يعمل من أوّل يوم بلا رفع أيّ خلفيّة (12.5-ب) */
    private function templates(): void
    {
        foreach (CertificateType::query()->get() as $type) {
            CertificateTemplate::updateOrCreate(
                ['certificate_type_id' => $type->id, 'language' => 'ar', 'name' => 'التصميم الافتراضيّ'],
                [
                    'width_px' => 1754,
                    'height_px' => 1240,
                    'background_path' => null,
                    'layers' => [],
                    'is_default' => true,
                    'version' => 1,
                ],
            );
        }
    }

    /** تدريب ومسار وامتحانان بأسئلة عربيّة واقعيّة */
    private function demoContent(): void
    {
        $course = Course::updateOrCreate(
            ['slug' => 'usus-altatawwu'],
            [
                'name_ar' => 'أسس العمل التطوّعيّ',
                'description_ar' => 'تدريب تمهيديّ يشرح قواعد العمل التطوّعيّ داخل المنصّة وأخلاقيّاته.',
                'is_free' => true,
                'status' => 'published',
                'published_at' => now(),
            ],
        );

        $path = LearningPath::updateOrCreate(
            ['slug' => 'masar-alqiada'],
            [
                'name_ar' => 'مسار القيادة الميدانيّة',
                'description_ar' => 'مسار متكامل يبني مهارات قيادة الفرق في الميدان.',
                'status' => 'published',
                'published_at' => now(),
            ],
        );

        $courseExam = Exam::updateOrCreate(
            ['examable_type' => $course->getMorphClass(), 'examable_id' => $course->id],
            [
                'title_ar' => 'الامتحان النهائيّ — أسس العمل التطوّعيّ',
                'duration_minutes' => 30,
                // ⭐ 4.2 حرفيًّا: «**بدون مدة انتظار** … **كل دخول = تذكرة**»
                // — فلا سقفَ للمحاولات (0) ولا انتظارَ بعد الرسوب (0)
                'attempts_allowed' => 0,
                'retry_cooldown_hours' => 0,
                'pass_score' => 70,
                'price_coins' => 0,
                'is_active' => true,
            ],
        );

        $pathExam = Exam::updateOrCreate(
            ['examable_type' => $path->getMorphClass(), 'examable_id' => $path->id],
            [
                'title_ar' => 'امتحان شهادة مسار القيادة الميدانيّة',
                'duration_minutes' => 45,
                // امتحان **شهادة المسار** مدفوعٌ بالكوينز لا بالتذاكر، ولا نصّ في
                // الدستور يأذن بسقفٍ ولا بمدّة انتظارٍ له — فبيانات العرض لا تفتعل
                // أيّهما، والمفتاحان يبقيان قابلَين لضبط المالك (2.13).
                'attempts_allowed' => 0,
                'retry_cooldown_hours' => 0,
                'pass_score' => 70,
                'price_coins' => 150, // امتحان شهادة المسار مدفوع بالكوينز (24.5)
                'is_active' => true,
            ],
        );

        $this->questions($courseExam, [
            ['choice', 'إيه أوّل خطوة لازم تعملها لمّا تستلم مهمّة جديدة؟', ['أقرأ وصف المهمّة والمعايير', 'أبدأ التنفيذ فورًا', 'أستنّى حدّ يفكّرني'], 'أقرأ وصف المهمّة والمعايير'],
            ['choice', 'المهمّة المتعثّرة الصحّ إنّك تعمل فيها إيه؟', ['أعلن التعثّر بسببه', 'أسيبها لحدّ ما تتحلّ', 'أقفلها بلا تسليم'], 'أعلن التعثّر بسببه'],
            ['number', 'كام يوم أقصى مدّة للتعثّر قبل التصعيد؟', null, '3'],
            ['text', 'اكتب في سطر: إيه الفرق بين الاعتذار عن المهمّة وتركها بلا ردّ؟', null, 'الاعتذار إبلاغ مبكّر يسمح بإعادة الإسناد'],
        ]);

        $this->questions($pathExam, [
            ['choice', 'القائد الميدانيّ بيبدأ اجتماعه بإيه؟', ['بالهدف والمخرجات', 'بالحضور والغياب', 'بالملاحظات الشخصيّة'], 'بالهدف والمخرجات'],
            ['choice', 'لو عضو في فريقك اتأخّر مرّتين، الخطوة الأولى إيه؟', ['أسأله وأفهم السبب', 'أخصم من درجته فورًا', 'أنقله لفريق تاني'], 'أسأله وأفهم السبب'],
            ['number', 'كام ساعة نافذة القرار الافتراضيّة لكلّ مستوى تصعيد؟', null, '24'],
        ]);
    }

    private function questions(Exam $exam, array $rows): void
    {
        foreach ($rows as $index => [$type, $prompt, $options, $correct]) {
            ExamQuestion::updateOrCreate(
                ['exam_id' => $exam->id, 'sort_order' => $index + 1],
                [
                    'type' => $type,
                    'prompt' => $prompt,
                    'options' => $options,
                    'correct_answer' => $correct,
                    'weight' => 1,
                ],
            );
        }
    }
}
