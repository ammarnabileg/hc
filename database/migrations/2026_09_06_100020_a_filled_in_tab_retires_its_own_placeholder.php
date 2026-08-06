<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 🧩 المطوّرين — Webhooks: كلّ نصّ ورقم ظاهر في تاب Webhooks عبر `setting()`
 * لا محروقًا (2.13-أ)، **في هجرة لا سيدرٍ وحده** لأنّ الإنتاج لا يشغّل
 * السيدرات — نفس أسلوب هجرة إعدادات تاب API
 * (`2026_09_05_100020_a_locked_door_needs_labels_not_hardcoding.php`).
 *
 * أربع مجموعات:
 * 1) `developers.webhooks.*` — القيم التشغيليّة (12.15-د): تفعيل الصفحة ·
 *    عدد محاولات إعادة الإرسال وفتراتها · مهلة الاتّصال · مدّة الاحتفاظ بالسجلّ.
 * 2) `developers.webhooks.events.*` — تسميات كتالوج الأحداث المقفول
 *    (`WebhookEventCatalog::EVENT_KEYS`).
 * 3) `developers.admin.webhook_*` و`developers.admin.deliveries_*` — كلّ
 *    نصّ في شاشة تاب Webhooks (فورم التسجيل · جدول الويب-هوكس · سجلّ المحاولات).
 * 4) **تقاعد لافتتَي السقالة القديمة**: `webhooks_placeholder_title` و
 *    `webhooks_placeholder_body` كانتا نصّ «قريبًا» في تاب Webhooks الفارغ —
 *    التاب الآن مبنيّ بالكامل فلا قارئ لهما، فتُحذَفان كي لا يبقيا
 *    «مفتاحًا ميّتًا» (`php artisan settings:coverage --dead`).
 */
