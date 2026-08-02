<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * القاعدة الذهبيّة 2.13: لكلّ ميزة إعدادات كاملة في لوحة الإدارة (No Hard-coding).
 * نمط المفتاح: «المجال.الميزة.المفتاح» — ولكلّ إعداد قيمة افتراضيّة قابلة للاسترجاع (Reset).
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ---------------- الحساب والتفعيل (2.5-د)
            ['accounts.activation.is_free', 'accounts', 'تفعيل الحساب مجّانيّ', 'bool', '1'],
            ['accounts.activation.requires_admin_approval', 'accounts', 'التفعيل باعتماد إداريّ', 'bool', '1'],
            ['accounts.code.prefix', 'accounts', 'بادئة كود المستخدم', 'string', 'U'],

            // ---------------- البساطة أوّلًا (2.15)
            ['ux.simple_mode.default_on', 'ux', 'الوضع المبسّط مفعَّل افتراضيًّا', 'bool', '1'],
            ['ux.kpi.max_cards', 'ux', 'أقصى عدد كروت KPI', 'number', '4'],
            ['ux.filters.max_visible', 'ux', 'أقصى فلاتر ظاهرة', 'number', '3'],
            ['ux.tables.default_columns', 'ux', 'عدد الأعمدة الافتراضيّة', 'number', '6'],
            ['ux.forms.max_fields_before_stepper', 'ux', 'حدّ حقول الفورم قبل التقسيم لخطوات', 'number', '7'],
            ['ux.lists.default_range_days', 'ux', 'المدى الزمنيّ الافتراضيّ للقوائم (أيّام)', 'number', '30'],
            ['ux.undo.seconds', 'ux', 'مدّة التراجع (ثوانٍ)', 'number', '5'],
            ['ux.first_time.enabled_screens', 'ux', 'شاشات «أوّل مرّة» المفعَّلة', 'json', '[]'],

            // ---------------- الاحتفالات (2.14)
            ['celebrations.peak.daily_cap', 'celebrations', 'الحدّ اليوميّ لاحتفالات الذروة', 'number', '3'],
            ['celebrations.sound.enabled', 'celebrations', 'تفعيل الصوت (والأنيميشن دائم)', 'bool', '1'],

            // ---------------- طبقة الإحساس (2.17)
            ['feel.counter.animate_numbers', 'feel', 'عدّاد تصاعديّ للأرقام', 'bool', '1'],
            ['feel.haptics.enabled', 'feel', 'اهتزاز خفيف على الموبايل', 'bool', '1'],
            ['feel.signature_sound.path', 'feel', 'صوت التوقيع', 'media', ''],

            // ---------------- درجة الالتزام (13.4-ن)
            ['rep.reset.day_of_month', 'rep', 'يوم التصفير الشهريّ', 'number', '1'],
            ['rep.reset.hour_cairo', 'rep', 'ساعة التصفير بتوقيت القاهرة', 'number', '5'],
            ['rep.behavior.monthly_cap_per_grantor', 'rep', 'سقف معاملات السلوك شهريًّا للمانح', 'number', '5'],
            ['rep.objection.window_days', 'rep', 'مهلة الاعتراض (أيّام)', 'number', '5'],

            // ---------------- دورة العمل (23)
            ['workflow.escalation.window_hours', 'workflow', 'نافذة القرار لكلّ مستوى (ساعات)', 'number', '24'],
            ['workflow.escalation.top_window_hours', 'workflow', 'نافذة السقف (ساعات)', 'number', '48'],
            ['workflow.activity_window.start', 'workflow', 'بداية نافذة النشاط', 'string', '09:00'],
            ['workflow.activity_window.end', 'workflow', 'نهاية نافذة النشاط', 'string', '00:00'],
            ['workflow.contribution.owner_review_hours', 'workflow', 'مهلة مراجعة المالك للمساهم (ساعات)', 'number', '24'],
            ['workflow.checkpoint.response_hours', 'workflow', 'مهلة الردّ على نقطة تفتيش (ساعات)', 'number', '2'],
            ['workflow.blocked.max_days', 'workflow', 'أقصى مدّة تعثّر (أيّام)', 'number', '3'],
            ['workflow.vxp.parent_min_share_percent', 'workflow', 'أدنى شريحة محفوظة للأب (%)', 'number', '10'],

            // ---------------- الأوفبوردنج والعودة (13.4-س · 13.4-ق)
            ['offboarding.inactivity.alert_days', 'offboarding', 'أيّام الخمول قبل التنبيه', 'number', '21'],
            ['offboarding.notice_days', 'offboarding', 'مهلة إشعار تسليم العمل (أيّام)', 'number', '7'],
            ['offboarding.cooldown.resignation_days', 'offboarding', 'تبريد الاستقالة الطوعيّة (أيّام)', 'number', '30'],
            ['offboarding.cooldown.thresholds_days', 'offboarding', 'تبريد الخروج بالعتبات (أيّام)', 'number', '90'],
            ['reentry.exam.required', 'offboarding', 'إلزام العائد بإعادة الامتحان', 'bool', '1'],

            // ---------------- شهادات التطوّع والبطاقة (13.4-ع · 13.4-ر)
            ['volunteer_cert.min_days_in_position', 'volunteer', 'أدنى مدّة في البوزشن (أيّام)', 'number', '30'],
            ['volunteer_card.enabled', 'volunteer', 'تفعيل بطاقة المتطوّع الرقميّة', 'bool', '1'],
            ['volunteer_card.show_rep', 'volunteer', 'إظهار Rep على البطاقة العامّة', 'bool', '0'],
            ['volunteer.honorary.enabled', 'volunteer', 'إظهار العنصر الشرفيّ «أخوكم»', 'bool', '1'],
            ['volunteer.honorary.label_ar', 'volunteer', 'وصف العنصر الشرفيّ', 'string', 'أخوكم'],

            // ---------------- التقدير (13.4-ي)
            ['kudos.daily_limit', 'kudos', 'حدّ Kudos اليوميّ', 'number', '2'],
            ['kudos.weekly_people_limit', 'kudos', 'حدّ الأشخاص أسبوعيًّا', 'number', '7'],
            ['kudos.vxp_value', 'kudos', 'قيمة Kudos بالـVXP', 'number', '20'],

            // ---------------- المتجر والاسترجاع (19.4 · 19.5)
            ['store.refund.policy_text', 'store', 'نصّ سياسة الاسترجاع (HTML أو نصّ)', 'text', 'لا يوجد استرجاع نقديّ للمدفوعات، ويبقى رصيدك في محفظتك تشتري به ما تشاء من الموقع.'],
            ['topup.default_tab', 'store', 'التاب الافتراضيّ للشحن', 'string', 'manual'],
            ['topup.manual.enabled', 'store', 'تفعيل التحويل اليدويّ', 'bool', '1'],
            ['topup.gateway.enabled', 'store', 'تفعيل بوّابة الدفع', 'bool', '1'],
            ['topup.gateway.provider', 'store', 'مزوّد البوّابة', 'string', 'fawaterk'],
            ['topup.gateway.sandbox', 'store', 'وضع الاختبار (Sandbox)', 'bool', '1'],
            ['topup.gateway.api_key', 'store', 'مفتاح API', 'string', ''],
            ['topup.gateway.vendor_key', 'store', 'مفتاح التاجر (للتحقّق من الهاش)', 'string', ''],
            ['topup.receipt.max_size_kb', 'store', 'أقصى حجم للإيصال (KB)', 'number', '4096'],

            // ---------------- الريفيرال وحلقات النموّ (7.6 · 21.1)
            ['referral.commission_percent', 'growth', 'نسبة عمولة الإحالة (%)', 'number', '7'],
            ['referral.welcome_tickets', 'growth', 'تذاكر ترحيب للمدعوّ', 'number', '1'],
            ['growth.profile_completion.reward_tickets', 'growth', 'مكافأة إكمال الملفّ (تذاكر)', 'number', '3'],
            ['growth.seo.index_certificates', 'growth', 'فهرسة صفحات الشهادات', 'bool', '1'],
            ['growth.seo.index_courses', 'growth', 'فهرسة صفحات التدريبات', 'bool', '1'],
            ['growth.linkedin.share_text', 'growth', 'نصّ منشور لينكدإن', 'text', 'أتممتُ [المسار] وحصلتُ على شهادة معتمدة.'],

            // ---------------- الإعلان المدفوع (21.3)
            ['ads.tracking.enabled', 'ads', 'تفعيل التتبّع كلّيًّا', 'bool', '0'],
            ['ads.pixel.meta_id', 'ads', 'معرّف بكسل Meta', 'string', ''],
            ['ads.pixel.google_id', 'ads', 'معرّف Google', 'string', ''],
            ['ads.capi.token', 'ads', 'توكن أحداث الخادم', 'string', ''],
            ['ads.consent.banner_text', 'ads', 'نصّ بانر الموافقة', 'text', 'نستخدم ملفّات تعريف الارتباط لتحسين تجربتك. تقدر تقبل أو ترفض.'],
            ['ads.best_user.rule', 'ads', 'تعريف «أفضل مستخدم»', 'json', '{"completed_course":true,"purchased":true,"returned":true}'],

            // ---------------- استوديو الصور (12.14)
            ['images.rate_limit_per_minute', 'images', 'حدّ التوليد في الدقيقة', 'number', '30'],
            ['images.cache_days', 'images', 'مدّة الكاش (أيّام)', 'number', '30'],
            ['images.watermark.show_logo', 'images', 'إظهار الشعار على الصور المستخرَجة', 'bool', '1'],
            ['images.watermark.show_date', 'images', 'إظهار تاريخ اللقطة', 'bool', '1'],

            // ---------------- النظام والصيانة (12.7)
            ['system.maintenance.enabled', 'system', 'وضع الصيانة العامّ', 'bool', '0'],
            ['system.maintenance.message', 'system', 'رسالة الصيانة', 'text', 'بنطوّر حاجة حلوة — هنرجع قريب.'],
            ['system.maintenance.freeze_deadlines', 'system', 'تجميد كلّ المهل أثناء الصيانة', 'bool', '1'],
            ['system.timezone', 'system', 'المنطقة الزمنيّة', 'string', 'Africa/Cairo'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
                'is_sensitive' => str_contains($key, 'api_key') || str_contains($key, 'vendor_key') || str_contains($key, 'token'),
                'is_owner_only' => str_contains($key, 'gateway.api_key') || str_contains($key, 'gateway.vendor_key'),
            ]);
        }

        $this->command?->info('الإعدادات: '.Setting::count());
    }
}
