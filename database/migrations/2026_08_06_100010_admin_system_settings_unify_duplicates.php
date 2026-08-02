<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * توحيد الإعدادات المكرَّرة (2.13).
 *
 * لماذا؟ لأنّ مفتاحين لنفس المعنى يعنيان مصدرَي حقيقة: يعدّل المالك واحدًا
 * ويقرأ الكودُ الآخر، فيبدو أنّ الإعداد «لا يعمل». والمجموعتان لنفس البادئة
 * (`celebrations.*` في مجموعتين) تكسران البحث الموحّد: النتيجة تفتح تابًا
 * لا يسكنه المفتاح. الترحيل هنا يحفظ قيمة المالك ولا يترك مفتاحًا يتيمًا.
 */
return new class extends Migration
{
    /** المفتاح المهجور ⟵ المفتاح المعتمَد */
    private const MERGE = [
        // طول كود حضور الفعاليّة — النمط المنقَّط هو المعتمَد (2.13)
        'events.attendance_code_length' => 'events.attendance.code_length',

        // الستريك ونادي الخامسة: الشاشة والخدمة كانتا على تهجئتين
        'streaks.club_5am.window_start' => 'streaks.club5am.window_start',
        'streaks.club_5am.window_end' => 'streaks.club5am.window_end',
        'streaks.reward.every_days' => 'streaks.reward_days',

        // السمعة: grantor/granter وساعة التصفير مرّتين
        'rep.behavior.monthly_cap_per_grantor' => 'rep.behavior.monthly_cap_per_granter',
        'rep.reset.hour_cairo' => 'rep.reset.hour',

        // عتبات الإشغال: نقطة مقابل شرطة سفليّة
        'volunteer.org.occupancy.warn_percent' => 'volunteer.org.occupancy_warn_percent',
        'volunteer.org.occupancy.danger_percent' => 'volunteer.org.occupancy_danger_percent',

        // الخروج والعودة: مجموعة `offboarding` كانت نسخةً ثانية من `volunteer.offboarding.*`
        'offboarding.notice_days' => 'volunteer.offboarding.notice_days',
        'offboarding.cooldown.resignation_days' => 'volunteer.offboarding.cooldown_days.resignation',
        'offboarding.cooldown.thresholds_days' => 'volunteer.offboarding.cooldown_days.thresholds',
        'offboarding.inactivity.alert_days' => 'rep.inactivity.days_before_alert',
        'reentry.exam.required' => 'volunteer.offboarding.reentry_exam_required',
    ];

    /** المجموعة المهجورة ⟵ المجموعة المعتمَدة (نفس البادئة لا تسكن مجموعتين) */
    private const REGROUP = [
        'celebrations' => 'gamification_celebrations',
        'streaks' => 'gamification_streaks',
        'leaderboard' => 'gamification_leaderboard',
        'rep' => 'volunteer_rep',
        'offboarding' => 'volunteer_offboarding',
    ];

    /** مفاتيح وُضِعت في مجموعة الجار: المفتاح ⟵ مجموعته الصحيحة */
    private const REKEY_GROUP = [
        'volunteer_cert.min_days_in_position' => 'volunteer_cert',
        'wallet.tickets.earn_sources' => 'wallet',
        'wallet.tickets.spend_targets' => 'wallet',
        'library.reader.session_minutes' => 'library',
        'library.watermark.font_size' => 'library',
        'library.watermark.opacity_percent' => 'library',
    ];

    public function up(): void
    {
        foreach (self::MERGE as $orphanKey => $canonicalKey) {
            $orphan = DB::table('settings')->where('key', $orphanKey)->first();

            if (! $orphan) {
                continue;
            }

            $canonical = DB::table('settings')->where('key', $canonicalKey)->first();

            if (! $canonical) {
                // المعتمَد غير مزروع بعد: نرقّي المهجور نفسه بدل حذف قيمةٍ بلا بديل
                DB::table('settings')->where('key', $orphanKey)->update(['key' => $canonicalKey]);

                continue;
            }

            // ⭐ لا نضيع تخصيص المالك: لو عدّل المهجور ولم يمسّ المعتمَد، القيمة تنتقل
            $ownerTouchedOrphan = $orphan->value !== $orphan->default_value;
            $canonicalUntouched = $canonical->value === $canonical->default_value;

            if ($ownerTouchedOrphan && $canonicalUntouched) {
                DB::table('settings')->where('key', $canonicalKey)->update(['value' => $orphan->value]);
            }

            DB::table('setting_overrides')->where('setting_id', $orphan->id)->delete();
            DB::table('settings')->where('key', $orphanKey)->delete();
        }

        foreach (self::REGROUP as $from => $to) {
            DB::table('settings')->where('group', $from)->update(['group' => $to]);
        }

        foreach (self::REKEY_GROUP as $key => $group) {
            DB::table('settings')->where('key', $key)->update(['group' => $group]);
        }
    }

    /**
     * الترحيل دمجٌ لا نقل: بعد الدمج لا نعرف أيّ مفتاح جاء من أين، وإعادةُ
     * مصدرَي حقيقة عمدًا ليست «تراجعًا» بل إعادةٌ للعطل. فالرجوع لا يفعل شيئًا،
     * والاسترجاع الصحيح من نسخة احتياطيّة.
     */
    public function down(): void
    {
        // بلا عكس — انظر التعليق أعلاه.
    }
};
