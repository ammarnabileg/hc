<?php

namespace Database\Seeders;

use App\Models\RepRule;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * مجال الاحتفاظ (13.4-س · 13.4-ن-أ).
 *
 * يكمّل **جدول Rep الموحَّد** بالقيمتين الناقصتين المنصوصتين في الدستور،
 * ويضيف إعدادات سلّم الخمول ونافذة المكتسَب التراكميّ — كلّها قيمٌ من
 * الجدول والإعدادات لا أرقام محروقة (2.13).
 *
 * ولا يُسجَّل في `DatabaseSeeder` (BUILD.md §7).
 */
class RetentionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->repRules();
        $this->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');
    }

    /**
     * القيمتان الناقصتان من جدول Rep (13.4-ن-أ):
     *  · **قرار الحالة 8 اليدويّ** — مدًى من +0.25 إلى −0.5، وحدّاه إعدادان
     *    يقرأهما محرّك التصعيد؛ والقيمة هنا هي حدّه الأعلى.
     *  · **فرصة لجنة التحقيق** — +1 لمعدّل الالتزام مع إعادة التفعيل.
     */
    private function repRules(): void
    {
        $rows = [
            [
                'tasks', 'task.state8_manual', 'قرار الحالة 8 اليدويّ (الإرجاع المتكرّر)', 0.25,
                'مدًى يدويّ بمبرّر: من +0.25 إلى −0.5 — والحدّان في workflow.repeated_return.rep_max/rep_min.',
            ],
            [
                'tasks', 'task.committee_chance', 'فرصة لجنة التحقيق', 1.0,
                'قرار اللجنة بمنح فرصة: +1 لمعدّل الالتزام مع إعادة التفعيل.',
            ],
        ];

        foreach ($rows as [$group, $key, $label, $value, $note]) {
            RepRule::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'value' => $value,
                'note' => $note,
            ]);
        }
    }

    /** إعدادات المجال — دورة الخصم ونافذة المكتسَب التراكميّ */
    public function settings(): void
    {
        $rows = [
            ['rep.inactivity.deduction_every_days', 'rep', 'دورة خصم الخمول (أيّام)', 'number', '7'],
            ['volunteer.offboarding.cumulative_window_days', 'offboarding', 'نافذة المكتسَب التراكميّ (يوم)', 'number', '90'],
            // ⭐ بتر الاختياريّ (23-0.2-2): «المحافظات والملفات **عضويّات
            // اختياريّة** تُبتَر أوّلًا … أمّا **الأقسام فأساسيّة**». والمفاتيح
            // مفاتيح جدول `tracks` بالحرف — و«department» لا يدخلها أبدًا.
            ['volunteer.optional_cut.tracks', 'offboarding', 'مسارات العضويّات الاختياريّة التي تُبتَر عند −9.5', 'json', '["governorate","case_file"]'],

            /*
             | ⭐ **الدرجة الأولى — الإنذار عند −8** (23-0.2-1). المهلة رقمٌ منصوص
             | («خلال **48 ساعة**») فهي قيمة افتراضيّة قابلة للتعديل لا ثابتٌ
             | نظاميّ (2.13-ب)، وكلّ نصٍّ يقرؤه بشرٌ معه مفتاحُه — فلا رسالة
             | محروقة في مسار عرض.
             */
            ['volunteer.rep_warning.contact_hours', 'offboarding', 'مهلة التواصل الموثَّق بعد إنذار −8 (ساعة)', 'number', '48'],
            ['volunteer.rep_warning.duty_title', 'offboarding', 'عنوان إشعار الالتزام لأبلاين العضويّة الكاسرة', 'string', 'مطلوب منك تواصل موثَّق خلال :hours ساعة'],
            ['volunteer.rep_warning.duty_body', 'offboarding', 'نصّ إشعار الالتزام لأبلاين العضويّة الكاسرة', 'text', 'درجة الالتزام لـ:name وصلت :score، والمعاملة اللي كسرت الحاجز وقعت في عضويّتك معاه. كلّمه وسجّل التواصل في الملاحظات الإداريّة خلال :hours ساعة.'],
            ['volunteer.rep_warning.upline_title', 'offboarding', 'عنوان إشعار الإنذار لباقي الأبلاينز', 'string', 'إنذار درجة الالتزام لواحد من فريقك'],
            ['volunteer.rep_warning.upline_body', 'offboarding', 'نصّ إشعار الإنذار لباقي الأبلاينز', 'text', 'درجة الالتزام لـ:name وصلت :score — المؤشّر الأحمر شغّال. التواصل الموثَّق واقع على أبلاين العضويّة اللي وقعت فيها المعاملة، وأنت شايف الحالة عشان تسند.'],
            ['volunteer.rep_warning.breach_title', 'offboarding', 'عنوان إشعار فوات التزام التواصل', 'string', 'فاتت مهلة التواصل الموثَّق'],
            ['volunteer.rep_warning.breach_body', 'offboarding', 'نصّ إشعار فوات التزام التواصل', 'text', 'عدّت :hours ساعة ولسّه مفيش توثيق تواصل مع :name بعد إنذار درجة الالتزام. التوثيق ده بيتقرا في ملفّ لجنة التحقيق لو الدرجة كمّلت نزول.'],
            ['volunteer.rep_warning.note_body', 'offboarding', 'قالب الملاحظة الإداريّة لتوثيق التواصل', 'text', 'توثيق تواصل إنذار درجة الالتزام (:score): :body'],
            ['volunteer.rep_warning.error_missing', 'offboarding', 'رسالة: الإنذار غير موجود', 'string', 'الإنذار ده مش موجود.'],
            ['volunteer.rep_warning.error_done', 'offboarding', 'رسالة: التواصل موثَّق بالفعل', 'string', 'التواصل ده متوثَّق خلاص — مفيش توثيق تاني لنفس الإنذار.'],
            ['volunteer.rep_warning.error_actor', 'offboarding', 'رسالة: التوثيق ليس عليك', 'string', 'التزام التواصل ده واقع على أبلاين العضويّة اللي وقعت فيها المعاملة — مش عليك.'],

            /*
             | ⭐ **الدرجة الأخيرة — التعليق عند −10** (23-0.2-4). ولا مهلة هنا
             | ولا موعد انتهاء: «**التصفير الشهري لا يفكّ التعليق** … يظلّ معلَّقًا
             | حتى قرار اللجنة والقمّة» — فالإعدادات نصوصٌ فقط، وأيّ إعداد مدّةٍ
             | كان سيكون مخالفةً للنصّ لا تسهيلًا للمالك.
             */
            ['volunteer.suspension.user_title', 'offboarding', 'عنوان إشعار التعليق للمتطوّع', 'string', 'اتعلّقت عضويّاتك في التطوّع مؤقّتًا'],
            ['volunteer.suspension.user_body', 'offboarding', 'نصّ إشعار التعليق للمتطوّع', 'text', 'درجة الالتزام وصلت :score، فاتعلّقت عضويّاتك ولوحة التطوّع لحدّ ما لجنة التحقيق تسمع منك. مهامّك المفتوحة راحت لأبلايناتها بلا أيّ خصم جديد، ونقاط الإنتاج كلّها باقية. هنكلّمك على بيانات تواصلك لميعاد الميتينج.'],
            ['volunteer.suspension.cover_title', 'offboarding', 'عنوان إشعار تغطية البوزشن للأبلاين', 'string', 'انتقلت لك مسؤوليّات إشرافيّة مؤقّتًا'],
            ['volunteer.suspension.cover_body', 'offboarding', 'نصّ إشعار تغطية البوزشن للأبلاين', 'text', 'حساب :name اتعلّق مؤقّتًا، ومسؤوليّاته الإشرافيّة في :entity بقت عندك (المراجعات ونوافذ التصعيد ودفعات الصب-تاسكات) لحدّ ما ينتهي التحقيق. عدد اللي تحته: :downline.'],
            ['volunteer.suspension.release_title', 'offboarding', 'عنوان إشعار رفع التعليق', 'string', 'رجع حسابك في التطوّع ✓'],
            ['volunteer.suspension.release_body', 'offboarding', 'نصّ إشعار رفع التعليق', 'text', 'اترفع التعليق ورجعت عضويّة قسمك شغّالة. المؤشّر الأحمر لسّه قايم كإنذار، والعضويّات الاختياريّة بتفضل مقفولة لحدّ التصفير الشهريّ.'],
            ['volunteer.suspension.chance_reason', 'offboarding', 'سبب معاملة فرصة اللجنة في السجلّ', 'string', 'فرصة لجنة التحقيق — قرار موثَّق بمرجع اللجنة'],
            ['volunteer.suspension.task_reason', 'offboarding', 'سبب تحويل مهامّ المعلَّق لمسار عدم التسليم', 'string', 'اتعلّق حساب صاحب المهمّة عند عتبة التعليق — المهمّة تدور على مالك جديد'],
            ['volunteer.suspension.hold_release_reason', 'offboarding', 'سبب تحرير الرصيد المعلَّق عند سحب مساهمة المعلَّق', 'string', 'تحرير الرصيد المعلَّق بعد سحب المساهمة عند تعليق الحساب'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
            ]);
        }
    }
}
