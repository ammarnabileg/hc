<?php

namespace Database\Seeders;

use App\Models\MaintenanceWindow;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إعدادات وبيانات مجال «الأمان»: الصيانة (12.7-و-1) · الاسترجاع والتحقّق (2.3 · 2.5-ب) ·
 * منطقة الخطر (2.3) · احتواء الحسابات والانتحال (12.1) · رسالة noscript (2.1).
 *
 * القاعدة الذهبيّة 2.13: **ولا رقم ولا نصّ محروق في الكود** — كلّه هنا بقيمة
 * افتراضيّة قابلة للاسترجاع من لوحة الإدارة.
 */
class SecurityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->demo();

        Cache::forget('settings');
    }

    private function settings(): void
    {
        $rows = [
            // ---------------- وضع الصيانة العامّ (12.7-و-1) — ولا صيانة جزئيّة
            ['system.maintenance.exempt_ips', 'maintenance', 'IPs مستثناة من الصيانة', 'text', '127.0.0.1'],
            ['system.maintenance.exempt_paths', 'maintenance', 'مسارات مستثناة من الصيانة', 'json', '["login","logout","webhooks\/*","up","impersonate\/stop"]'],
            ['system.maintenance.page_title', 'maintenance', 'عنوان صفحة الصيانة', 'string', 'المنصّة تحت الصيانة'],
            ['system.maintenance.countdown_label', 'maintenance', 'عنوان العدّاد', 'string', 'باقي على الرجوع'],
            ['system.maintenance.refresh_hint', 'maintenance', 'سطر التحديث التلقائيّ', 'text', 'الصفحة بتحدّث نفسها كلّ {minutes} دقيقة — مش محتاج تعمل حاجة.'],
            ['system.maintenance.staff_link_label', 'maintenance', 'زرّ دخول فريق العمل', 'string', 'دخول فريق العمل'],
            ['system.maintenance.alert_title', 'maintenance', 'عنوان تنبيه تجاوز المدّة', 'string', 'الصيانة عدّت المدّة المعلَنة'],
            ['system.maintenance.alert_body', 'maintenance', 'نصّ تنبيه تجاوز المدّة', 'text', 'العدّاد وصل صفر والمنصّة لسّه مقفولة، والمستخدم بيشوف «قرّبنا ننتهي». مدّد المدّة أو ارفع الصيانة.'],

            // ---------------- التحقّق بالـOTP (2.5-ب)
            ['auth.otp.length', 'auth', 'عدد أرقام رمز التحقّق', 'number', '4'],
            ['auth.otp.resend_seconds', 'auth', 'مهلة إعادة إرسال الرمز (ثوانٍ)', 'number', '60'],
            ['auth.otp.max_attempts', 'auth', 'أقصى محاولات للرمز', 'number', '5'],
            ['auth.otp.ttl_minutes', 'auth', 'صلاحيّة الرموز المؤقّتة (دقائق)', 'number', '15'],
            ['auth.otp.title', 'auth', 'عنوان شاشة التحقّق', 'string', 'أكّد بريدك'],
            ['auth.otp.hint', 'auth', 'شرح شاشة التحقّق', 'text', 'هنبعت رمز من {length} أرقام على {email} — نتأكّد إنّه بريدك فعلًا.'],
            ['auth.otp.send_label', 'auth', 'زرّ الإرسال', 'string', 'إرسال'],
            ['auth.otp.resend_label', 'auth', 'زرّ إعادة الإرسال', 'string', 'إعادة إرسال الرمز'],
            ['auth.otp.confirm_label', 'auth', 'زرّ التأكيد', 'string', 'تأكيد'],
            ['auth.otp.code_label', 'auth', 'عنوان حقل الرمز', 'string', 'كود التحقّق'],
            ['auth.otp.back_label', 'auth', 'رابط تعديل البريد', 'string', 'ارجع عدّله'],
            ['auth.otp.wrong_email_hint', 'auth', 'سؤال تعديل البريد', 'string', 'البريد غلط؟'],
            ['auth.otp.sent_text', 'auth', 'رسالة بعد الإرسال', 'text', 'بعتنا الرمز على بريدك. بصّ في «غير الهامّ» كمان.'],
            ['auth.otp.wait_text', 'auth', 'رسالة انتظار إعادة الإرسال', 'text', 'استنّى {seconds} ثانية قبل ما تطلب تاني.'],
            ['auth.otp.error_missing', 'auth', 'خطأ: مافيش رمز', 'text', 'مابعتناش رمز لسّه — اضغط «إرسال» الأوّل.'],
            ['auth.otp.error_wrong', 'auth', 'خطأ: رمز غلط', 'text', 'الرمز مش مظبوط. راجع بريدك وجرّب تاني.'],
            ['auth.otp.error_locked', 'auth', 'خطأ: محاولات كتير', 'text', 'جرّبت كتير. استنّى شويّة واطلب رمزًا جديدًا.'],
            ['auth.otp.error_expired', 'auth', 'خطأ: رمز منتهي', 'text', 'الرمز ده انتهت صلاحيّته. اطلب رمزًا جديدًا.'],
            ['auth.otp.success', 'auth', 'رسالة نجاح التحقّق', 'string', 'اتأكّد ✓'],
            ['auth.otp.subject_register', 'auth', 'عنوان بريد رمز التسجيل', 'string', 'رمز تأكيد بريدك'],
            ['auth.otp.subject_delete', 'auth', 'عنوان بريد رمز الحذف', 'string', 'رمز تأكيد حذف حسابك'],
            ['auth.otp.subject_password', 'auth', 'عنوان بريد رمز الاسترجاع', 'string', 'رمز استرجاع كلمة السرّ'],
            ['auth.otp.body', 'auth', 'نصّ بريد الرمز', 'text', "رمز التأكيد بتاعك: {code}\nلو مش إنت اللي طلبته، اهمل الرسالة دي."],

            // ---------------- استرجاع كلمة السرّ (2.3)
            ['auth.password.min_length', 'auth', 'أدنى طول لكلمة السرّ', 'number', '8'],
            ['auth.password_reset.ttl_minutes', 'auth', 'صلاحيّة رابط الاسترجاع (دقائق)', 'number', '60'],
            ['auth.password_reset.request_title', 'auth', 'عنوان شاشة الطلب', 'string', 'نسيت كلمة السرّ؟'],
            ['auth.password_reset.request_hint', 'auth', 'شرح شاشة الطلب', 'text', 'اكتب بريدك وهنبعتلك رابط ورمز — أيّهما أسهل عليك.'],
            ['auth.password_reset.request_action', 'auth', 'زرّ الطلب', 'string', 'ابعتلي'],
            ['auth.password_reset.neutral_message', 'auth', 'الردّ المحايد بعد الطلب', 'text', 'لو البريد ده مسجّل عندنا، هتلاقي رسالة فيها رابط ورمز خلال دقايق. بصّ في «غير الهامّ» كمان.'],
            ['auth.password_reset.sent_title', 'auth', 'عنوان شاشة الإرسال', 'string', 'بصّ في بريدك'],
            ['auth.password_reset.sent_hint', 'auth', 'شرح شاشة الإرسال', 'text', 'لو {email} مسجّل عندنا، هتلاقي رابط ورمز. صالحين {minutes} دقيقة.'],
            ['auth.password_reset.code_label', 'auth', 'عنوان حقل الرمز', 'string', 'أو اكتب الرمز اللي وصلك'],
            ['auth.password_reset.code_action', 'auth', 'زرّ المتابعة بالرمز', 'string', 'كمّل بالرمز'],
            ['auth.password_reset.resend_label', 'auth', 'زرّ إعادة الإرسال', 'string', 'ابعت تاني'],
            ['auth.password_reset.reset_title', 'auth', 'عنوان شاشة التعيين', 'string', 'اختار كلمة سرّ جديدة'],
            ['auth.password_reset.reset_hint', 'auth', 'شرح شاشة التعيين', 'text', '{min} خانات على الأقلّ. وهنقفل كلّ الجلسات القديمة بعد التغيير.'],
            ['auth.password_reset.reset_action', 'auth', 'زرّ التعيين', 'string', 'غيّرها'],
            ['auth.password_reset.expired_text', 'auth', 'رسالة انتهاء الرابط', 'text', 'الرابط ده انتهت صلاحيّته. اطلب واحدًا جديدًا — بياخد ثانية.'],
            ['auth.password_reset.done_text', 'auth', 'رسالة نجاح التغيير', 'text', 'كلمة السرّ اتغيّرت ✓ — ادخل بيها دلوقتي. وقفلنا كلّ الجلسات القديمة للأمان.'],
            ['auth.password_reset.mail_subject', 'auth', 'عنوان بريد الاسترجاع', 'string', 'تغيير كلمة السرّ'],
            ['auth.password_reset.mail_body', 'auth', 'نصّ بريد الاسترجاع', 'text', "أهلًا {name}،\nده رابط تغيير كلمة السرّ: {url}\nالرابط صالح {minutes} دقيقة. لو مش إنت اللي طلبت، اهمل الرسالة."],

            // ---------------- منطقة الخطر: حذف الحساب (2.3)
            ['account.delete.badge', 'account', 'شارة منطقة الخطر', 'string', 'منطقة الخطر'],
            ['account.delete.title', 'account', 'عنوان حذف الحساب', 'string', 'حذف الحساب'],
            ['account.delete.intro', 'account', 'مقدّمة حذف الحساب', 'text', 'ده قرار كبير — اقرا الأوّل بيحصل إيه لبياناتك:'],
            ['account.delete.grace_days', 'account', 'مهلة التراجع (أيّام)', 'number', '30'],
            ['account.delete.grace_text', 'account', 'نصّ مهلة التراجع', 'text', 'عندك {days} يوم تقدر ترجع فيهم: كلّم الدعم وهنرجّع حسابك زيّ ما هو.'],
            ['account.delete.code_action', 'account', 'زرّ طلب الرمز', 'string', 'ابعتلي رمز التأكيد'],
            ['account.delete.code_placeholder', 'account', 'نصّ حقل الرمز', 'string', 'الرمز'],
            ['account.delete.action', 'account', 'زرّ الحذف', 'string', 'احذف حسابي'],
            ['account.delete.confirm_text', 'account', 'سؤال التأكيد الأخير', 'text', 'متأكّد؟ الحساب هيتقفل دلوقتي.'],
            ['account.delete.data_notes', 'account', 'ما يحدث للبيانات', 'json', json_encode([
                'بروفايلك وبياناتك الشخصيّة هتتشال من كلّ الشاشات فورًا.',
                'شهاداتك الصادرة هتفضل قابلة للتحقّق برقمها — دي حقّ الجهة اللي استلمتها.',
                'معاملات المحفظة والمشتريات بتفضل في السجلّ الماليّ بالقانون، بلا اسمك.',
                'مساهماتك في التطوّع بتفضل باسم «عضو سابق» علشان شغل الفريق ما يتكسرش.',
                'رصيدك الحاليّ بيسقط ومش هيرجع لو رجعت تاني.',
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- احتواء الحسابات والانتحال (12.1)
            ['admin.moderation.badge', 'admin', 'شارة قسم الاحتواء', 'string', 'احتواء الحساب'],
            ['admin.moderation.title', 'admin', 'عنوان قسم الاحتواء', 'string', 'أدوات الاحتواء'],
            ['admin.moderation.hint', 'admin', 'شرح قسم الاحتواء', 'text', 'كلّ فعل هنا بيتسجّل في سجلّ التدقيق باسمك ووقته.'],
            ['admin.moderation.link_hint', 'admin', 'شرح رابط كلمة السرّ', 'text', 'الرابط ده بيظهر مرّة واحدة — انسخه دلوقتي.'],
            ['admin.moderation.suspend_default_days', 'admin', 'مدّة التعليق الافتراضيّة (أيّام)', 'number', '7'],
            ['admin.moderation.suspend_max_days', 'admin', 'أقصى مدّة تعليق (أيّام)', 'number', '90'],
            ['admin.moderation.reasons', 'admin', 'أسباب الاحتواء الجاهزة', 'json', json_encode([
                'إساءة لمستخدم تاني',
                'محتوى مخالف',
                'محاولة اختراق أو تلاعب',
                'حساب مكرّر',
            ], JSON_UNESCAPED_UNICODE)],
            ['impersonation.banner_text', 'admin', 'نصّ شريط الانتحال', 'text', 'إنت بتتصفّح كـ{target} (#{code}) — أيّ فعل هنا محسوب على {actor}.'],
            ['impersonation.stop_label', 'admin', 'زرّ العودة من الانتحال', 'string', 'ارجع لحسابي'],

            // ---------------- التحسين التدريجيّ (2.1)
            ['ux.noscript.title', 'ux', 'عنوان رسالة noscript', 'string', 'الجافاسكربت مقفول في متصفّحك'],
            ['ux.noscript.body', 'ux', 'شرح رسالة noscript', 'text', 'الصفحة شغّالة وتقدر تقرأ وتتنقّل عادي، لكن الحفظ التلقائيّ والعدّادات والبوب-أبات محتاجة الجافاسكربت. تفعيله بياخد أقلّ من دقيقة:'],
            ['ux.noscript.steps', 'ux', 'خطوات تفعيل الجافاسكربت', 'json', json_encode([
                'افتح إعدادات المتصفّح من القائمة (⋮ أو ⚙) فوق على اليمين.',
                'ادخل على «الخصوصيّة والأمان» ثمّ «إعدادات المواقع».',
                'اختار «جافاسكربت» وخلّيه «مسموح».',
                'ارجع للصفحة دي واعمل تحديث (F5 أو سهم التحديث).',
            ], JSON_UNESCAPED_UNICODE)],
            ['ux.noscript.footer', 'ux', 'ذيل رسالة noscript', 'text', 'لو المتصفّح عندك مختلف، دوّر على كلمة «JavaScript» جوّه الإعدادات — الخطوة واحدة في كلّ المتصفّحات.'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        $this->command?->info('إعدادات الأمان: '.count($rows));
    }

    /** بيانات تجريبيّة عربيّة واقعيّة: فترة صيانة منتهية + حسابان محتوَيان */
    private function demo(): void
    {
        if (! MaintenanceWindow::query()->exists()) {
            MaintenanceWindow::create([
                'message' => 'بنحدّث محرّك الشهادات — هنرجع خلال ساعتين.',
                'planned_hours' => 2,
                'started_at' => now()->subDays(9)->setTime(2, 0),
                'expected_end_at' => now()->subDays(9)->setTime(4, 0),
                'ended_at' => now()->subDays(9)->setTime(3, 40),
                'deadlines_recomputed' => true,
            ]);
        }

        $banned = User::query()->where('code', 'SEC00001')->first();

        if (! $banned) {
            $banned = User::create([
                'name' => 'مصطفى عبد الحليم',
                'email' => 'mostafa.banned@demo.local',
                'password' => 'secret-password',
                'code' => 'SEC00001',
                'status' => 'banned',
                'containment_reason' => 'إساءة لمستخدم تاني',
                'contained_at' => now()->subDays(4),
            ]);
        }

        if (! User::query()->where('code', 'SEC00002')->exists()) {
            User::create([
                'name' => 'نورهان سعيد',
                'email' => 'nourhan.suspended@demo.local',
                'password' => 'secret-password',
                'code' => 'SEC00002',
                'status' => 'suspended',
                'containment_reason' => 'محتوى مخالف',
                'suspended_until' => now()->addDays(3),
                'contained_at' => now()->subDays(4),
            ]);
        }

        // سطر تدقيق واقعيّ للحظر — الاحتواء بلا أثرٍ مكتوب بابُ ظلم (12.1)
        DB::table('audit_logs')->insertOrIgnore([
            'user_id' => null,
            'action' => 'user.ban',
            'auditable_type' => $banned->getMorphClass(),
            'auditable_id' => $banned->getKey(),
            'old_values' => json_encode(['status' => 'active'], JSON_UNESCAPED_UNICODE),
            'new_values' => json_encode(['status' => 'banned'], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
    }
}