return new class extends Migration
{
    private const OBSOLETE_KEYS = [
        'developers.admin.webhooks_placeholder_title',
        'developers.admin.webhooks_placeholder_body',
    ];

    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // ---- 1) القيم التشغيليّة (12.15-د)
        ['developers.webhooks.enabled', 'developers', 'تفعيل صفحة/إرسال الويب-هوكس', 'bool', '1'],
        ['developers.webhooks.max_retries', 'developers', 'عدد محاولات إعادة إرسال الويب-هوك', 'number', '3'],
        ['developers.webhooks.retry_delays_minutes', 'developers', 'فترات تأخير إعادة المحاولة بالدقائق (مفصولة بفواصل)', 'string', '1,5,30'],
        ['developers.webhooks.timeout_seconds', 'developers', 'مهلة الاتّصال بوجهة الويب-هوك بالثواني', 'number', '8'],
        ['developers.webhooks.log_retention_count', 'developers', 'عدد محاولات الإرسال المحتفَظ بها في السجلّ', 'number', '100'],

        // ---- 2) كتالوج الأحداث المقفول (WebhookEventCatalog::EVENT_KEYS)
        ['developers.webhooks.events.user_registered', 'developers', 'تسمية حدث: user.registered', 'string', 'مستخدم جديد سجّل حسابًا'],
        ['developers.webhooks.events.certificate_issued', 'developers', 'تسمية حدث: certificate.issued', 'string', 'شهادة صدرت'],
        ['developers.webhooks.events.badge_awarded', 'developers', 'تسمية حدث: badge.awarded', 'string', 'شارة مُنِحت'],
        ['developers.webhooks.events.membership_activated', 'developers', 'تسمية حدث: membership.activated', 'string', 'حساب فُعِّل'],
        ['developers.webhooks.events.order_paid', 'developers', 'تسمية حدث: order.paid', 'string', 'طلب دُفع'],

        // ---- 3) شاشة الأدمن — بوب-أب السرّ الصريح
        ['developers.admin.webhook_secret_title', 'developers', 'عنوان بوب-أب سرّ الويب-هوك الكامل', 'string', 'احفظ سرّ الويب-هوك الآن'],
        ['developers.admin.webhook_secret_warning', 'developers', 'تحذير سرّ الويب-هوك لن يظهر ثانيةً', 'string', 'هذا هو السرّ الكامل — لن يظهر ثانيةً بعد إغلاق هذه الرسالة. استخدمه للتحقّق من توقيع HMAC في رأس X-Webhook-Signature.'],

        // ---- فورم ويب-هوك جديد
        ['developers.admin.webhook_form_title', 'developers', 'عنوان فورم ويب-هوك جديد', 'string', 'ويب-هوك جديد'],
        ['developers.admin.field_url', 'developers', 'حقل: رابط الاستقبال', 'string', 'رابط الاستقبال (URL)'],
        ['developers.admin.field_events', 'developers', 'حقل: الأحداث المشترَك فيها', 'string', 'الأحداث المشترَك فيها'],
        ['developers.admin.webhook_save_cta', 'developers', 'زرّ تسجيل الويب-هوك', 'string', 'تسجيل الويب-هوك'],
        ['developers.admin.webhook_created_ok', 'developers', 'رسالة تسجيل ويب-هوك', 'string', 'اتسجّل الويب-هوك ✓'],
        ['developers.admin.webhook_rotated_ok', 'developers', 'رسالة تدوير سرّ ويب-هوك', 'string', 'اتدوّر سرّ الويب-هوك ✓'],
        ['developers.admin.webhook_paused_ok', 'developers', 'رسالة إيقاف ويب-هوك', 'string', 'اتوقّف الويب-هوك ✓'],
        ['developers.admin.webhook_resumed_ok', 'developers', 'رسالة استئناف ويب-هوك', 'string', 'اشتغل الويب-هوك تاني ✓'],
        ['developers.admin.webhook_deleted_ok', 'developers', 'رسالة حذف ويب-هوك', 'string', 'اتحذف الويب-هوك ✓'],
        ['developers.admin.webhook_retry_ok', 'developers', 'رسالة إعادة إرسال محاولة', 'string', 'هتتبعت المحاولة تاني ✓'],
        ['developers.admin.webhook_test_ok', 'developers', 'رسالة إرسال اختبار', 'string', 'اتبعتت حمولة تجريبيّة ✓'],

        // ---- جدول الويب-هوكس
        ['developers.admin.webhooks_title', 'developers', 'عنوان جدول الويب-هوكس', 'string', 'الويب-هوكس المسجَّلة'],
        ['developers.admin.webhook_col_url', 'developers', 'عمود: الرابط', 'string', 'الرابط'],
        ['developers.admin.webhook_col_last_run', 'developers', 'عمود: آخر تشغيل ونتيجته', 'string', 'آخر تشغيل ونتيجته'],
        ['developers.admin.webhooks_empty', 'developers', 'الحالة الفارغة لجدول الويب-هوكس', 'string', 'لا ويب-هوكس بعد — سجّل أوّل ويب-هوك.'],
        ['developers.admin.webhook_status_paused', 'developers', 'حالة الويب-هوك: موقوف', 'string', 'موقوف'],
        ['developers.admin.webhook_never_triggered', 'developers', 'نصّ لم يُشغَّل بعد', 'string', 'لم يُشغَّل بعد'],
        ['developers.admin.webhook_last_code', 'developers', 'تسمية كود الردّ الأخير', 'string', 'كود الردّ'],
        ['developers.admin.webhook_test_cta', 'developers', 'زرّ اختبار الويب-هوك', 'string', 'اختبار'],
        ['developers.admin.webhook_pause_cta', 'developers', 'زرّ إيقاف الويب-هوك', 'string', 'إيقاف'],
        ['developers.admin.webhook_resume_cta', 'developers', 'زرّ استئناف الويب-هوك', 'string', 'استئناف'],
        ['developers.admin.webhook_rotate_confirm', 'developers', 'تأكيد تدوير سرّ الويب-هوك', 'string', 'تدوير السرّ يُبطل القديم فورًا — تأكيد؟'],
        ['developers.admin.webhook_delete_confirm', 'developers', 'تأكيد حذف الويب-هوك', 'string', 'حذف الويب-هوك نهائيّ — تأكيد؟'],

        // ---- سجلّ محاولات الإرسال (Deliveries)
        ['developers.admin.deliveries_title', 'developers', 'عنوان سجلّ محاولات الإرسال', 'string', 'سجلّ محاولات الإرسال (آخر 100)'],
        ['developers.admin.deliveries_empty', 'developers', 'الحالة الفارغة لسجلّ المحاولات', 'string', 'لا محاولات إرسال بعد.'],
        ['developers.admin.deliveries_col_event', 'developers', 'عمود سجلّ المحاولات: الحدث', 'string', 'الحدث'],
        ['developers.admin.deliveries_col_attempts', 'developers', 'عمود سجلّ المحاولات: عدد المحاولات', 'string', 'عدد المحاولات'],
        ['developers.admin.deliveries_col_payload', 'developers', 'عمود سجلّ المحاولات: الحمولة والردّ', 'string', 'الحمولة والردّ'],
        ['developers.admin.deliveries_preview_cta', 'developers', 'زرّ معاينة الحمولة والردّ', 'string', 'معاينة'],
        ['developers.admin.deliveries_payload_label', 'developers', 'تسمية الحمولة في المعاينة', 'string', 'الحمولة'],
        ['developers.admin.deliveries_response_label', 'developers', 'تسمية الردّ الخام في المعاينة', 'string', 'الردّ الخام'],
        ['developers.admin.deliveries_retry_cta', 'developers', 'زرّ إعادة إرسال محاولة', 'string', 'إعادة إرسال'],
        ['developers.admin.delivery_status_success', 'developers', 'حالة المحاولة: نجحت', 'string', 'نجحت'],
        ['developers.admin.delivery_status_failed', 'developers', 'حالة المحاولة: فشلت وبانتظار إعادة', 'string', 'فشلت — بانتظار إعادة'],
        ['developers.admin.delivery_status_exhausted', 'developers', 'حالة المحاولة: استُنفدت', 'string', 'استُنفدت'],
        ['developers.admin.delivery_status_pending', 'developers', 'حالة المحاولة: منتظرة', 'string', 'منتظرة'],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('settings')->whereIn('key', self::OBSOLETE_KEYS)->delete();

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

        $now = now();

        foreach ([
            ['developers.admin.webhooks_placeholder_title', 'developers', 'عنوان بديل تاب Webhooks (قيد البناء)', 'string', 'Webhooks — قريبًا'],
            ['developers.admin.webhooks_placeholder_body', 'developers', 'نصّ بديل تاب Webhooks (قيد البناء)', 'string', 'قيد البناء — سيُضاف هنا تسجيل الويب-هوكس وكتالوج الأحداث وسجلّ المحاولات.'],
        ] as [$key, $group, $label, $type, $default]) {
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
};
