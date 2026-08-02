<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * الأدوار الأساسيّة العشرون كقوالب (الدستور 12.2.3).
 * قواعد: يجوز حمل أكثر من دور والصلاحيّة = اتّحادها مع Deny > Allow.
 * الدور يحدّد «ماذا» والعضويّة تحدّد «أين».
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            // ---------------- أدوار المنصّة (10)
            ['platform_owner', 'مالك المنصّة', 'platform', false, false, 'أعلى الجميع، ويملك المجموعة المحميّة والماليّات — غير قابل للحذف.'],
            ['super_admin', 'أدمن عامّ', 'platform', true, false, 'إدارة كاملة عدا المجموعة المحميّة لمالك المنصّة.'],
            ['content_admin', 'مسؤول المحتوى التعليميّ', 'platform', true, false, 'المسارات والتدريبات والسيكشنز والدروس ومكتبة الوسائط والاختبارات.'],
            ['certificates_admin', 'مسؤول الشهادات', 'platform', true, false, 'الاعتمادات والأنواع والقوالب والإصدار والسجلّ وصفحة التحقّق.'],
            ['support_admin', 'مسؤول دعم المستخدمين', 'platform', true, false, 'الحسابات والاعتمادات والشكاوى ودليل المستخدم.'],
            ['marketing_admin', 'مسؤول التسويق والمتجر', 'platform', true, false, 'المنتجات والبندلز والكوبونات والمقالات واستوديو الصور وحلقات النموّ.'],
            ['finance_admin', 'المسؤول الماليّ', 'platform', true, false, 'الطلبات والفواتير وشحن الحساب — والماليّات المعزولة لمالك المنصّة وحده.'],
            ['gamification_admin', 'مسؤول التلعيب والتحديات', 'platform', true, false, 'XP والتذاكر والشارات والستريكس والليدر بورد والحروب.'],
            ['tech_admin', 'المسؤول التقنيّ', 'platform', true, false, 'الإعدادات والصيانة والتحديثات والنسخ الاحتياطيّ وصحّة النظام.'],
            ['auditor', 'مدقّق (قراءة فقط)', 'platform', true, false, 'اطّلاع على كلّ شيء بلا أيّ تعديل.'],

            // ---------------- أدوار التطوّع (8) — تُسنَد داخل عضويّة
            ['volunteer_gm', 'مشرف عام التطوّع', 'volunteer', true, true, 'سقف طبقة التطوّع فقط — والأدمن العامّ يعلوه (12.2.1-ز-5).'],
            ['track_supervisor', 'مشرف عام المسار', 'volunteer', true, true, 'الأقسام أو المحافظات أو الملفّات — بنطاق TRACK.'],
            ['director', 'دايركتور الكيان', 'volunteer', true, true, 'إدارة الكيان الرئيسيّ وحزمه وبنوده ومكتبته وسعته.'],
            ['supervisor', 'سوبرفايزر', 'volunteer', true, true, 'إشراف على فرعيّ داخل الكيان، ومعاملات السلوك لداونلاينه.'],
            ['team_leader', 'تيم ليدر', 'volunteer', true, true, 'قيادة فريق: إسناد ومراجعة داخل نطاقه.'],
            ['coordinator', 'كوردنيتور', 'volunteer', true, true, 'التنفيذ: مهامّه والمُسنَد إليه والعامّة.'],
            ['recruiter', 'فريق التوظيف', 'volunteer', true, true, 'المرشّحون والمقابلات والقوائم والتسكين — ورقم المرشّح لهم وحدهم.'],
            ['academy_manager', 'مسؤول الأكاديمية', 'volunteer', true, true, 'مسارات وتدريبات وتسجيلات الأكاديمية للمتطوّعين.'],

            // ---------------- أدوار المستخدم (2)
            ['trainee', 'متدرّب', 'user', true, false, 'الدور الافتراضيّ لكلّ حساب مفعَّل.'],
            ['pending_review', 'تحت المراجعة', 'user', true, false, 'حساب مُسجَّل لم يُعتمَد بعد — وصول محدود.'],
        ];

        foreach ($roles as [$key, $name, $layer, $deletable, $needsMembership, $desc]) {
            Role::updateOrCreate(
                ['key' => $key],
                [
                    'name_ar' => $name,
                    'description' => $desc,
                    'layer' => $layer,
                    'is_system' => true,
                    'is_deletable' => $deletable,
                    'requires_membership' => $needsMembership,
                ],
            );
        }

        $this->command?->info('الأدوار الأساسيّة: '.Role::count());
    }
}
