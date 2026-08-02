<?php

namespace App\Support\Access;

/**
 * ⭐ خريطة نصوص الشروط العربيّة (مصفوفة 12.2.2) ⟵ مفاتيح القائمة المقفولة (12.2.1-ج).
 *
 * **لماذا هذا الملفّ؟** كان عمود «الشرط» في المصفوفة نصًّا عربيًّا وصفيًّا يُخزَّن في
 * `permissions.condition_key` ويُعرَض في الشاشة **ولا يُقيَّم أبدًا** — 423 شرطًا
 * زينةً على الورق. والنصّ يبقى للعرض (فهو ما يقرأه المسؤول)، أمّا **التقييم** فيقع
 * على المفاتيح المقفولة وحدها: `is_owner` · `not_self` · `state:published` … إلخ.
 *
 * وقاعدة الملفّ صارمة: **كلّ نصٍّ في المصفوفة له مفتاح هنا**. ونصٌّ بلا مفتاح
 * يرفعه `unmapped()` تقريرًا صريحًا — لا يمرّ صامتًا ولا يُترجَم بالتخمين.
 */
final class ConditionMap
{
    /** بادئة شرط الحالة — نمط `state=pending` المنصوص في 12.2.1-ج */
    public const STATE_PREFIX = 'state:';

    /** نصّ «بلا شرط» في المصفوفة */
    public const ALWAYS = 'دائمًا';

