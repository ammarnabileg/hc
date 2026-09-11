<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `topup.min_amount` — **تكرارٌ صامتٌ كان يتجاوز شاشة المالك**، لا يتيمٌ عاديّ.
 *
 * المنصّة كانت تحمل مجموعتَي «حدود شحن» لا تلتقيان:
 * - `finance.topup.min_amount` = **50** (و`max_amount` و`daily_limit`) مزروعةٌ في
 *   `AdminSystemDemoSeeder` ومعروضةٌ للتحرير في شاشة **🔒 الماليّات** — التي
 *   ينصّ 24.3 على أنّها «**مصدر الحقيقة الوحيد لكلّ رقم ماليّ**». **ولا قارئ لها.**
 * - `topup.min_amount` = **10** مزروعةٌ في `WalletDemoSeeder`، ويقرؤها
 *   `TopupController::storeManual()` **وحدها**.
 *
 * فالمالك يضبط 50 في شاشته ويمرّ تحويلٌ بـ20 — لا لخطأ في الحفظ بل لأنّ
 * الكنترولر كان يسأل مفتاحًا آخر لا تعرضه أيّ شاشةٍ لتلك الحدود. وهذا أسوأ من
 * إعدادٍ بلا أثر: إعدادٌ **يُظهِر أثرًا كاذبًا**.
 *
 * الحلّ: `TopupLimits` مصدرٌ واحد يقرأ `finance.topup.*` (سياسة المنصّة) ويضيّقها
 * بـ`topup.gateway.min_amount/max_amount` على مسار البوّابة وحده (19.5-ج-5).
 * وحُذف موضع الزرع من `WalletDemoSeeder` في نفس الدفعة — وإلّا عاد المفتاح في
 * أوّل `migrate:fresh --seed`.
 */
return new class extends Migration
{
    private const DEAD_KEYS = [
        'topup.min_amount',
    ];

    public function up(): void
    {
        $ids = DB::table('settings')->whereIn('key', self::DEAD_KEYS)->pluck('id');

        DB::table('setting_overrides')->whereIn('setting_id', $ids)->delete();
        DB::table('settings')->whereIn('key', self::DEAD_KEYS)->delete();
    }

    /**
     * لا عكس: إعادة المفتاح إعادةُ التجاوز الصامت نفسه لا تراجعٌ عن خطأ.
     * ومَن أراد حدًّا أدنى مختلفًا يضبطه من شاشة 🔒 الماليّات — وهي مكانه.
     */
    public function down(): void
    {
        // بلا عكس — انظر التعليق أعلاه.
    }
};
