<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **الاختبار التمهيديّ: جدولٌ بلا شاشة ⟵ شاشة ببنود 24.**
 *
 * الدستور 2.5-د-2 حرفيًّا: «**اختبار تمهيدي (Placement):** يُدار من الأدمن —
 * الأسئلة ممكن تكون (**فيديو و/أو كود Embedded HTML من أي مكان و/أو نص و/أو
 * صورة**)، والإجابات مثل إجابات الاختبارات العادية. **مكافأة لكل سؤال** بجانبه:
 * **XP فقط أو تذاكر فقط أو الاثنين**».
 *
 * و24 (محتوى الـOnboarding): «**[الاختبار التمهيديّ]** جدول أسئلة: السؤال ·
 * النوع (فيديو/HTML/نصّ/صورة) · **مكافأة XP** · **مكافأة تذاكر** · الترتيب
 * (سحب) · الحالة · إجراءات» و«**[سؤال]** النصّ + النوع + الوسائط + الخيارات +
 * الإجابة الصحيحة + XP + تذاكر» و«**الحالات:** فارغة «لا أسئلة تمهيديّة — أضِف
 * أوّل سؤال» … **خطأ HTML غير صالح ⇒ تحذير قبل الحفظ**».
 *
 * وكلّ نصٍّ ورقمٍ في تلك الشاشة يُكتَب هنا لا في الكود (2.13)، **وفي هجرة لا في
 * بذرة عرض** لأنّ الإنتاج يشغّل الهجرات ولا يشغّل البذور — كما فعلت هجرة
 * `2026_08_09_120030` بإعدادات الرحلة نفسها.
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // أنواع الوسيط المنصوصة حرفيًّا في 2.5-د-2 — لا نوعَ مخترَع
        ['onboarding.placement.media_kinds', 'onboarding', 'أنواع وسيط سؤال الاختبار التمهيديّ', 'json',
            '{"none":"نصّ فقط","video":"فيديو","image":"صورة","embed":"كود HTML مضمَّن"}'],
        ['onboarding.placement.answer_types', 'onboarding', 'أنواع إجابة سؤال الاختبار التمهيديّ', 'json',
            '{"choice":"اختيار من متعدّد","text":"إجابة نصّيّة"}'],

        // ---- شاشة الأدمن
        ['onboarding.placement.admin.title', 'onboarding', 'عنوان شاشة بناء الاختبار التمهيديّ', 'string', 'الاختبار التمهيديّ'],
        ['onboarding.placement.admin.subtitle', 'onboarding', 'سطر شرح شاشة الاختبار التمهيديّ', 'string', 'أسئلة المسجّل الجديد ومكافأة كلّ سؤال — والترتيب بالسحب.'],
        ['onboarding.placement.admin.empty', 'onboarding', 'الحالة الفارغة لبنك الأسئلة', 'string', 'لا أسئلة تمهيديّة — أضِف أوّل سؤال'],
        ['onboarding.placement.admin.add_cta', 'onboarding', 'زرّ إضافة سؤال تمهيديّ', 'string', '+ سؤال'],
        ['onboarding.placement.admin.export_cta', 'onboarding', 'زرّ تصدير إجابات المتقدّمين', 'string', 'تصدير الإجابات'],
        ['onboarding.placement.admin.form_title', 'onboarding', 'عنوان بوب-أب السؤال التمهيديّ', 'string', 'سؤال الاختبار التمهيديّ'],
        ['onboarding.placement.admin.save_cta', 'onboarding', 'زرّ حفظ السؤال التمهيديّ', 'string', 'احفظ السؤال'],
        ['onboarding.placement.admin.edit_cta', 'onboarding', 'زرّ تعديل السؤال التمهيديّ', 'string', 'تعديل'],
        ['onboarding.placement.admin.pause_cta', 'onboarding', 'زرّ إيقاف السؤال التمهيديّ', 'string', 'إيقاف'],
        ['onboarding.placement.admin.resume_cta', 'onboarding', 'زرّ تشغيل السؤال التمهيديّ', 'string', 'تشغيل'],
        ['onboarding.placement.admin.delete_cta', 'onboarding', 'زرّ حذف السؤال التمهيديّ', 'string', 'حذف'],
        ['onboarding.placement.admin.delete_confirm', 'onboarding', 'تأكيد حذف السؤال التمهيديّ', 'string', 'هنشيل السؤال وإجاباته — نكمّل؟'],
        ['onboarding.placement.admin.move_up', 'onboarding', 'تحريك السؤال لأعلى', 'string', 'حرّك لأعلى'],
        ['onboarding.placement.admin.move_down', 'onboarding', 'تحريك السؤال لأسفل', 'string', 'حرّك لأسفل'],

        // ---- أعمدة الجدول كما نصّ 24
        ['onboarding.placement.admin.col_prompt', 'onboarding', 'عمود: السؤال', 'string', 'السؤال'],
        ['onboarding.placement.admin.col_kind', 'onboarding', 'عمود: النوع', 'string', 'النوع'],
        ['onboarding.placement.admin.col_xp', 'onboarding', 'عمود: مكافأة XP', 'string', 'مكافأة XP'],
        ['onboarding.placement.admin.col_tickets', 'onboarding', 'عمود: مكافأة تذاكر', 'string', 'مكافأة تذاكر'],
        ['onboarding.placement.admin.col_order', 'onboarding', 'عمود: الترتيب', 'string', 'الترتيب'],
        ['onboarding.placement.admin.col_status', 'onboarding', 'عمود: الحالة', 'string', 'الحالة'],
        ['onboarding.placement.admin.col_actions', 'onboarding', 'عمود: إجراءات', 'string', 'إجراءات'],
        ['onboarding.placement.admin.answers_count', 'onboarding', 'تسمية عدد الإجابات على السؤال', 'string', 'إجابات:'],
        ['onboarding.placement.admin.state_active', 'onboarding', 'حالة السؤال: شغّال', 'string', 'شغّال'],
        ['onboarding.placement.admin.state_paused', 'onboarding', 'حالة السؤال: موقوف', 'string', 'موقوف'],

        // ---- حقول الفورم كما نصّ 24
        ['onboarding.placement.admin.field_prompt', 'onboarding', 'حقل: نصّ السؤال', 'string', 'نصّ السؤال'],
        ['onboarding.placement.admin.field_kind', 'onboarding', 'حقل: نوع الوسيط', 'string', 'النوع (فيديو/صورة/HTML/نصّ)'],
        ['onboarding.placement.admin.field_type', 'onboarding', 'حقل: نوع الإجابة', 'string', 'نوع الإجابة'],
        ['onboarding.placement.admin.field_media_url', 'onboarding', 'حقل: رابط الوسيط', 'string', 'رابط الفيديو أو الصورة'],
        ['onboarding.placement.admin.field_media_hint', 'onboarding', 'شرح حقل رابط الوسيط', 'string', 'يُملأ فقط لو النوع فيديو أو صورة.'],
        ['onboarding.placement.admin.field_embed', 'onboarding', 'حقل: كود HTML مضمَّن', 'string', 'كود HTML مضمَّن'],
        ['onboarding.placement.admin.field_embed_hint', 'onboarding', 'شرح حقل الكود المضمَّن', 'string', 'من أيّ مكان — وبنراجع اتّزان الوسوم قبل الحفظ.'],
        ['onboarding.placement.admin.field_options', 'onboarding', 'حقل: الخيارات', 'string', 'الخيارات'],
        ['onboarding.placement.admin.field_options_hint', 'onboarding', 'شرح حقل الخيارات', 'string', 'خيار في كلّ سطر — تُترَك فاضية للإجابة النصّيّة.'],
        ['onboarding.placement.admin.field_correct', 'onboarding', 'حقل: الإجابة الصحيحة', 'string', 'الإجابة الصحيحة'],
        ['onboarding.placement.admin.field_correct_hint', 'onboarding', 'شرح حقل الإجابة الصحيحة', 'string', 'سيبها فاضية لو السؤال استطلاعيّ بلا صحّ وغلط.'],
        ['onboarding.placement.admin.field_xp', 'onboarding', 'حقل: مكافأة XP', 'string', 'مكافأة XP'],
        ['onboarding.placement.admin.field_tickets', 'onboarding', 'حقل: مكافأة تذاكر', 'string', 'مكافأة تذاكر'],
        ['onboarding.placement.admin.field_active', 'onboarding', 'حقل: السؤال شغّال', 'string', 'السؤال شغّال'],

        // ---- الحدود والرسائل
        ['onboarding.placement.admin.max_reward_xp', 'onboarding', 'أقصى XP لسؤال تمهيديّ', 'number', '1000'],
        ['onboarding.placement.admin.max_reward_tickets', 'onboarding', 'أقصى تذاكر لسؤال تمهيديّ', 'number', '100'],
        ['onboarding.placement.admin.bad_html', 'onboarding', 'تحذير HTML غير صالح', 'string', 'الكود فيه وسم مش مقفول — صلّحه قبل الحفظ عشان ما يكسرش شاشة المسجّلين.'],
        ['onboarding.placement.admin.bad_media_kind', 'onboarding', 'خطأ نوع وسيط غير معروف', 'string', 'النوع ده مش من الأنواع المسموحة.'],
        ['onboarding.placement.admin.bad_type', 'onboarding', 'خطأ نوع إجابة غير معروف', 'string', 'نوع الإجابة ده مش مسموح.'],
        ['onboarding.placement.admin.created', 'onboarding', 'رسالة إضافة سؤال', 'string', 'اتضاف السؤال ✓'],
        ['onboarding.placement.admin.updated', 'onboarding', 'رسالة تعديل سؤال', 'string', 'اتحفظ السؤال ✓'],
        ['onboarding.placement.admin.toggled', 'onboarding', 'رسالة تبديل حالة السؤال', 'string', 'اتغيّرت حالة السؤال ✓'],
        ['onboarding.placement.admin.deleted', 'onboarding', 'رسالة حذف سؤال', 'string', 'اتشال السؤال ✓'],
        ['onboarding.placement.admin.export_file', 'onboarding', 'اسم ملفّ تصدير الإجابات', 'string', 'placement-answers.csv'],
        ['onboarding.placement.admin.export_headers', 'onboarding', 'رؤوس ملفّ تصدير الإجابات', 'json',
            '["الكود","الاسم","النتيجة %","السؤال","الإجابة","صحيحة","XP","تذاكر","وقت الإجابة"]'],
        ['onboarding.placement.admin.void_tags', 'onboarding', 'وسوم HTML بلا إغلاق (لفحص الاتّزان)', 'json',
            '["br","hr","img","input","meta","link","source","track","area","base","col","embed","param","wbr"]'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$key, $group, $label, $type, $default]) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();
    }
};