    /**
     * النصّ العربيّ ⟵ المفتاح المقفول.
     *
     * ⚠️ تمييزٌ جوهريّ لا يجوز الخلط فيه:
     *   «مالك المنصّة فقط» = **عزل** المجموعة المحميّة ⟵ `is_owner_only = true`.
     *   «المالك فقط»       = **مالك السجلّ نفسه** (صاحب البيانات) ⟵ شرط `is_owner`.
     * وخلطُهما هو الذي حبس المستخدم عن أرباحه هو، وترك الماليّات بلا عزل.
     *
     * @var array<string, string>
     */
    private const MAP = [
        // ————————————————————————————————————————— شروط الفاعل والزمن والنظام
        'مالك المنصّة فقط' => 'platform_owner',
        'المالك فقط' => 'is_owner',
        'ليس نفسه' => 'not_self',
        'الأبلاين المباشر' => 'direct_upline',
        'داخل النافذة الزمنيّة' => 'within_window',
        'قبل الديدلاين' => 'before_deadline',
        'الميزة مفعّلة' => 'feature_enabled',

        // ————————————————————————————————————————————————— حالات السجلّ
        'الحالة = منشور' => self::STATE_PREFIX.'published',
        'الحالة = مسودّة' => self::STATE_PREFIX.'draft',
        'الحالة = مسودّة أو قادمة' => self::STATE_PREFIX.'draft_or_upcoming',
        'الحالة = مؤرشف' => self::STATE_PREFIX.'archived',
        'الحالة = قيد المراجعة' => self::STATE_PREFIX.'in_review',
        'الحالة = تحت المراجعة' => self::STATE_PREFIX.'under_review',
        'الحالة = بانتظار الاعتماد' => self::STATE_PREFIX.'awaiting_approval',
        'الحالة = معتمد' => self::STATE_PREFIX.'approved',
        'الحالة = مرفوض' => self::STATE_PREFIX.'rejected',
        'الحالة = مرشّح' => self::STATE_PREFIX.'nominated',
        'الحالة = مقترح' => self::STATE_PREFIX.'proposed',
        'الحالة = نشط' => self::STATE_PREFIX.'active',
        'الحالة = غير نشطة' => self::STATE_PREFIX.'inactive',
        'الحالة = موقوفة' => self::STATE_PREFIX.'paused',
        'الحالة = معلَّق' => self::STATE_PREFIX.'suspended',
        'الحالة = ساري' => self::STATE_PREFIX.'in_force',
        'الحالة = سارية' => self::STATE_PREFIX.'valid',
        'الحالة = منتهية الصلاحيّة' => self::STATE_PREFIX.'expired',
        'الحالة = مفتوح' => self::STATE_PREFIX.'open',
        'الحالة = قضيّة مفتوحة' => self::STATE_PREFIX.'case_open',
        'الحالة = مغلق' => self::STATE_PREFIX.'closed',
        'الحالة = مغلقة' => self::STATE_PREFIX.'closed',
        'الحالة = منتهية' => self::STATE_PREFIX.'ended',
        'الحالة = منتهٍ' => self::STATE_PREFIX.'ended',
        'الحالة = مكتمل' => self::STATE_PREFIX.'completed',
        'الحالة = مكتملة' => self::STATE_PREFIX.'completed',
        'الحالة = قيد التنفيذ' => self::STATE_PREFIX.'in_progress',
        'الحالة = لم يبدأ' => self::STATE_PREFIX.'not_started',
        'الحالة = مجدولة' => self::STATE_PREFIX.'scheduled',
        'الحالة = أُرسل للتنفيذ' => self::STATE_PREFIX.'sent_to_execution',
        'الحالة = قبل إرسال للتنفيذ' => self::STATE_PREFIX.'before_execution',
        'الحالة = ناجح' => self::STATE_PREFIX.'passed',
        'الحالة = فشل' => self::STATE_PREFIX.'failed',
        'الحالة = القائمة النهائيّة' => self::STATE_PREFIX.'final_list',
        'الحالة = شاغر' => self::STATE_PREFIX.'vacant',
        'الحالة = مدعوّ' => self::STATE_PREFIX.'invited',
        'الحالة = مستخدَم' => self::STATE_PREFIX.'used',
        'الحالة = مخفيّ' => self::STATE_PREFIX.'hidden',
        'الحالة = محذوف' => self::STATE_PREFIX.'deleted',
        'الحالة = عدم تسليم' => self::STATE_PREFIX.'no_delivery',

        // ——————————————————————— حالات يحرسها المجال (لا عمود حالة يقيسها)
        'الحالة = بلا أعضاء نشطين' => self::STATE_PREFIX.'no_active_members',
        'الحالة = بلا مهامّ مفتوحة' => self::STATE_PREFIX.'no_open_tasks',
        'الحالة = المهامّ محسومة' => self::STATE_PREFIX.'tasks_settled',
        'الحالة = بلغت عتبة 3 مقيّمين' => self::STATE_PREFIX.'rater_threshold_met',
        'الحالة = المعاينة النهائيّة' => self::STATE_PREFIX.'final_review',
        'الحالة = المعاينة مكتملة' => self::STATE_PREFIX.'review_complete',
        'الحالة = رُفعت معاينة' => self::STATE_PREFIX.'review_submitted',
        'الحالة = الطبقة الحائزة' => self::STATE_PREFIX.'holding_layer',
        'الحالة = ضمن سقف الانشغال' => self::STATE_PREFIX.'within_load_cap',
        'الحالة = مستوى الوصول المطابق' => self::STATE_PREFIX.'access_level_matches',
        'الحالة = مستوى الوصول «كيانه فقط»' => self::STATE_PREFIX.'access_entity_only',
        'الحالة = مستوى الوصول «كلّ المتطوّعين»' => self::STATE_PREFIX.'access_all_volunteers',
        'الحالة = لا حرب نشطة' => self::STATE_PREFIX.'no_active_war',
        'الحالة = غير مُنصَّب' => self::STATE_PREFIX.'not_installed',
        'الحالة = الدرس السابق مكتمل' => self::STATE_PREFIX.'previous_lesson_completed',
        'الحالة = الدرس متاح' => self::STATE_PREFIX.'lesson_available',
        'الحالة = الفيديو مُشاهَد' => self::STATE_PREFIX.'video_watched',
        'الحالة = دروس التدريب مكتملة' => self::STATE_PREFIX.'course_lessons_completed',
        'الحالة = ناجح في الامتحان النهائيّ' => self::STATE_PREFIX.'passed_final_exam',
        'الحالة = التفاعل مسموح' => self::STATE_PREFIX.'interaction_allowed',
        'الحالة = حضر المقابلة' => self::STATE_PREFIX.'interview_attended',
        'الحالة = بانتظار موافقة المرشّح' => self::STATE_PREFIX.'awaiting_candidate',
        'الحالة = لم يبدأ التأهيليّ' => self::STATE_PREFIX.'qualifying_not_started',
        'الحالة = بوزشن جديد مثبَّت' => self::STATE_PREFIX.'position_confirmed',
        'الحالة = اعتراض مقبول' => self::STATE_PREFIX.'objection_accepted',
        'الحالة = تعليق الحساب (−10)' => self::STATE_PREFIX.'suspension_threshold',
        'الحالة = توصية مرفوعة' => self::STATE_PREFIX.'recommendation_raised',
        'الحالة = صدر القرار' => self::STATE_PREFIX.'decision_issued',
        'الحالة = مربوط بمسار' => self::STATE_PREFIX.'linked_to_track',
        'الحالة = مربوطة بالكيان' => self::STATE_PREFIX.'linked_to_entity',
        'الحالة = ضمن نطاق العضويّة' => self::STATE_PREFIX.'within_membership_scope',
        'الحالة = حزمة قيد الملء' => self::STATE_PREFIX.'package_filling',
        'الحالة = طريق الرجوع' => self::STATE_PREFIX.'rollback_path',
        'الحالة = مشروع تشغيليّ مفتوح' => self::STATE_PREFIX.'operational_project_open',
        'الحالة = ضمن المشروع التشغيليّ' => self::STATE_PREFIX.'within_operational_project',
        'الحالة = اعتماد أوّل' => self::STATE_PREFIX.'first_approval',
        'الحالة = صب-تاسك معتمد' => self::STATE_PREFIX.'subtask_approved',
        'الحالة = مهمّة معتمدة' => self::STATE_PREFIX.'task_approved',
        'الحالة = منوط بالمهمّة' => self::STATE_PREFIX.'bound_to_task',
        'الحالة = دعوة مساهمة' => self::STATE_PREFIX.'contribution_invite',
        'الحالة = انضباط تسليم مخلّ' => self::STATE_PREFIX.'delivery_discipline_breach',
        'الحالة = المحكّم الحالي' => self::STATE_PREFIX.'current_arbiter',
        'الحالة = التسجيل مفتوح' => self::STATE_PREFIX.'registration_open',
        'الحالة = مكتمل البيانات' => self::STATE_PREFIX.'profile_complete',
        'الحالة = إقرار إلزاميّ' => self::STATE_PREFIX.'ack_required',
        'الحالة = الرصيد ≥ 12 تذكرة' => self::STATE_PREFIX.'tickets_balance_12',
        'الحالة = الرصيد ≥ 1 تذكرة' => self::STATE_PREFIX.'tickets_balance_1',
        'الحالة = أقلّ من 5 تحدّيات نشطة' => self::STATE_PREFIX.'under_challenge_cap',
        'الحالة = 7 أيّام متواصلة' => self::STATE_PREFIX.'streak_7_days',
        'الحالة = غير ممنوحة' => self::STATE_PREFIX.'not_granted',
        'الحالة = متاح للسحب' => self::STATE_PREFIX.'withdrawable',
    ];

