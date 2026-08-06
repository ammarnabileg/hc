<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 🧩 المطوّرين — API: كلّ نصّ ورقم ظاهر في الشاشة عبر `setting()` لا محروقًا
 * (2.13-أ)، **في هجرة لا سيدرٍ وحده** لأنّ الإنتاج لا يشغّل السيدرات — نفس
 * أسلوب `2026_09_01_100010_the_placement_test_bank_gets_a_screen.php`.
 *
 * ثلاث مجموعات من الصفوف:
 * 1) `developers.api.*` — القيم التشغيليّة الثلاث المنصوصة في 12.15-د
 *    (تفعيل/تعطيل الصفحة · الحدّ الافتراضيّ للمعدّل · مدّة الاحتفاظ بالسجلّ).
 * 2) `developers.admin.*` و`developers.scopes.*` و`developers.api.doc.*` —
 *    كلّ نصّ في شاشة الأدمن وكتالوج التوثيق (12.15-أ).
 * 3) `nav.admin.*` — لافتات بند «🧩 المطوّرين» في سايد بار الإدارة (12.0)،
 *    بنفس مجموعة بقيّة لافتات السايد بار (`nav`) القابلة للتعديل (2.13-ب).
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // ---- 1) القيم التشغيليّة (12.15-د)
        ['developers.api.enabled', 'developers', 'تفعيل صفحة/واجهة الـAPI', 'bool', '1'],
        ['developers.api.default_rate_limit', 'developers', 'الحدّ الافتراضيّ لطلبات الـAPI بالدقيقة لكلّ مفتاح', 'number', '60'],
        ['developers.api.log_retention_count', 'developers', 'عدد سجلّات الاستخدام المحتفَظ بها لكلّ مفتاح', 'number', '100'],

        // ---- 2) شاشة الأدمن — عناوين وتابات
        ['developers.admin.tab_api', 'developers', 'عنوان تاب API', 'string', 'API'],
        ['developers.admin.tab_webhooks', 'developers', 'عنوان تاب Webhooks', 'string', 'Webhooks'],
        ['developers.admin.page_title', 'developers', 'عنوان شاشة المطوّرين', 'string', 'المطوّرين'],
        ['developers.admin.page_subtitle', 'developers', 'سطر شرح شاشة المطوّرين', 'string', 'مفاتيح الربط بين المنصّة والمواقع والأنظمة الخارجيّة.'],
        ['developers.admin.webhooks_placeholder_title', 'developers', 'عنوان بديل تاب Webhooks (قيد البناء)', 'string', 'Webhooks — قريبًا'],
        ['developers.admin.webhooks_placeholder_body', 'developers', 'نصّ بديل تاب Webhooks (قيد البناء)', 'string', 'قيد البناء — سيُضاف هنا تسجيل الويب-هوكس وكتالوج الأحداث وسجلّ المحاولات.'],

        // ---- جدول المفاتيح
        ['developers.admin.keys_title', 'developers', 'عنوان جدول مفاتيح الـAPI', 'string', 'مفاتيح الـAPI'],
        ['developers.admin.col_name', 'developers', 'عمود: الاسم', 'string', 'الاسم'],
        ['developers.admin.col_prefix', 'developers', 'عمود: البادئة الظاهرة', 'string', 'البادئة'],
        ['developers.admin.col_scopes', 'developers', 'عمود: الصلاحيّات', 'string', 'الصلاحيّات (Scopes)'],
        ['developers.admin.col_last_used', 'developers', 'عمود: آخر استخدام', 'string', 'آخر استخدام'],
        ['developers.admin.col_expires', 'developers', 'عمود: تاريخ الانتهاء', 'string', 'تاريخ الانتهاء'],
        ['developers.admin.col_rate_limit', 'developers', 'عمود: حدّ المعدّل', 'string', 'حدّ المعدّل/دقيقة'],
        ['developers.admin.col_status', 'developers', 'عمود: الحالة', 'string', 'الحالة'],
        ['developers.admin.col_actions', 'developers', 'عمود: إجراءات', 'string', 'إجراءات'],
        ['developers.admin.status_active', 'developers', 'حالة المفتاح: فعّال', 'string', 'فعّال'],
        ['developers.admin.status_revoked', 'developers', 'حالة المفتاح: مُبطَل', 'string', 'مُبطَل'],
        ['developers.admin.empty_keys', 'developers', 'الحالة الفارغة لجدول المفاتيح', 'string', 'لا مفاتيح بعد — أنشئ أوّل مفتاح API.'],
        ['developers.admin.no_expiry', 'developers', 'نصّ بلا تاريخ انتهاء', 'string', 'بلا انتهاء'],
        ['developers.admin.never_used', 'developers', 'نصّ لم يُستخدَم بعد', 'string', 'لم يُستخدَم بعد'],
        ['developers.admin.inherits_default', 'developers', 'نصّ حدّ معدّل موروث من الإعداد العامّ', 'string', 'الحدّ العامّ'],

        // ---- فورم مفتاح جديد
        ['developers.admin.new_key_cta', 'developers', 'زرّ مفتاح API جديد', 'string', '+ مفتاح جديد'],
        ['developers.admin.form_title', 'developers', 'عنوان فورم مفتاح API جديد', 'string', 'مفتاح API جديد'],
        ['developers.admin.field_name', 'developers', 'حقل: اسم المفتاح', 'string', 'اسم وصفيّ'],
        ['developers.admin.field_scopes', 'developers', 'حقل: الصلاحيّات', 'string', 'الصلاحيّات (Scopes)'],
        ['developers.admin.field_expires', 'developers', 'حقل: تاريخ الانتهاء', 'string', 'تاريخ الانتهاء (اختياريّ)'],
        ['developers.admin.field_rate_limit', 'developers', 'حقل: حدّ المعدّل المخصّص', 'string', 'حدّ الطلبات بالدقيقة (اختياريّ — فارغ يرث الحدّ العامّ)'],
        ['developers.admin.save_cta', 'developers', 'زرّ إنشاء المفتاح', 'string', 'إنشاء المفتاح'],
        ['developers.admin.rotate_cta', 'developers', 'زرّ تدوير المفتاح', 'string', 'تدوير'],
        ['developers.admin.revoke_cta', 'developers', 'زرّ إبطال المفتاح', 'string', 'إبطال'],
        ['developers.admin.rotate_confirm', 'developers', 'تأكيد تدوير المفتاح', 'string', 'تدوير المفتاح يُبطل القديم فورًا ويصدر مفتاحًا جديدًا بنفس الاسم والصلاحيّات — تأكيد؟'],
        ['developers.admin.revoke_confirm', 'developers', 'تأكيد إبطال المفتاح', 'string', 'إبطال المفتاح فوريّ ولا رجعة فيه — تأكيد؟'],

        // ---- بوب-أب المفتاح الصريح (مرّة واحدة)
        ['developers.admin.plain_key_title', 'developers', 'عنوان بوب-أب المفتاح الكامل', 'string', 'احفظ المفتاح الآن'],
        ['developers.admin.plain_key_warning', 'developers', 'تحذير المفتاح الكامل لن يظهر ثانيةً', 'string', 'هذا هو المفتاح الكامل — لن يظهر ثانيةً بعد إغلاق هذه الرسالة.'],
        ['developers.admin.plain_key_copy_cta', 'developers', 'زرّ نسخ المفتاح', 'string', 'نسخ'],
        ['developers.admin.created_ok', 'developers', 'رسالة إنشاء مفتاح', 'string', 'اتنشأ المفتاح ✓'],
        ['developers.admin.rotated_ok', 'developers', 'رسالة تدوير مفتاح', 'string', 'اتدوّر المفتاح ✓'],
        ['developers.admin.revoked_ok', 'developers', 'رسالة إبطال مفتاح', 'string', 'اتبطَّل المفتاح ✓'],

        // ---- سجلّ الاستخدام
        ['developers.admin.usage_title', 'developers', 'عنوان سجلّ الاستخدام', 'string', 'سجلّ الاستخدام (آخر 100 طلب)'],
        ['developers.admin.usage_col_time', 'developers', 'عمود سجلّ الاستخدام: الوقت', 'string', 'الوقت'],
        ['developers.admin.usage_col_method', 'developers', 'عمود سجلّ الاستخدام: الطريقة', 'string', 'الطريقة'],
        ['developers.admin.usage_col_path', 'developers', 'عمود سجلّ الاستخدام: المسار', 'string', 'المسار'],
        ['developers.admin.usage_col_status', 'developers', 'عمود سجلّ الاستخدام: كود الردّ', 'string', 'كود الردّ'],
        ['developers.admin.usage_col_ip', 'developers', 'عمود سجلّ الاستخدام: IP', 'string', 'IP'],
        ['developers.admin.usage_col_duration', 'developers', 'عمود سجلّ الاستخدام: زمن الاستجابة', 'string', 'زمن الاستجابة'],
        ['developers.admin.usage_empty', 'developers', 'الحالة الفارغة لسجلّ الاستخدام', 'string', 'لا طلبات مسجَّلة بعد على هذا المفتاح.'],

        // ---- كتالوج نقاط النهاية (توثيق مرجعيّ — 12.15-أ)
        ['developers.admin.catalog_title', 'developers', 'عنوان كتالوج نقاط النهاية', 'string', 'كتالوج نقاط النهاية (Endpoints)'],
        ['developers.admin.catalog_col_method', 'developers', 'عمود الكتالوج: الطريقة', 'string', 'الطريقة'],
        ['developers.admin.catalog_col_path', 'developers', 'عمود الكتالوج: المسار', 'string', 'المسار'],
        ['developers.admin.catalog_col_scope', 'developers', 'عمود الكتالوج: الـScope المطلوب', 'string', 'الـScope المطلوب'],
        ['developers.admin.catalog_col_description', 'developers', 'عمود الكتالوج: الوصف', 'string', 'الوصف'],
        ['developers.admin.catalog_no_scope', 'developers', 'نصّ بلا Scope مطلوب في الكتالوج', 'string', 'بلا Scope — يكفي مفتاحٌ صالح'],

        // ---- تسميات الـScopes المقفولة (ApiKeyService::SCOPES)
        ['developers.scopes.read_courses', 'developers', 'تسمية Scope: read:courses', 'string', 'قراءة قائمة التدريبات المنشورة'],
        ['developers.scopes.read_certificates', 'developers', 'تسمية Scope: read:certificates', 'string', 'التحقّق من حالة شهادة'],
        ['developers.scopes.read_users_basic', 'developers', 'تسمية Scope: read:users_basic', 'string', 'قراءة بيانات مستخدم أساسيّة'],

        // ---- وصف نقاط النهاية الثلاث الحقيقيّة (ApiEndpointCatalog::ENDPOINTS)
        ['developers.api.doc.ping', 'developers', 'وصف نقطة /api/v1/ping', 'string', 'فحص صلاحيّة المفتاح — يردّ OK باسم المفتاح.'],
        ['developers.api.doc.courses', 'developers', 'وصف نقطة /api/v1/courses', 'string', 'قائمة التدريبات المنشورة (id · name_ar · slug) بصفحات.'],
        ['developers.api.doc.certificates_verify', 'developers', 'وصف نقطة /api/v1/certificates/{code}/verify', 'string', 'حالة شهادة بكودها: سارية/منتهية/مُلغاة/غير موجودة.'],

        // ---- لافتات بند السايد بار (12.0) — مجموعة `nav` كبقيّة اللافتات
        ['nav.admin.group_developers', 'nav', 'لافتة مجموعة السايد بار: المطوّرين', 'string', 'المطوّرين'],
        ['nav.admin.item_developers_api', 'nav', 'لافتة بند السايد بار: API', 'string', 'API'],
        ['nav.admin.item_developers_webhooks', 'nav', 'لافتة بند السايد بار: Webhooks', 'string', 'Webhooks'],
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
