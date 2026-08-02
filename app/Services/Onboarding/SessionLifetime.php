<?php

namespace App\Services\Onboarding;

/**
 * ⭐ 2.3: **الجلسة تُحفَظ مدى الحياة ولا تنتهي تلقائيًّا** — تفضل مفتوحة حتى
 * يعمل المستخدم «تسجيل خروج» بنفسه.
 *
 * لماذا صنفٌ مستقلّ لا سطرٌ في `config/session.php`؟ لأنّ ملفّات الإعداد تُقرَأ
 * **قبل** أن يقوم الحاوي وقاعدة البيانات، فلا تستطيع أن تسأل لوحة الإدارة —
 * وكتابة الرقم فيها يجعله محروقًا وهو ما تمنعه 2.13. وبوت المزوّدين يقع **قبل**
 * خطّ الميدل وير، أيْ قبل `StartSession` وقبل كتابة كوكي الجلسة، فهي أوّل نقطة
 * يصحّ فيها أن تأتي المدّة من `setting()`.
 */
class SessionLifetime
{
    /**
     * تطبيق المدّة المضبوطة من اللوحة على إعداد الجلسة.
     *
     * الفشل صامت عمدًا: أثناء `migrate` على قاعدة جديدة لا وجود لجدول الإعدادات،
     * ولا يجوز أن يسقط التنصيب من أجل قراءة تفضيل.
     *
     * @return int الدقائق المطبَّقة فعلًا
     */
    public static function apply(): int
    {
        $fallback = (int) config('session.lifetime');

        try {
            $minutes = (int) setting('auth.session.lifetime_minutes', $fallback);
        } catch (\Throwable) {
            return $fallback;
        }

        if ($minutes <= 0) {
            return $fallback;
        }

        config([
            'session.lifetime' => $minutes,
            // ولا تنتهي بإغلاق المتصفّح كذلك — وإلّا انكسرت القاعدة من الباب الآخر
            'session.expire_on_close' => false,
        ]);

        return $minutes;
    }
}
