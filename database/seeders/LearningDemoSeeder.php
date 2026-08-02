<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonQuestion;
use App\Models\MediaItem;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\SettingsCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بذرة مجال التعلّم: إعداداته الكاملة (2.13 — ممنوع أيّ رقم أو نصّ محروق)
 * ثمّ بيانات تجريبيّة عربيّة واقعيّة: مسار واحد وتدريبان بسيكشنز ودروس وأسئلة.
 *
 * ملاحظة: لا تُسجَّل في DatabaseSeeder — تُجمَّع مركزيًّا (دليل البناء 7).
 */
class LearningDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->availabilitySettings();
        $path = $this->path();
        $this->communicationCourse($path);
        $this->timeCourse($path);

        Cache::forget('settings');

        $this->command?->info('بذرة التعلّم: '.Course::count().' تدريبات · '.Lesson::count().' دروس');
    }

    // ------------------------------------------------------------ الإعدادات

    /** كلّ رقم ونصّ في هذا المجال يُقرأ من هنا — لا من الكود (2.13) */
    private function settings(): void
    {
        $rows = [
            // ---- العناوين والتنقّل
            ['learning.courses.title', 'string', 'تدريباتي'],
            ['learning.courses.subtitle', 'string', 'كلّ تدريباتك وحالة كلٍّ منها'],
            ['learning.paths.title', 'string', 'المسارات'],
            ['learning.breadcrumb.root', 'string', 'تعلّمي'],
            ['learning.more.label', 'string', 'أفعال أخرى'],
            ['learning.course.outline_title', 'string', 'محتويات التدريب'],
            ['learning.course.lesson_search', 'string', 'ابحث في الدروس'],
            ['learning.paths.contents_title', 'string', 'محتويات المسار'],

            // ---- الحالات والأفعال
            ['learning.status.not_started', 'string', 'لم أبدأ'],
            ['learning.status.active', 'string', 'جارٍ'],
            ['learning.status.completed', 'string', 'مكتمل'],
            ['learning.cta.resume', 'string', 'أكمل آخر درس'],
            ['learning.cta.continue', 'string', 'أكمل'],
            ['learning.cta.review', 'string', 'مراجعة'],
            ['learning.cta.view_state', 'string', 'عرض الحالة'],
            ['learning.cta.store', 'string', 'تصفّح المتجر'],
            ['learning.cta.certificates', 'string', 'شهاداتي'],
            ['learning.cta.library', 'string', 'مكتبتي'],
            ['learning.cta.next', 'string', 'التالي'],
            ['learning.cta.previous', 'string', 'السابق'],

            // ---- الفلاتر (ثلاثة ظاهرة والباقي مطويّ — 2.15-أ-4)
            ['learning.filter.status', 'string', 'الحالة'],
            ['learning.filter.path', 'string', 'المسار'],
            ['learning.filter.search', 'string', 'بحث'],
            ['learning.filter.search_placeholder', 'string', 'اسم التدريب'],
            ['learning.filter.all', 'string', 'الكلّ'],
            ['learning.filter.apply', 'string', 'تطبيق'],
            ['learning.filter.sort', 'string', 'الترتيب'],
            ['learning.filter.sort_recent', 'string', 'الأحدث'],
            ['learning.filter.sort_name', 'string', 'الاسم'],

            // ---- الحالات الفارغة: سطر واحد + زرّ واحد، تشجّع ولا تعاتب (2.17-ج)
            ['learning.empty.courses', 'string', 'أوّل تدريب لك على بُعد خطوة واحدة'],
            ['learning.empty.courses_cta', 'string', 'تصفّح المتجر'],
            ['learning.empty.paths', 'string', 'أوّل مسار لك على بُعد خطوة واحدة'],
            ['learning.empty.paths_cta', 'string', 'تصفّح المتجر'],

            // ---- التقدّم والدروس
            ['learning.progress.label', 'string', 'التقدّم'],
            ['learning.progress.complete_percent', 'number', '100'],
            ['learning.lesson.unit_plural', 'string', 'دروس'],
            ['learning.lesson.minutes_suffix', 'string', 'دقيقة'],
            ['learning.lesson.default_duration_minutes', 'number', '5'],
            ['learning.lesson.done_badge', 'string', 'مكتمل'],
            ['learning.lesson.current_badge', 'string', 'الدرس الحاليّ'],
            ['learning.lesson.panel_title', 'string', 'دروس التدريب'],
            ['learning.lesson.read_progress_label', 'string', 'تقدّمك في الدرس'],
            ['learning.lesson.complete_title', 'string', 'إنهاء الدرس'],
            ['learning.lesson.complete_cta', 'string', 'أنهيت الدرس'],
            ['learning.lesson.done_message', 'string', 'تمّ تسجيل إكمال الدرس'],
            ['learning.lesson.already_done_message', 'string', 'هذا الدرس مسجَّل مكتملًا من قبل'],
            ['learning.course.done_message', 'string', 'أحسنت — أنهيت هذا التدريب بالكامل'],

            // ---- الإتاحة والقفل: السبب مكتوب دائمًا ولا يُخفى العنصر (24.5)
            ['learning.course.published_status', 'string', 'published'],
            ['learning.lock.badge', 'string', 'مقفول'],
            ['learning.lock.forced_order_reason', 'string', 'يفتح بعد إكمال الدرس السابق'],
            ['learning.lock.quiz_reason', 'string', 'أجب عن أسئلة الدرس أوّلًا ليُحتسَب إكماله'],
            ['learning.lock.expired_reason', 'string', 'انتهت فترة إتاحة هذا التدريب'],
            ['learning.lock.unpublished_reason', 'string', 'هذا التدريب غير متاح حاليًّا'],
            ['learning.lock.scheduled_reason', 'string', 'يفتح في موعده'],

            // ---- الإتاحة الزمنيّة (5): ماذا حدث + متى يفتح — بتوقيت المستخدم دائمًا
            ['learning.lock.outside_period_reason', 'string', 'دلوقتي إحنا خارج فترات إتاحة التدريب'],
            ['learning.lock.outside_daily_reason', 'string', 'التدريب بيفتح يوميًّا من :from إلى :to بتوقيتك'],
            ['learning.lock.periods_over_reason', 'string', 'خلصت كلّ فترات إتاحة هذا التدريب'],
            ['learning.lock.opens_at_prefix', 'string', 'يفتح'],
            ['learning.availability.stamp_format', 'string', 'l j F — H:i'],
            ['learning.availability.countdown_label', 'string', 'باقي على الفتح'],
            ['learning.availability.opening_now', 'string', 'بيفتح دلوقتي…'],
            ['learning.availability.day_suffix', 'string', 'ي'],
            ['learning.availability.timezone_note', 'string', 'كلّ المواعيد فوق بتوقيتك:'],
            ['learning.availability.schedule_title', 'string', 'مواعيد إتاحة التدريب'],
            ['learning.availability.daily_line', 'string', 'يوميًّا من :from إلى :to بتوقيتك المحلّيّ.'],

            // ---- الديدلاين و Ghost Timer (6)
            ['learning.deadline.warn_percent', 'number', '50'],
            ['learning.deadline.danger_percent', 'number', '20'],
            ['learning.deadline.left_prefix', 'string', 'تبقّى'],
            ['learning.deadline.passed_label', 'string', 'انتهت المهلة'],
            ['learning.deadline.none_label', 'string', 'بلا موعد نهائيّ'],
            ['learning.ghost.title', 'string', 'الموعد النهائيّ'],
            ['learning.ghost.hero_glyph', 'string', '🧑‍🎓'],
            ['learning.ghost.glyph', 'string', '👻'],
            ['learning.ghost.hint', 'string', 'كلّما أنجزت أبكر ابتعد الشبح —'],

            // ---- نقاط الخبرة (7)
            ['learning.xp.suffix', 'string', 'XP'],
            ['learning.xp.earned_label', 'string', 'الخبرة المكتسبة'],
            ['learning.xp.next_label', 'string', 'الدرس القادم يمنحك'],
            ['learning.coins.suffix', 'string', 'كوينز'],

            // ---- أسئلة الدرس (4 · 4.1)
            ['learning.questions.block_title', 'string', 'أسئلة الدرس'],
            ['learning.questions.block_hint', 'string', 'أسئلة الدرس بوّابة الانتقال — بلا تذاكر وبلا أثر على حسابك'],
            ['learning.questions.gate_label', 'string', 'بانتظار الحلّ'],
            ['learning.questions.passed_label', 'string', 'مجتاز'],
            ['learning.questions.open_cta', 'string', 'ابدأ الحلّ'],
            ['learning.questions.review_cta', 'string', 'مراجعة الأسئلة'],
            ['learning.questions.solved_label', 'string', 'صحيحة'],
            ['learning.questions.open_label', 'string', 'مفتوح'],
            ['learning.questions.submit', 'string', 'تحقّق'],
            ['learning.questions.digit_label', 'string', 'الخانة'],
            ['learning.questions.reward_label', 'string', 'مكافأة الإجابة'],
            ['learning.questions.correct_message', 'string', 'إجابة صحيحة'],
            ['learning.questions.wrong_message', 'string', 'الإجابة غير صحيحة — راجع الدرس وأعد المحاولة'],
            ['learning.questions.already_message', 'string', 'أجبت عن هذا السؤال من قبل'],
            ['learning.questions.retry_hint', 'string', 'المحاولات مفتوحة، وخطؤك لا يخصم شيئًا'],
            ['learning.otp.max_length', 'number', '8'],

            // ---- تدفّق اختبار الدرس (4.1): عشوائيّة · معاينة · انتظار الإعادة
            ['learning.quiz.title', 'string', 'اختبار الدرس'],
            ['learning.quiz.shuffle_questions', 'bool', '1'],
            ['learning.quiz.shuffle_options', 'bool', '1'],
            ['learning.quiz.retry_wait_seconds', 'number', '20'],
            ['learning.quiz.answer_hint', 'string', 'أجب عن الأسئلة كلّها، ثمّ راجع إجاباتك قبل التسليم'],
            ['learning.quiz.question_label', 'string', 'سؤال'],
            ['learning.quiz.of_label', 'string', 'من'],
            ['learning.quiz.preview_cta', 'string', 'معاينة الإجابات'],
            ['learning.quiz.preview_hint', 'string', 'دي إجاباتك قبل التسليم — راجعها وعدّل ما تشاء'],
            ['learning.quiz.answered_label', 'string', 'مُجاب'],
            ['learning.quiz.unanswered_label', 'string', 'بلا إجابة'],
            ['learning.quiz.no_answer_placeholder', 'string', 'لم تُجب بعد'],
            ['learning.quiz.submit_cta', 'string', 'تسليم نهائيّ'],
            ['learning.quiz.edit_cta', 'string', 'تعديل الإجابات'],
            ['learning.quiz.correct_label', 'string', 'صحيحة'],
            ['learning.quiz.wrong_label', 'string', 'غير صحيحة'],
            ['learning.quiz.score_label', 'string', 'الإجابات الصحيحة'],
            ['learning.quiz.passed_title', 'string', 'أحسنت — اجتزت اختبار الدرس'],
            ['learning.quiz.failed_title', 'string', 'فيه إجابات محتاجة مراجعة'],
            ['learning.quiz.passed_message', 'string', 'اجتزت اختبار الدرس — الانتقال مفتوح'],
            ['learning.quiz.failed_message', 'string', 'فيه إجابة غير صحيحة — راجع الدرس وأعد المحاولة'],
            ['learning.quiz.already_passed_message', 'string', 'اجتزت اختبار هذا الدرس من قبل'],
            ['learning.quiz.retry_badge', 'string', 'إعادة'],
            ['learning.quiz.retry_cta', 'string', 'أعد الاختبار'],
            ['learning.quiz.wait_message', 'string', 'إعادة الاختبار متاحة بعد'],
            ['learning.quiz.seconds_suffix', 'string', 'ثانية'],
            ['learning.quiz.back_to_lesson', 'string', 'رجوع للدرس'],
            ['learning.quiz.no_questions_message', 'string', 'هذا الدرس بلا أسئلة — أكمله مباشرةً'],

            // ---- تعليقات الفيديو (3.1)
            ['learning.comments.title', 'string', 'تعليقات الفيديو'],
            ['learning.comments.unit', 'string', 'تعليقًا'],
            ['learning.comments.per_page', 'number', '6'],
            ['learning.comments.max_length', 'number', '1000'],
            ['learning.comments.placeholder', 'string', 'اكتب سؤالك أو خلاصتك من الفيديو…'],
            ['learning.comments.hint', 'string', 'تعليقك يفيد زملاءك — اكتب بوضوح واحترام'],
            ['learning.comments.submit', 'string', 'أرسل التعليق'],
            ['learning.comments.reply', 'string', 'ردّ'],
            ['learning.comments.reply_placeholder', 'string', 'اكتب ردّك…'],
            ['learning.comments.reply_submit', 'string', 'أرسل الردّ'],
            ['learning.comments.like', 'string', 'أعجبني'],
            ['learning.comments.load_more', 'string', 'تعليقات أقدم'],
            ['learning.comments.load_error', 'string', 'تعذّر تحميل التعليقات — اضغط هنا للمحاولة مرّة أخرى'],
            ['learning.comments.empty', 'string', 'لسّه مفيش تعليقات — كن أوّل من يشارك سؤاله'],
            ['learning.comments.sent_message', 'string', 'اتنشر تعليقك ✓'],
            ['learning.comments.empty_error', 'string', 'التعليق فاضي — اكتب سطرًا واحدًا على الأقلّ ثمّ أرسل'],
            ['learning.comments.too_long_error', 'string', 'التعليق أطول من المسموح — اختصره ثمّ أعد الإرسال'],
            ['learning.comments.hide', 'string', 'إخفاء'],
            ['learning.comments.unhide', 'string', 'إظهار'],
            ['learning.comments.delete', 'string', 'حذف'],
            ['learning.comments.delete_confirm', 'string', 'هل تحذف هذا التعليق وردوده؟'],
            ['learning.comments.hidden_badge', 'string', 'مخفيّ'],
            ['learning.comments.hidden_message', 'string', 'اتخفى التعليق عن العرض العامّ'],
            ['learning.comments.unhidden_message', 'string', 'رجع التعليق للعرض العامّ'],
            ['learning.comments.deleted_message', 'string', 'اتحذف التعليق'],

            // ---- ملاحظات التدريب (3.2)
            ['learning.notes.title', 'string', 'ملاحظاتي على التدريب'],
            ['learning.notes.hint', 'string', 'مساحة واحدة لكلّ دروس التدريب — تُحفَظ تلقائيًّا'],
            ['learning.notes.placeholder', 'string', 'اكتب خلاصتك، وستجدها في أيّ درسٍ آخر…'],
            ['learning.notes.max_length', 'number', '20000'],
            ['learning.notes.autosave_delay_ms', 'number', '800'],
            ['learning.notes.saving', 'string', 'بنحفظ…'],
            ['learning.notes.saved', 'string', 'اتحفظ ✓'],
            ['learning.notes.error', 'string', 'ما قدرناش نحفظ ملاحظتك — راجع اتصالك وسنعيد المحاولة عند أوّل تعديل'],
            ['learning.notes.too_long_error', 'string', 'الملاحظات أطول من المسموح — اختصرها ثمّ احفظ'],
            ['learning.notes.save_cta', 'string', 'حفظ الملاحظات'],
            ['learning.notes.clear', 'string', 'مسح الملاحظات'],
            ['learning.notes.clear_confirm', 'string', 'هل تمسح كلّ ملاحظاتك على هذا التدريب؟'],
            ['learning.notes.cleared_message', 'string', 'اتمسحت ملاحظاتك على هذا التدريب'],
            ['learning.notes.export', 'string', 'تنزيل الملاحظات'],
            ['learning.notes.export_heading', 'string', 'ملاحظاتي على التدريب'],
            ['learning.notes.export_date_label', 'string', 'تاريخ التنزيل'],
            ['learning.notes.export_file_prefix', 'string', 'notes'],

            // ---- الامتحان النهائيّ والشهادة (نقطة تكامل مع مجال الامتحانات)
            ['learning.exam.block_title', 'string', 'الامتحان النهائيّ للتدريب'],
            ['learning.exam.unlock_percent', 'number', '100'],
            ['learning.exam.unlock_condition', 'string', 'يفتح بعد إكمال'],
            ['learning.exam.start_cta', 'string', 'ادخل الامتحان'],
            ['learning.exam.none_label', 'string', 'بلا امتحان'],
            ['learning.exam.pending_label', 'string', 'لم يُدخَل بعد'],
            ['learning.exam.attempted_label', 'string', 'محاولة سابقة'],
            ['learning.exam.passed_label', 'string', 'ناجح'],
            ['learning.certificate.none_label', 'string', 'بلا شهادة'],
            ['learning.certificate.issued_label', 'string', 'شهادة صادرة'],
            ['learning.certificate.inactive_label', 'string', 'شهادة غير سارية'],

            // ---- «مجّاني أوّل مرّة» والـPaywall النفسيّ (16)
            ['learning.paywall.title', 'string', 'وصلت لنهاية المشاهدة المجّانيّة'],
            ['learning.paywall.badge', 'string', 'أنجزت وامتحنت'],
            ['learning.paywall.headline', 'text', 'أنجزت «{course}» ومعك شهادته — واصِل رحلتك الكاملة واحتفظ بالتدريب معك للأبد.'],
            ['learning.paywall.lock_reason', 'text', 'التدريب ده كان مجّانيًّا أوّل مرّة، وبعد الامتحان والشهادة بقى بالشراء — وشهادتك تفضل معك في مكتبتك زيّ ما هي.'],
            ['learning.paywall.cta', 'string', 'اشترِ التدريب — {price}'],
            ['learning.paywall.certificate_link', 'string', 'شهادتي'],
            ['learning.paywall.paid_sources', 'json', '["purchase","bundle"]'],
            ['learning.certificate.valid_status', 'string', 'valid'],

            // ---- المسارات (3.3 · 24.5)
            ['learning.paths.courses_unit', 'string', 'تدريبات'],
            ['learning.paths.not_owned', 'string', 'لا تملكه بعد'],
            ['learning.paths.exam_cta', 'string', 'امتحان شهادة المسار'],
            ['learning.paths.certificate_block_title', 'string', 'شهادة المسار'],
            ['learning.paths.exam_ready_hint', 'string', 'أكملت المسار — الامتحان متاح الآن'],
            ['learning.paths.exam_locked_hint', 'string', 'يظهر الامتحان بعد إكمال تدريبات المسار كاملة'],
            ['learning.path.exam_unlock_percent', 'number', '100'],
            ['learning.path.exam_price_coins', 'number', '150'],

            // ---- المرفقات والفيديو
            ['learning.attachments.title', 'string', 'مرفقات الدرس'],
            ['learning.attachments.size_unit', 'string', 'كيلوبايت'],
            ['learning.video.embed_base', 'string', 'https://www.youtube-nocookie.com/embed'],
            ['learning.video.embed_params', 'json', '{"rel":"0","modestbranding":"1"}'],
            ['learning.video.missing_message', 'string', 'تعذّر عرض الفيديو — استعن بمحتوى الدرس ومرفقاته، وأبلغنا لنصلحه'],

            // ---- الإبلاغ عن مشكلة ⟵ تذكرة دعم
            ['learning.report.cta', 'string', 'الإبلاغ عن مشكلة في الدرس'],
            ['learning.report.type_label', 'string', 'نوع المشكلة'],
            ['learning.report.lesson_label', 'string', 'الدرس'],
            ['learning.report.lesson_any', 'string', 'التدريب كلّه'],
            ['learning.report.body_label', 'string', 'اشرح المشكلة'],
            ['learning.report.submit', 'string', 'إرسال'],
            ['learning.report.sent_message', 'string', 'وصلنا بلاغك — فتحنا لك تذكرة دعم وسنردّ عليك'],
            ['learning.report.category', 'string', 'lesson'],
            ['learning.report.ticket_type', 'string', 'complaint'],
            ['learning.report.number_length', 'number', '10'],
            ['learning.report.types', 'json', '["مشكلة في الفيديو","خطأ في المحتوى","مرفق لا يعمل","سؤال غير واضح","أخرى"]'],

            // ---- الأيقونات (SVG لاحقًا من مكتبة المنصّة — 2.16-ج)
            ['learning.icon.video', 'string', '🎥'],
            ['learning.icon.document', 'string', '📄'],
            ['learning.icon.course', 'string', '🎓'],
            ['learning.icon.path', 'string', '🧭'],
            ['learning.icon.lock', 'string', '🔒'],
            ['learning.icon.done', 'string', '✓'],
            ['learning.icon.xp', 'string', '⚡'],
            ['learning.icon.attachment', 'string', '📎'],
        ];

        foreach ($rows as [$key, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'learning',
                'label_ar' => $this->label($key),
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    private function label(string $key): string
    {
        return 'التعلّم — '.str_replace(['learning.', '.', '_'], ['', ' / ', ' '], $key);
    }

    /**
     * إعدادات الإتاحة والتوقيت (5) — تُقرأ من **كتالوج الإعدادات نفسه** لا من
     * قائمة ثانية هنا، فلا تختلف القيمة الافتراضيّة بين السيدر وزرّ الـReset.
     */
    private function availabilitySettings(): void
    {
        foreach (SettingsCatalog::group('availability') as $key => [$group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    // ------------------------------------------------------------ المحتوى

    private function path(): LearningPath
    {
        return LearningPath::updateOrCreate(['slug' => 'usus-al-amal-al-tatawui'], [
            'name_ar' => 'أساسيّات العمل التطوّعيّ المؤسّسيّ',
            'name_en' => 'Foundations of Institutional Volunteering',
            'description_ar' => 'مسار يبني عندك أساس العمل داخل فريق: تتواصل بوضوح، وتدير وقتك، وتسلّم في موعدك.',
            'forced_order' => true,
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => Carbon::now()->subMonths(2),
        ]);
    }

    /** التدريب الأوّل: متاح وجارٍ — يعرض الحالة الطبيعيّة للشاشات */
    private function communicationCourse(LearningPath $path): void
    {
        $course = Course::updateOrCreate(['slug' => 'maharat-al-tawasul'], [
            'name_ar' => 'مهارات التواصل الفعّال',
            'name_en' => 'Effective Communication Skills',
            'description_ar' => 'كيف توصّل فكرتك في جملة واحدة، وتستمع فتفهم قبل أن تردّ، وتكتب رسالة عمل لا تحتاج توضيحًا بعدها.',
            'is_free' => false,
            'price_coins' => 250,
            'xp_before_half' => 40,
            'xp_after_half' => 20,
            'deadline_days' => 30,
            'forced_order' => true,
            'free_preview_lessons' => 1,
            'status' => 'published',
            'published_at' => Carbon::now()->subMonth(),
        ]);

        $this->attach($course, $path, 1);

        $first = Section::updateOrCreate(
            ['course_id' => $course->id, 'title_ar' => 'أساس الرسالة'],
            ['title_en' => 'The Message Core', 'sort_order' => 1],
        );

        $lessons = [
            [$first, 'ما التواصل الفعّال؟', 'video', 'dQw4w9WgXcQ', 7, true, null],
            [$first, 'الجملة الواحدة: كيف تلخّص فكرتك؟', 'document', null, 6,  false,
                "قبل أن تتكلّم، اكتب فكرتك في جملة واحدة.\nلو احتجت أكثر من جملة، فالفكرة لم تنضج بعد.\nالتمرين: خذ آخر رسالة أرسلتها في العمل، وأعد كتابتها في جملة واحدة لا تزيد على عشرين كلمة."],
            [$first, 'الاستماع قبل الردّ', 'video', 'aqz-KE-bpKQ', 9, false, null],
        ];

        $second = Section::updateOrCreate(
            ['course_id' => $course->id, 'title_ar' => 'التواصل المكتوب'],
            ['title_en' => 'Written Communication', 'sort_order' => 2],
        );

        $lessons[] = [$second, 'بنية رسالة العمل', 'document', null, 8, false,
            "رسالة العمل الجيّدة ثلاث طبقات:\n١) الطلب في السطر الأوّل.\n٢) السياق الذي يبرّره.\n٣) الموعد المطلوب والخطوة التالية.\nمن يقرأ سطرك الأوّل فقط يجب أن يعرف ماذا تريد."];
        $lessons[] = [$second, 'تلخيص الاجتماع في خمسة أسطر', 'document', null, 5, false,
            "خمسة أسطر تكفي: القرار · المسؤول · الموعد · المخاطر · ما يحتاج قرارًا أعلى.\nما زاد على ذلك يُقرأ نادرًا، وما نقص عنه يُسأل عنه لاحقًا."];

        $created = $this->lessons($lessons);

        // سؤال بالإدخال الرقميّ بنمط OTP (4) + سؤال عامّ يصلح للامتحان النهائيّ
        LessonQuestion::updateOrCreate(
            ['lesson_id' => $created[1]->id, 'prompt' => 'كم كلمة حدُّ الجملة الواحدة كما ورد في الدرس؟'],
            ['type' => 'otp', 'correct_answer' => '20', 'is_general' => true, 'xp_reward' => 10, 'sort_order' => 1],
        );

        LessonQuestion::updateOrCreate(
            ['lesson_id' => $created[3]->id, 'prompt' => 'ما الذي يجب أن يظهر في السطر الأوّل من رسالة العمل؟'],
            [
                'type' => 'choice',
                'options' => ['الطلب', 'التحيّة', 'السياق الكامل', 'التوقيع'],
                'correct_answer' => 'الطلب',
                'is_general' => true,
                'xp_reward' => 10,
                'sort_order' => 1,
            ],
        );

        $this->attachment($created[3], 'قالب رسالة عمل.pdf', 'learning/demo/work-message-template.pdf');

        $this->enrollDemoUser($course, Carbon::now()->subDays(6), Carbon::now()->addDays(24));
    }

    /** التدريب الثاني: منتهية إتاحته — ليظهر بحالته وسبب قفله لا مخفيًّا (24.5) */
    private function timeCourse(LearningPath $path): void
    {
        $course = Course::updateOrCreate(['slug' => 'idarat-al-waqt'], [
            'name_ar' => 'إدارة الوقت وترتيب الأولويّات',
            'name_en' => 'Time Management & Prioritisation',
            'description_ar' => 'تعرف ما يستحقّ وقتك اليوم، وتقول «لا» بلا إحراج، وتسلّم قبل الموعد لا بعده.',
            'is_free' => false,
            'price_coins' => 200,
            'xp_before_half' => 30,
            'xp_after_half' => 15,
            'deadline_days' => 21,
            'forced_order' => false,
            'status' => 'published',
            'published_at' => Carbon::now()->subMonths(3),
        ]);

        $this->attach($course, $path, 2);

        $section = Section::updateOrCreate(
            ['course_id' => $course->id, 'title_ar' => 'ترتيب الأولويّات'],
            ['title_en' => 'Prioritisation', 'sort_order' => 1],
        );

        $created = $this->lessons([
            [$section, 'المهمّ والعاجل: كيف تفرّق؟', 'video', 'M3BM9TB-8yA', 10, true, null],
            [$section, 'ثلاث مهامّ لا أكثر', 'document', null, 6, false,
                "اختر كلّ صباح ثلاث مهامّ تُنجزها مهما حدث.\nما زاد عنها قائمة أمنيات لا خطّة.\nوفي آخر اليوم اسأل: أنجزت الثلاث؟ ولو لا، فما الذي سرق الوقت؟"],
            [$section, 'كيف تقول «لا» باحترام؟', 'document', null, 5, false,
                "قل «لا» للمهمّة لا للشخص: اشرح انشغالك الحاليّ، واعرض موعدًا تستطيعه، أو اقترح من يستطيع.\nالرفض الواضح أرحم من موافقة لا تُنفَّذ."],
        ]);

        LessonQuestion::updateOrCreate(
            ['lesson_id' => $created[1]->id, 'prompt' => 'كم مهمّة أساسيّة يوصي بها الدرس يوميًّا؟'],
            ['type' => 'otp', 'correct_answer' => '3', 'is_general' => true, 'xp_reward' => 10, 'sort_order' => 1],
        );

        $this->enrollDemoUser($course, Carbon::now()->subDays(40), Carbon::now()->subDays(5));
    }

    // ------------------------------------------------------------ أدوات

    private function attach(Course $course, LearningPath $path, int $order): void
    {
        DB::table('course_learning_path')->updateOrInsert(
            ['course_id' => $course->id, 'learning_path_id' => $path->id],
            ['sort_order' => $order, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        );
    }

    /** @return array<int, Lesson> */
    private function lessons(array $rows): array
    {
        $out = [];

        foreach ($rows as $index => [$section, $title, $type, $videoId, $minutes, $preview, $content]) {
            $out[] = Lesson::updateOrCreate(
                ['section_id' => $section->id, 'title_ar' => $title],
                [
                    'type' => $type,
                    'video_provider' => $type === 'video' ? 'youtube' : null,
                    'video_id' => $videoId,
                    'content' => $content,
                    'duration_minutes' => $minutes,
                    'sort_order' => $index + 1,
                    'is_free_preview' => $preview,
                ],
            );
        }

        return $out;
    }

    private function attachment(Lesson $lesson, string $name, string $path): void
    {
        $media = MediaItem::updateOrCreate(['path' => $path], [
            'disk' => 'public',
            'name' => $name,
            'mime' => 'application/pdf',
            'size' => 184320,
        ]);

        LessonAttachment::updateOrCreate(
            ['lesson_id' => $lesson->id, 'media_item_id' => $media->id],
            ['sort_order' => 1],
        );
    }

    /** يُسجَّل أوّل مستخدم موجود ليكون للبذرة أثرٌ مرئيّ فورًا — وبلا مستخدم لا شيء */
    private function enrollDemoUser(Course $course, Carbon $startedAt, Carbon $deadlineAt): void
    {
        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            return;
        }

        Enrollment::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'source' => 'purchase',
                'started_at' => $startedAt,
                'deadline_at' => $deadlineAt,
                'status' => 'active',
            ],
        );
    }
}
