<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **الاختبار التمهيديّ يتخطّى نفسه صامتًا لمّا بنك الأسئلة فاضي.**
 *
 * `PlacementTest::isEnabled()` (2.5-د-2) ترجع `false` حين لا سؤال نشِط في
 * البنك — سواء كان ذلك قرار المالك الصريح («onboarding.placement.enabled»
 * = false) أو مجرّد أنّ آخر سؤال نشِط تمّ إيقافه/حذفه. والحالتان تُنتِجان
 * نفس الأثر على `OnboardingJourney::currentStep()`: الخطوة تُتخطّى لكلّ
 * مُسجَّل جديد — لكنّ الأدمن لا يرى أيّ فرق بين «أوقفتها أنا» و«فرغ البنك
 * بالخطأ»، فالتصفية المنصوصة في 2.5-د تسقط بصمت.
 *
 * هذه الهجرة تضيف نصّ التحذير الذي تعرضه شاشة `placement.admin.index` حين
 * يكون الإعداد مفعَّلًا وبلا سؤال نشِط — بديل التخطّي الصامت السابق.
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        ['onboarding.placement.admin.skipped_title', 'onboarding', 'عنوان تحذير تخطّي الاختبار التمهيديّ صامتًا', 'string', 'الاختبار التمهيديّ متوقّف فعليًّا الآن'],
        ['onboarding.placement.admin.skipped_warning', 'onboarding', 'نصّ تحذير تخطّي الاختبار التمهيديّ صامتًا', 'string', 'الإعداد «مفعَّل» لكن ولا سؤال نشِط في البنك — فكلّ مُسجَّل جديد يتخطّى هذه الخطوة صامتًا. أضِف سؤالًا نشِطًا أو أوقف الخطوة من الإعدادات صراحةً.'],
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
