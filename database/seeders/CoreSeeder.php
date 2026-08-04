<?php

namespace Database\Seeders;

use App\Models\BehaviorViolation;
use App\Models\CelebrationEvent;
use App\Models\CertificateAccreditation;
use App\Models\CertificateType;
use App\Models\Currency;
use App\Models\Level;
use App\Models\NameParticle;
use App\Models\Position;
use App\Models\RepRule;
use App\Models\TaskType;
use App\Models\Track;
use Illuminate\Database\Seeder;

/**
 * الثوابت المعتمَدة في الدستور: العملات · البوزشنز · المسارات · قيم Rep ·
 * المخالفات · الاحتفالات · أدوات الاسم · المستويات · أنواع الشهادات.
 */
class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->currencies();
        $this->tracks();
        $this->positions();
        $this->repRules();
        $this->behaviorViolations();
        $this->celebrations();
        $this->nameParticles();
        $this->levels();
        $this->certificateTypes();
        $this->taskTypes();
    }

    /**
     * أنواع المهمّة الثمانية (23-0.3): **وسم وقالب فقط** — تشيك ليست جاهزة
     * وهيكل بريف وشكل مخرجات وقيم مقترحة. «اختيار النوع لا يغيّر شيئًا في
     * السلوك: نفس الحالات، نفس جدول Rep، نفس محرّك التصعيد».
     *
     * ولماذا في السيدر الأساسيّ لا في سيدر العرض؟ لأنّها **كتالوج إنتاج** كالعملات
     * والبوزشنات — والشاشة تعدّله وتزيد عليه (2.13)، ولا يبدأ التنصيب فارغًا.
     */
    private function taskTypes(): void
    {
        $rows = [
            ['execution', 'تنفيذ', '⚙️', 'أيّ شغل عامّ لا ينطبق عليه نوع أدقّ.', 'حسب البريف', [], 'text'],
            ['content', 'محتوى', '✍️', 'سكربتات · بوستات · مقالات.', 'ملفّ قابل للتعديل + نسخة نهائيّة', ['مسودّة', 'مراجعة لغويّة', 'مطابقة الهويّة'], 'file'],
            ['design', 'تصميم/مونتاج', '🎨', 'جرافيك · فيديو · موشن.', 'رابط ملفّ مفتوح + تصدير نهائيّ', ['مقاسات', 'هويّة بصريّة', 'نسخة مضغوطة'], 'link'],
            ['recruitment', 'توظيف', '🧑‍💼', 'فرز طلبات · اتصال · مقابلات.', 'تحديث حالة المتقدّمين في مسار الرحلة', ['استلام القائمة', 'فرز', 'تواصل', 'تسجيل النتيجة'], 'confirm'],
            ['followup', 'متابعة', '🔁', 'متابعة متدرّبين ومحافظات ومتعثّرين.', 'نصّ ملخّص + أرقام', ['سحب القائمة', 'تواصل', 'تسجيل الردود'], 'text'],
            ['support', 'دعم', '🎧', 'الردّ على تذاكر واستفسارات.', 'تأكيد + عدد المغلَق', ['استلام', 'ردّ', 'إغلاق'], 'confirm'],
            ['research', 'بحث/تحليل', '🔎', 'جمع بيانات · تقارير · مقارنات.', 'مستند بأرقام ومراجع', ['مصادر', 'تجميع', 'استنتاجات'], 'file'],
            ['training', 'تدريب/سيشن', '🎓', 'تحضير وتنفيذ محتوى تدريبيّ.', 'رابط المادّة + التسجيل للأكاديميّة', ['مادّة', 'بروفة', 'تنفيذ', 'رفع التسجيل'], 'link'],
        ];

        foreach ($rows as [$key, $label, $icon, $brief, $spec, $checklist, $delivery]) {
            TaskType::updateOrCreate(['key' => $key], [
                'name_ar' => $label,
                'icon' => $icon,
                'default_brief' => $brief,
                'default_deliverable_spec' => $spec,
                'checklist' => $checklist,
                'default_delivery_kind' => $delivery,
                'is_active' => true,
            ]);
        }
    }

    /** العملات (القسم 19 · 13.4-ن) */
    private function currencies(): void
    {
        $rows = [
            // ⭐ الكوينز والتذاكر **قابلتان للصرف فلهما قاعٌ صفريّ** (15.2-4:
            // «والتذاكر لا تنزل تحت الصفر») — والقاع بيانٌ يقرؤه دفتر الأستاذ
            // فيَرُدّ الخصم الذي يتجاوزه بدل أن يقصّه أو ينزل تحت الصفر.
            ['coins', 'كوينز', 'Coins', 'training', true, true, 0, null, false],
            // XP تراكميّة غير قابلة للصرف ولا تُخصَم آليًّا (13.4-ن) — حارسها آخر
            ['xp', 'نقاط الخبرة', 'XP', 'training', false, true, null, null, false],
            ['tickets', 'تذاكر', 'Tickets', 'training', true, true, 0, null, false],
            // ⭐ الساعات: عملة محتملة مستقبلًا لكنّها **تُعرَض في المحفظة** من اليوم (19.1)
            ['hours', 'الساعات', 'Hours', 'training', false, true, 0, null, false],
            // ⭐ دولار الأرباح: وعاء الأرباح القابلة للسحب (عمولة الريفيرال والتحويلات) — 19.2/19.3
            // حدّه الأدنى صفر، فلا يصير رصيد الأرباح سالبًا مهما تسابقت العمليّات.
            ['usd', 'دولار الأرباح', 'Earnings USD', 'training', true, false, 0, null, false],
            // VXP تراكميّ لا يتصفّر ولا يُخصَم آليًّا (13.4-ن)
            ['vxp', 'نقاط الإنتاج', 'VXP', 'volunteer', false, true, null, null, false],
            // Rep مسقوف −10…+10 ويتصفّر يوم 1 الساعة 5:00ص بتوقيت القاهرة
            ['rep', 'درجة الالتزام', 'Rep', 'volunteer', false, false, -10, 10, true],
        ];

        foreach ($rows as [$code, $ar, $en, $layer, $spendable, $cumulative, $min, $max, $reset]) {
            Currency::updateOrCreate(['code' => $code], [
                'name_ar' => $ar,
                'name_en' => $en,
                'layer' => $layer,
                'decimals' => in_array($code, ['rep', 'vxp', 'usd'], true) ? 2 : 0,
                'is_spendable' => $spendable,
                'is_cumulative' => $cumulative,
                'min_value' => $min,
                'max_value' => $max,
                'resets_monthly' => $reset,
            ]);
        }
    }

    /** المسارات الثلاثة (23-0.2) */
    private function tracks(): void
    {
        foreach ([
            ['department', 'قسم', 'Department', false],
            ['governorate', 'محافظة', 'Governorate', false],
            ['case_file', 'ملفّ', 'Case File', true],
        ] as [$key, $ar, $en, $temp]) {
            Track::updateOrCreate(['key' => $key], [
                'name_ar' => $ar, 'name_en' => $en, 'is_temporary' => $temp,
            ]);
        }
    }

    /** البوزشنز الستّة + «أخوكم» الشرفيّ (13.4 · 13.4-ف · 13.4-ص) */
    private function positions(): void
    {
        $rows = [
            // key, ar, rank, span_min, span_default, span_max, task_cap, honorary
            ['coordinator', 'كوردنيتور', 1, null, null, null, 3, false],
            ['team_leader', 'تيم ليدر', 2, 2, 5, 8, 7, false],
            ['supervisor', 'سوبرفايزر', 3, 2, 3, 5, 15, false],
            ['director', 'دايركتور', 4, 1, 3, 6, null, false],
            ['track_supervisor', 'مشرف عام المسار', 5, null, null, null, null, false],
            ['volunteer_gm', 'مشرف عام التطوّع', 6, null, null, null, null, false],
            // عنصر شرفيّ بلا صلاحيّات ولا داونلاين ولا تصعيد — فوق الجميع
            ['brother', 'أخوكم', 7, null, null, null, null, true],
        ];

        foreach ($rows as [$key, $ar, $rank, $min, $def, $max, $cap, $honorary]) {
            Position::updateOrCreate(['key' => $key], [
                'name_ar' => $ar,
                'rank' => $rank,
                'span_min' => $min,
                'span_default' => $def,
                'span_max' => $max,
                'task_load_cap' => $cap,
                'is_honorary' => $honorary,
            ]);
        }
    }

    /** جدول Rep الموحَّد (13.4-ن) — كلّ القيم إعدادات قابلة للتعديل */
    private function repRules(): void
    {
        $rows = [
            // المهامّ
            ['tasks', 'task.early', 'تسليم قبل الموعد', 0.25],
            ['tasks', 'task.late_under_24h', 'تأخير أقلّ من 24 ساعة', -0.25],
            ['tasks', 'task.no_delivery', 'عدم تسليم', -0.75],
            ['tasks', 'task.apology_accepted', 'اعتذار مقبول', -0.5],
            ['tasks', 'task.breakdown_delay_per_day', 'تأخّر التفكيك (يوميًّا)', -0.2],
            ['tasks', 'task.breakdown_delay_cap', 'سقف خصم تأخّر التفكيك', -1.0],
            ['tasks', 'task.contribution_no_delivery', 'عدم تسليم بند مساهمة', -0.2],
            ['tasks', 'task.checkpoint_missed', 'عدم الردّ على نقطة تفتيش', -0.2],
            ['tasks', 'task.slowdown', 'تباطؤ المراجعة (فوات النافذة)', -0.1],

            // الاجتماعات
            ['meetings', 'meeting.within_3h', 'تسجيل الحضور خلال 3 ساعات من الانتهاء', 1.0],
            ['meetings', 'meeting.within_12h', 'تسجيل الحضور من 3 وحتى 12 ساعة', 0.5],
            ['meetings', 'meeting.excused_absence', 'غياب باعتذار', 0.0],
            ['meetings', 'meeting.unexcused_absence', 'غياب بلا اعتذار', -0.5],
            ['meetings', 'meeting.managed', 'إدارة اجتماع وإنهاؤه بمحضر موثَّق', 1.0],

            // الأكاديمية
            ['academy', 'academy.recording_otp', 'تسجيل حضور تسجيل بـOTP', 0.2],
            ['academy', 'academy.path_complete', 'إكمال مسار أكاديميّ', 1.0],

            // مؤشّر القيادة (بعتبة 3 مقيّمين)
            ['leadership', 'leadership.ge_9', 'متوسّط التقييم 9 فأعلى', 0.3],
            ['leadership', 'leadership.8_to_8_9', 'متوسّط من 8 إلى 8.9', 0.15],
            ['leadership', 'leadership.6_to_7_9', 'متوسّط من 6 إلى 7.9', 0.0],
            ['leadership', 'leadership.4_to_5_9', 'متوسّط من 4 إلى 5.9', -0.15],
            ['leadership', 'leadership.lt_4', 'متوسّط أقلّ من 4', -0.3],

            // السلوك
            ['behavior', 'behavior.warning', 'تنبيه موثّق', -0.5],
            ['behavior', 'behavior.severe', 'مخالفة جسيمة (بموافقة مستوى أعلى)', -1.0],

            // الخمول — مدخل لسلّم العتبات لا نوع خروج (13.4-س)
            ['behavior', 'inactivity.weekly', 'خمول أسبوعيّ بعد 21 يومًا بلا نشاط', -0.5],

            // الحدود
            ['limits', 'limit.daily_loss', 'حدّ الخسارة اليوميّ (ما زاد يُسجَّل كاملًا بوسم)', -2.0],
            ['limits', 'limit.red_indicator', 'عتبة المؤشّر الأحمر', -8.0],
            ['limits', 'limit.warning_threshold', 'عتبة الإنذار', -5.0],
            ['limits', 'limit.optional_cut', 'عتبة بتر الاختياريّ', -9.5],
            ['limits', 'limit.suspension', 'عتبة التعليق ولجنة التحقيق', -10.0],
            ['limits', 'limit.cumulative_90d', 'عتبة المكتسَب التراكميّ خلال 90 يومًا', -15.0],
            ['limits', 'limit.club_threshold', 'عتبة نادي التميّز', 9.5],
        ];

        foreach ($rows as [$group, $key, $label, $value]) {
            RepRule::updateOrCreate(['key' => $key], [
                'group' => $group, 'label_ar' => $label, 'value' => $value,
            ]);
        }
    }

    /** قائمة المخالفات المكوَّدة (13.4-ن-هـ) */
    private function behaviorViolations(): void
    {
        $rows = [
            ['BV01', 'عدم الالتزام بمواعيد متكرّر', -0.5, false],
            ['BV02', 'عدم الردّ على التواصل الإداريّ', -0.5, false],
            ['BV03', 'إخلال بجودة المخرجات بعد تنبيه', -0.5, false],
            ['BV04', 'تجاوز في التعامل مع زميل', -1.0, true],
            ['BV05', 'إفشاء بيانات أو مخرجات مقيَّدة', -1.0, true],
            ['BV06', 'انتحال عمل الغير', -1.0, true],
            ['BV07', 'تمثيل المنصّة بلا تفويض', -1.0, true],
        ];

        foreach ($rows as [$code, $label, $value, $needsApproval]) {
            BehaviorViolation::updateOrCreate(['code' => $code], [
                'label_ar' => $label,
                'default_value' => $value,
                'requires_higher_approval' => $needsApproval,
            ]);
        }
    }

    /** نظام الاحتفالات — ثلاثة مستويات لا رابع (2.14) */
    private function celebrations(): void
    {
        $tier1 = ['lesson.completed' => 'إكمال درس', 'kudos.received' => 'استلام Kudos',
            'consent.granted' => 'الموافقة على إظهار التواصل', 'cv.saved' => 'حفظ السيرة الذاتيّة',
            'meeting.attendance_registered' => 'تسجيل حضور اجتماع', 'topup.completed' => 'إتمام شحن'];
        $tier2 = ['course.completed' => 'إتمام تدريب', 'level.up' => 'بلوغ مستوى جديد',
            'streak.7days' => 'ستريك 7 أيّام', 'task.approved' => 'اعتماد مهمّة', 'order.first' => 'أوّل شراء'];
        $tier3 = ['certificate.issued' => 'إصدار شهادة', 'account.approved' => 'قبول الحساب',
            'qualifying.completed' => 'إتمام المسار التأهيليّ', 'placement.accepted' => 'قبول التسكين',
            'position.promoted' => 'بلوغ بوزشن جديد', 'club.joined' => 'دخول نادي التميّز',
            'supervisor.of_month' => 'مشرف الشهر', 'volunteer_card.issued' => 'إصدار بطاقة المتطوّع'];

        foreach ([1 => $tier1, 2 => $tier2, 3 => $tier3] as $tier => $events) {
            foreach ($events as $key => $label) {
                CelebrationEvent::updateOrCreate(['key' => $key], ['label_ar' => $label, 'tier' => $tier]);
            }
        }
    }

    /** أدوات الاسم تُعامَل جزءًا من الكلمة التالية (12.14-ج) */
    private function nameParticles(): void
    {
        $ar = ['عبد', 'عبدال', 'أبو', 'ابو', 'أبا', 'بن', 'ابن', 'آل', 'ال'];
        $en = ['abd', 'abdel', 'abdul', 'abo', 'abu', 'bin', 'ibn', 'al', 'el'];

        foreach ($ar as $p) {
            NameParticle::updateOrCreate(['particle' => $p], ['locale' => 'ar']);
        }
        foreach ($en as $p) {
            NameParticle::updateOrCreate(['particle' => $p], ['locale' => 'en']);
        }
    }

    /**
     * أسماء المستويات وعتباتها — **والعتبات بنصّ 10.1 حرفيًّا** لا بأرقام مخترَعة.
     *
     * «الزيادة للوصول للمستوى N = `base + (N − 2) × step`» و`base = 500` و
     * `step = 250` لمسار الحساب، فالتراكميّ: 0 · 500 · 1,250 · 2,250 · 3,500 ·
     * 5,000 · 6,750 · 8,750 — وهو ما يطابق جدول «التراكمي L1 → L10» في 10.1.
     *
     * وكانت هنا عتبات أخرى (1500 · 3500 · 7000 · 12000 …) تُقرَأ كمصدرٍ ثانٍ
     * للمستوى، فيختلف رقم الـKPI عن رقم الرادار لنفس المستخدم (ن-2). والحساب
     * صار كلّه في `LevelResolver` بصيغة 10.1، **وهذا الجدول معجم أسماء** —
     * وعتباتُه هنا مطابقةٌ للصيغة كي لا تعرض شاشة الأدمن رقمًا يناقض المنصّة.
     */
    private function levels(): void
    {
        $levels = [
            [1, 'مبتدئ', 0], [2, 'متعلّم', 500], [3, 'متمرّس', 1250], [4, 'متقدّم', 2250],
            [5, 'محترف', 3500], [6, 'خبير', 5000], [7, 'رائد', 6750], [8, 'أسطورة', 8750],
        ];

        foreach ($levels as [$n, $name, $xp]) {
            Level::updateOrCreate(['level' => $n], ['name_ar' => $name, 'min_xp' => $xp]);
        }
    }

    /** أنواع الشهادات — والاعتماد الأساسيّ للمنصّة لا يُحذَف (12.5-أ) */
    private function certificateTypes(): void
    {
        $platform = CertificateAccreditation::updateOrCreate(
            ['name_en' => 'Platform'],
            ['name_ar' => 'اعتماد المنصّة', 'is_platform' => true],
        );

        $types = [
            ['course', 'شهادة تدريب', 'Course Certificate', 'CRS'],
            ['path', 'شهادة مسار', 'Path Certificate', 'PTH'],
            ['event', 'شهادة حضور فعاليّة', 'Event Certificate', 'EVT'],
            ['qualifying', 'شهادة المسار التأهيليّ', 'Qualifying Certificate', 'QLF'],
            ['volunteer_position', 'شهادة بوزشن تطوّعيّ', 'Volunteer Position', 'VPS'],
            ['volunteer_experience', 'شهادة خبرة تطوّع', 'Volunteer Experience', 'VEX'],
            ['volunteer_case_file', 'شهادة مشاركة في ملفّ', 'Case File Participation', 'VCF'],
            ['volunteer_appreciation', 'شهادة تقدير استثنائيّة', 'Appreciation', 'VAP'],
        ];

        foreach ($types as [$key, $ar, $en, $prefix]) {
            CertificateType::updateOrCreate(['key' => $key], [
                'name_ar' => $ar,
                'name_en' => $en,
                'accreditation_id' => $platform->id,
                'numbering_prefix' => $prefix,
                'auto_issue' => true,
            ]);
        }
    }
}
