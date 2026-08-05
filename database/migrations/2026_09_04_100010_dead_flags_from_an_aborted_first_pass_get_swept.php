<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * أيتامٌ حقيقيّون من `settings:coverage --dead` (2.13) — لا بلاغٌ كاذب.
 *
 * كلّ مفتاحٍ هنا **زُرِع ولا يقرؤه أحد**، وقُرِّر شطبه لا وصله بقارئ لأنّ
 * سببًا واحدًا من ثلاثة ينطبق عليه، بلا استثناء:
 *
 * 1) **رقمٌ ناقص في تسلسل مُرقَّم** — سلسلة `text_N`/`option_N`/`legend_N`
 *    الحاليّة في سيدرها **لا تحوي هذا الرقم أصلًا** (مثلًا `overview_report`
 *    تقفز من `text_12` إلى `text_14`، و`escalations_arbitrations_file` من
 *    `option_2` إلى `option_4`) — أثر إعادة ترقيمٍ في دفعة استخراج نصٍّ محروق
 *    سابقة، والصفّ بقي في القاعدة بلا مصدرٍ في أيّ سيدر حاليّ يعيد كتابته.
 * 2) **مفتاحٌ لا وجود له في أيّ سيدر أو ملفّ بيانات بالمرّة** (عائلة
 *    `cv.*.js_N` و`exams.take.js_N`) — صفوفٌ من تجربةٍ أولى مُجهَضة، لا يذكرها
 *    كودٌ ولا سيدر اليوم.
 * 3) **مفتاحٌ استُبدِل باسمٍ آخر يُقرَأ فعلًا** — `bundles.anchoring` ⟵
 *    `store.bundle.anchoring_enabled` (تقرؤه `BundleLanding::sectionVisible`
 *    فعلًا)، و`cv.template.{buy_title,price_title,balance_before,balance_after,
 *    confirm_label}` ⟵ `cv.template.selected_message`/`selected_paid_message`
 *    (يقرؤهما `CvController::selectTemplate` فعلًا)، و`features.ui.col.key`
 *    (الجدول لا عمود «مفتاح» مستقلّ فيه أصلًا — يظهر تحت اسم الميزة)، و
 *    `volunteer.qualifying.path_slug` ⟵ `volunteer.qualifying.path_id`
 *    (يقرؤه `JourneyService::path()` فعلًا؛ الـslug كان يُستشار مرّةً وقت
 *    الزرع الأوّل فقط، فتعديله من الأدمن بعدها بلا أثر).
 *
 * وحُذفت مواضعُها من السيدرات المعنيّة في نفس الدفعة (`LibraryDemoSeeder` ·
 * `AdminSystemDemoSeeder` · `VolunteerPeopleDemoSeeder`) — وإلّا عاد بعضُها في
 * أوّل `migrate:fresh --seed`. والعائلتان (1) و(2) لم تكونا في أيّ سيدرٍ أصلًا.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const DEAD_KEYS = [
        // (1) رقمٌ ناقص من تسلسل مُرقَّم — تجربة استخراج نصٍّ محروق سابقة
        'volunteer.contributions.field_22',
        'volunteer.contributions_invite_form.legend_2',
        'volunteer.escalations_arbitrations_file.option_3',
        'volunteer.overview_report.text_13',
        'volunteer.performance_rep.text_8',
        'volunteer.performance_rep.text_9',
        'volunteer.profile_report.text_5',
        'volunteer.tasks_action_modals.option_2',
        'volunteer.tasks_action_modals.text_9',
        'volunteer.tasks_show.text_12',

        // (2) لا وجود له في أيّ سيدر أو ملفّ بيانات — تجربة أولى مُجهَضة
        'cv.attestations_page.js_1',
        'cv.attestations_page.js_2',
        'cv.index.js_1',
        'cv.index.js_2',
        'cv.index.js_3',
        'cv.index.js_4',
        'cv.step_certificates.js_1',
        'cv.templates_partial.js_1',
        'exams.take.js_1',
        'exams.take.js_2',
        'exams.take.js_3',

        // (3) استُبدِل باسمٍ آخر يُقرَأ فعلًا
        'bundles.anchoring',
        'cv.template.buy_title',
        'cv.template.price_title',
        'cv.template.balance_before',
        'cv.template.balance_after',
        'cv.template.confirm_label',
        'features.ui.col.key',
        'volunteer.qualifying.path_slug',
    ];

    public function up(): void
    {
        $ids = DB::table('settings')->whereIn('key', self::DEAD_KEYS)->pluck('id');

        DB::table('setting_overrides')->whereIn('setting_id', $ids)->delete();
        DB::table('settings')->whereIn('key', self::DEAD_KEYS)->delete();
    }

    /**
     * لا عكس: أيتامٌ حقيقيّون بلا قارئ — إعادتهم إعادةُ وعدٍ كاذب لا تراجعٌ عن
     * خطأ. الاسترجاع الصحيح من نسخة احتياطيّة إن لزم.
     */
    public function down(): void
    {
        // بلا عكس — انظر التعليق أعلاه.
    }
};