    /** @return array<string, string> النصّ العربيّ => المفتاح */
    public static function all(): array
    {
        return self::MAP;
    }

    /**
     * مفاتيح التقييم لنصوص شرطٍ عربيّة.
     *
     * «دائمًا» = بلا شرط. و«مالك المنصّة فقط» عزلٌ لا شرطُ سجلّ، ومع ذلك يبقى
     * مفتاحًا مقيَّمًا (دفاعٌ ثانٍ لو سقط وسم `is_owner_only` يومًا).
     *
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    public static function keysFor(array $texts): array
    {
        $keys = [];

        foreach ($texts as $text) {
            $text = trim((string) $text);

            if ($text === '' || $text === self::ALWAYS) {
                continue;
            }

            if (isset(self::MAP[$text])) {
                $keys[] = self::MAP[$text];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * نصوصٌ لا مفتاح لها — تُبلَّغ ولا تمرّ.
     *
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    public static function unmapped(array $texts): array
    {
        $missing = [];

        foreach ($texts as $text) {
            $text = trim((string) $text);

            if ($text === '' || $text === self::ALWAYS || isset(self::MAP[$text])) {
                continue;
            }

            $missing[] = $text;
        }

        return array_values(array_unique($missing));
    }

    /** هل المفتاح ضمن القائمة المقفولة (بسيطًا كان أو حالةً)؟ */
    public static function isLocked(string $key): bool
    {
        if (str_starts_with($key, self::STATE_PREFIX)) {
            return array_key_exists(substr($key, strlen(self::STATE_PREFIX)), config('access.condition_states', []));
        }

        return array_key_exists($key, config('access.conditions', []));
    }
}
