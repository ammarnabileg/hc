<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseNote;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonQuestion;
use App\Models\MediaItem;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoComment;
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
        $this->socialProofSettings();
        $this->availabilitySettings();
        $path = $this->path();
        $this->communicationCourse($path);
        $this->timeCourse($path);

        Cache::forget('settings');

        $this->socialDemo();

        $this->command?->info('بذرة التعلّم: '.Course::count().' تدريبات · '.Lesson::count().' دروس');
    }

    // ------------------------------------------------------------ الإعدادات

    /** كلّ رقم ونصّ في هذا المجال يُقرأ من هنا — لا من الكود (2.13) */
    public function settings(): void
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

            // ---- لافتة التهنئة عند نصّ التدريب (3.4-19)
            ['learning.course.half_banner_percent', 'number', '50'],
            ['learning.course.half_banner_title', 'string', 'نصّ الطريق خلص يا :name — أحسنت!'],
            ['learning.course.half_banner_hint', 'string', 'باقي :count درس وتخلّص التدريب — كمّل وأنت في أقوى لحظاتك.'],

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
            /*
             | ⭐ لا `hero_glyph` ولا `glyph` هنا (3 · 6 · 2.16-ج): كانا إيموجي
             | (🧑‍🎓 و👻) يرسمهما خطّ نظام التشغيل فلا يتبعان `currentColor` ولا
             | سُمك الخطّ. البديل **إليستريشن مرسومة** في `ghost-figure.blade.php`
             | تتلوّن مع تدرّج الخطر — وشكلٌ لا يُضبَط من لوحة الإعدادات أصلًا،
             | فالمفتاح الذي لا يقرؤه أحد وعدٌ كاذب للمالك (2.13).
             */
            ['learning.ghost.hint', 'string', 'كلّما أنجزت أبكر ابتعد الشبح —'],

            // ---- نقاط الخبرة (7)
            ['learning.xp.suffix', 'string', 'XP'],
            // لاحقة التذاكر في رسالة إتمام الدرس (7.1) — نصٌّ لا يُحرَق في الكود
            ['learning.tickets.suffix', 'string', 'تذكرة'],
            ['learning.lesson.xp_reason', 'string', 'إكمال درس'],
            ['learning.lesson.tickets_reason', 'string', 'تذاكر إتمام درس'],
            ['learning.questions.xp_reason', 'string', 'إجابة صحيحة على سؤال درس'],
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
            // حاجز الإتاحة على الملاحظات (5 · 3.2) — نصّ الردّ حين يكون التدريب مقفولًا
            ['learning.notes.course_locked', 'string', 'الملاحظات جزء من التدريب، والتدريب مقفول دلوقتي.'],

            // ---- الامتحان النهائيّ والشهادة (نقطة تكامل مع مجال الامتحانات)
            ['learning.exam.block_title', 'string', 'الامتحان النهائيّ للتدريب'],
            ['learning.exam.unlock_percent', 'number', '100'],
            ['learning.exam.unlock_condition', 'string', 'يفتح بعد إكمال'],
            ['learning.exam.start_cta', 'string', 'ادخل الامتحان'],
            ['learning.exam.none_label', 'string', 'بلا امتحان'],
            ['learning.exam.pending_label', 'string', 'لم يُدخَل بعد'],
            ['learning.exam.attempted_label', 'string', 'محاولة سابقة'],
            ['learning.exam.passed_label', 'string', 'ناجح'],
            // بلوك الامتحان حين يكون التدريب خارج نافذته (24.5 · 5): ظاهرٌ بقفلٍ وسببٍ مكتوب
            ['learning.exam.locked_badge', 'string', 'مقفول'],
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
            // ⚠️ لا مفتاح لسعر امتحان المسار هنا: السعر **لكلّ مسار** ومصدره الوحيد
            // صفّ الامتحان (12.4-أ) — وإعدادٌ عامّ ثالث كان يخلق سعرًا لا يعرفه الأدمن.

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

            /*
             | ---- الأيقونات: **أسماء في القاموس المشترك** لا رموز إيموجي (3 · 6 · 2.16-ج).
             |
             | كانت القيم إيموجي (🎥 📄 🔒 …) والإيموجي يرسمه خطّ نظام التشغيل: لا
             | يتبع `currentColor` ولا سُمك الخطّ، ويختلف شكله بين المنصّات — فينكسر
             | «سُمك خطّ موحّد وشبكة مقاس واحدة». والقيمة الآن اسمٌ يستهلكه `<x-icon>`
             | من القاموس المشترك، فيبقى الإعداد قابلًا للتغيير بلا لمس الكود (2.13).
             */
            ['learning.icon.video', 'string', 'video'],
            ['learning.icon.document', 'string', 'document'],
            ['learning.icon.course', 'string', 'course'],
            ['learning.icon.path', 'string', 'path'],
            ['learning.icon.lock', 'string', 'lock'],
            ['learning.icon.done', 'string', 'check'],
            ['learning.icon.xp', 'string', 'xp'],
            ['learning.icon.attachment', 'string', 'attachment'],

            // ---- صفحة المسار (3.3): المدّة والموعد والشهادة والمكافأة المبكرة
            ['learning.paths.total_duration_label', 'string', 'إجماليّ المدّة'],
            ['learning.paths.hours_suffix', 'string', 'ساعة'],
            ['learning.paths.due_label', 'string', 'موعد الاستكمال'],
            ['learning.paths.due_format', 'string', 'j F Y'],
            ['learning.paths.certificate_cta', 'string', 'عرض الشهادة'],
            ['learning.paths.early_reward_label', 'string', 'لو أنهيت درسًا دلوقتي'],
            // نصف المهلة **للتذاكر وحدها** (7) — القيمة من `XpCalculator::halfPoint()`
            ['learning.paths.half_point_label', 'string', 'التذاكر بتنزل لواحدة بعد'],
            ['learning.paths.half_point_format', 'string', 'j M Y'],
            ['learning.paths.rank_label', 'string', 'ترتيبك'],
            ['learning.paths.rank_of', 'string', 'من'],
            ['learning.paths.friends_limit', 'number', '12'],

            // ---- اقتراحات العرض المعتمدة (3.4)
            ['learning.cta.resume_where_left', 'string', 'أكمل من حيث توقفت'],
            ['learning.resume.scan_limit', 'number', '10'],
            ['learning.bookmark.add', 'string', 'احفظ الدرس'],
            ['learning.bookmark.remove', 'string', 'إزالة الحفظ'],
            ['learning.bookmark.saved_label', 'string', 'محفوظ'],
            ['learning.bookmark.saved_message', 'string', 'اتحفظ ✓ — هتلاقيه في قائمة الدروس'],
            ['learning.bookmark.removed_message', 'string', 'شِلنا الحفظ عن الدرس'],
            ['learning.lesson.locked_label', 'string', 'مقفول'],
            ['learning.nudge.resume_message', 'string', 'لسّه فاضل شويّة في الدرس ده — تحبّ تكمّله؟'],
            ['learning.nudge.tab_prefix', 'string', '⏸ '],
            ['learning.celebration.share_cta', 'string', 'شارك إنجازك'],
            ['learning.share.title', 'string', 'شارك إنجازك'],
            ['learning.share.text', 'string', 'خلّصت تدريبًا جديدًا على المنصّة 🎓'],
            // نافذة «بيتعلّموا الآن»: تسجيلات تحرّكت داخلها فعلًا — لا تخمين (2.9-7)
            ['learning.social.active_window_minutes', 'number', '30'],
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

    /**
     * ⭐ حدود الدليل الاجتماعيّ لسياقَي التدريب (3.4-45 · 3.4-49) — بقواعد 2.9-7.
     *
     * تُزرَع هنا لا في `SettingSeeder` كي يبقى كلّ مجالٍ صاحبَ مفاتيحه، وتُقرأ
     * من نفس `SocialProof` الذي يخدم الدرس والنادي والحروب — قاموسٌ واحد لا اثنان.
     * وبلا هذه المفاتيح يكون الحدّ صفرًا فلا يظهر العدّاد أصلًا — وهو الأمان
     * الصحيح: **لا رقم بلا حدٍّ يحكمه**.
     */
    public function socialProofSettings(): void
    {
        $rows = [
            ['engagement.social_proof.course_learners.min', 'حدّ عدّاد «بيتعلّموا الآن»', 'number', '20'],
            ['engagement.social_proof.course_learners.count_text', 'نصّ عدّاد «بيتعلّموا الآن»', 'string', ':count بيتعلّموا دلوقتي'],
            ['engagement.social_proof.course_learners.lead_text', 'تأطير الريادة تحت حدّ «بيتعلّموا الآن»', 'string', 'كن أوّل من يبدأ التدريب ده النهارده!'],
            ['engagement.social_proof.course_completed.min', 'حدّ عدّاد «أكملوا هذا التدريب»', 'number', '20'],
            ['engagement.social_proof.course_completed.count_text', 'نصّ «انضم لـ N أكملوا»', 'string', 'انضم لـ :count أكملوا التدريب ده'],
            ['engagement.social_proof.course_completed.lead_text', 'تأطير الريادة تحت حدّ الإكمال', 'string', 'كن أوّل من يُنهي التدريب ده'],
        ];

        foreach ($rows as [$key, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'engagement',
                'label_ar' => $label,
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
    public function availabilitySettings(): void
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
            'xp_max' => 40,
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
            'xp_max' => 30,
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

    /**
     * تعليقات فيديو وملاحظة تدريب (3.1 · 3.2) — ليظهر القسمان مأهولين لا فارغين.
     * وبلا مستخدم لا شيء: البذرة لا تخترع حسابات.
     */
    private function socialDemo(): void
    {
        $user = User::query()->orderBy('id')->first();
        $lesson = Lesson::query()->where('type', 'video')->orderBy('id')->first();
        $course = Course::query()->where('slug', 'maharat-al-tawasul')->first();

        if (! $user || ! $lesson || ! $course) {
            return;
        }

        $root = VideoComment::updateOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id, 'parent_id' => null],
            ['body' => 'أوضح جزء عندي كان مثال «الجملة الواحدة» — جرّبته في رسالة عمل وفرق فعلًا.', 'likes_count' => 0],
        );

        VideoComment::updateOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id, 'parent_id' => $root->id],
            ['body' => 'وأضيف: لو الجملة احتاجت شرحًا بعدها، فهي لم تنضج بعد.'],
        );

        CourseNote::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['body' => "خلاصتي حتى الآن:\n- ابدأ بالجملة الواحدة.\n- استمع لتفهم لا لتردّ.\n- راجع الرسالة قبل الإرسال بصوتٍ عالٍ."],
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
