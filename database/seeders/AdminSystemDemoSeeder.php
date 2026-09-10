<?php

namespace Database\Seeders;

use App\Models\AdAudience;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\ImageTemplate;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\TransferMethod;
use App\Models\User;
use App\Services\Features\FeatureCatalog;
use App\Services\Features\FeatureGate;
use App\Support\Access\PermissionExpander;
use Database\Seeders\Concerns\GrantsWithinMatrixCeiling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات مجال «المتجر والماليّات والإحصائيّات والإعدادات والنظام» التجريبيّة،
 * ومعها **الصلاحيّات والإعدادات الناقصة** لهذا المجال.
 *
 * 🏆 القاعدة الذهبيّة (2.13): كلّ رقم وكلّ نصّ هنا **إعداد بقيمة افتراضيّة** —
 *    ولا يوجد في كود المجال رقمٌ محروق واحد.
 */
class AdminSystemDemoSeeder extends Seeder
{
    // كتابةُ الإسناد تمرّ بنقطة القصّ نفسها التي يمرّ بها مسار الإنتاج (12.2.2)
    use GrantsWithinMatrixCeiling;

    public function run(): void
    {
        $this->permissions();
        $this->settings();
        // 🖥️ مفاتيح المزايا (24.3) — تعريفاتها في مسار الإنتاج، وهنا كذلك
        // ليجدها كلّ اختبارٍ يزرع هذا السيدر.
        $this->featureFlagsSettings();
        $this->store();
        $this->topup();
        $this->studio();
        $this->articles();
        $this->ads();

        Cache::forget('settings');
        $this->command?->info('بيانات admin-system جاهزة.');
    }

    // ---------------------------------------------------------------- الصلاحيّات

    /**
     * ⭐ موارد هذا المجال **صارت في المصفوفة الأمّ** (12.2.2): المقالات · استوديو
     * الصور · الماليّات · طلبات الشحن · الإعلان المدفوع · مصادر الاكتساب.
     *
     * كانت تُنشَأ هنا بـ`updateOrCreate` فتكتب `is_sensitive`/`is_owner_only`
     * من جديد في كلّ تشغيل — أي أنّ أيّ تصحيحٍ لوسم الحساسيّة في المصفوفة كان
     * **يُمحى** بعد السيدر التجريبيّ. فالتعريف مصدره واحدٌ الآن (`PermissionSeeder`)،
     * ويبقى لهذا السيدر ما يخصّه فعلًا: **ربط الأدوار** بها.
     */
    private function permissions(): void
    {
        $this->grantRoles();
    }

    /** ربط الأدوار القائمة بصلاحيّات المجال — والحسّاس يبقى لمالك المنصّة وحده */
    private function grantRoles(): void
    {
        $map = [
            'platform_owner' => ['finance', 'topup_requests', 'articles', 'image_templates', 'image_export', 'ad_audiences', 'ad_pixels', 'acquisition_sources'],
            'super_admin' => ['topup_requests', 'articles', 'image_templates', 'image_export', 'ad_audiences', 'acquisition_sources'],
            'finance_admin' => ['topup_requests'],
            'marketing_admin' => ['articles', 'image_templates', 'image_export', 'ad_audiences', 'acquisition_sources'],
            'tech_admin' => ['acquisition_sources'],
        ];

        foreach ($map as $roleKey => $resources) {
            $role = Role::query()->where('key', $roleKey)->first();

            if (! $role) {
                continue;
            }

            $ids = Permission::query()
                ->whereIn('resource', $resources)
                // ⭐ منع تصعيد الامتياز: غير المالك لا يأخذ owner-only مهما كان الدور
                ->when($roleKey !== 'platform_owner', fn ($q) => $q->where('is_owner_only', false))
                ->pluck('id')
                ->all();

            /*
             | ⭐ `scope = ALL` كان يُكتَب لكلّ مفتاحٍ بلا مرورٍ بالمصفوفة —
             | وهو عين ما صولح في مسار الإنتاج (12.2.2). فتمرّ الكتابة الآن
             | بنقطة القصّ نفسها: المنح يبقى بمورده ويُقصّ نطاقُه إلى السقف.
             */
            $this->insertRows($role->id, $ids, 'ALL');
        }

        app(PermissionExpander::class);
    }

    // ---------------------------------------------------------------- الإعدادات

    public function settings(): void
    {
        // [key, group, label, type, default, owner_only]
        $rows = [
            // ---------------- المتجر (24.3-أوّلًا)
            ['store.enabled', 'store', 'تفعيل المتجر', 'bool', '1', false],
            ['store.unified_grid', 'store', 'شبكة موحّدة بلا فصل حسب النوع', 'bool', '1', false],
            ['store.admin.per_page', 'store', 'عدد الصفوف في صفحة الإدارة', 'number', '20', false],
            ['store.products_per_page', 'store', 'عدد المنتجات لكلّ صفحة', 'number', '24', false],
            ['store.empty.text', 'store', 'نصّ الحالة الفارغة', 'text', 'مفيش نتائج للفلتر ده — جرّب توسّع شويّة.', false],
            ['bundles.enabled', 'store', 'تفعيل البندلز', 'bool', '1', false],
            // ⛔ 'bundles.anchoring' حُذفت (ولها هجرة حذفٍ من settings) — استُبدلت
            // بـ'store.bundle.anchoring_enabled' (تقرؤها BundleLanding فعلًا)
            // ولم يقرأ أحدٌ الاسم القديم قطّ.
            ['coupons.enabled', 'store', 'تفعيل الكوبونات', 'bool', '1', false],
            ['order_bump.enabled', 'store', 'تفعيل Order-bump', 'bool', '1', false],
            ['order_bump.max_per_checkout', 'store', 'أقصى عروض Bump في صفحة المراجعة', 'number', '2', false],
            ['store.invoice.prefix', 'store', 'بادئة رقم الفاتورة', 'string', 'INV-', false],
            ['store.invoice.digits', 'store', 'عدد خانات تسلسل الفاتورة', 'number', '6', false],
            ['store.order.pending_expiry_minutes', 'store', 'مهلة انتهاء الطلب المعلّق (دقائق)', 'number', '30', false],
            // ⛔ 'library.reader.session_minutes' و'library.watermark.opacity_percent'
            // و'library.watermark.font_size' حُذفت (هجرة 2026_09_10_100090):
            // الأخيرتان تكرارٌ لـ'reader.watermark.opacity_percent'/'reader.watermark.font_size_px'
            // (المزروعتان في LibraryDemoSeeder وتقرآن فعلًا في PageWatermark.php)،
            // والأولى يتيمةٌ بلا قارئ ولا مواصفة دستوريّة.

            // ---------------- 🔒 الماليّات (مجموعة معزولة لمالك المنصّة)
            ['finance.rates.usd_to_coins', 'finance', '1$ = كام كوين', 'number', '50', true],
            ['finance.rates.ticket_to_coins', 'finance', '1 تذكرة = كام كوين', 'number', '10', true],
            ['finance.rates.ticket_to_xp', 'finance', '1 تذكرة = كام XP', 'number', '300', true],
            ['finance.transfer.coins_fee_percent', 'finance', 'رسوم حوالة الكوينز (%)', 'number', '15', true],
            ['finance.transfer.xp_fee_percent', 'finance', 'رسوم حوالة الـXP (%)', 'number', '85', true],
            ['finance.transfer.tickets_fee_percent', 'finance', 'رسوم حوالة التذاكر (%)', 'number', '0', true],
            ['finance.transfer.rounding', 'finance', 'سياسة التقريب', 'string', 'ceil', true],
            ['finance.exchange.fee_percent', 'finance', 'رسوم تحويل العملة (%)', 'number', '5', true],
            ['finance.topup.min_amount', 'finance', 'الحدّ الأدنى لعمليّة الشحن', 'number', '50', true],
            ['finance.topup.max_amount', 'finance', 'الحدّ الأقصى لعمليّة الشحن', 'number', '20000', true],
            ['finance.topup.daily_limit', 'finance', 'الحدّ اليوميّ للشحن', 'number', '50000', true],
            ['finance.withdraw.fee_percent', 'finance', 'رسوم السحب (%)', 'number', '1', true],
            ['finance.withdraw.min_fee_usd', 'finance', 'حدّ أدنى للرسوم بالدولار', 'number', '0.5', true],
            ['finance.withdraw.sla_hours', 'finance', 'SLA معالجة السحب (ساعات)', 'number', '72', true],
            ['finance.referral.commission_percent', 'finance', 'عمولة الريفيرال (%)', 'number', '7', true],
            // ⭐ عملة Hours (19.1 · 24 القسم 12): موجودة في المحفظة من اليوم بلا مصدر كسبٍ مفعَّل بعد —
            // سعر الصرف ومصادر الكسب أرقامٌ يقرّرها المالك لاحقًا لا افتراضٌ محروق.
            ['finance.hours.show_in_wallet', 'finance', 'إظهار عملة الساعات في المحفظة', 'bool', '1', true],
            ['finance.hours.exchange_rate', 'finance', 'سعر صرف الساعة (كوين)', 'number', '0', true],
            ['finance.hours.earn_sources', 'finance', 'مصادر كسب الساعات', 'json', '[]', true],
            ['finance.pricing.default_currency', 'finance', 'العملة الافتراضيّة', 'string', 'coins', true],
            ['finance.preview.example_amount', 'finance', 'قيمة المثال في المعاينة اللحظيّة', 'number', '1000', true],
            ['finance.refund.policy_ar', 'finance', 'نصّ سياسة الاسترجاع (عربيّ)', 'text', 'لا يوجد استرجاع نقديّ للمدفوعات، ويبقى رصيدك في محفظتك تشتري به ما تشاء من الموقع.', true],
            ['finance.refund.policy_en', 'finance', 'نصّ سياسة الاسترجاع (إنجليزيّ)', 'text', 'No cash refunds. Your balance stays in your wallet and can be spent on the platform.', true],
            ['finance.refund.show_standalone_page', 'finance', 'صفحة سياسة مستقلّة دائمة', 'bool', '1', true],
            ['finance.refund.show_before_payment', 'finance', 'إقرار قبل إتمام الدفع', 'bool', '1', true],
            ['finance.refund.show_on_invoice', 'finance', 'إشارة في الفاتورة', 'bool', '1', true],
            ['finance.invoice.footer_ar', 'finance', 'تذييل الفاتورة', 'text', 'شكرًا لثقتك — رصيدك يفضل معاك في محفظتك.', true],

            // ---------------- الشحن والبوّابة (19.5)
            ['topup.credit_currency', 'store', 'عملة الشحن', 'string', 'coins', false],
            ['topup.admin.per_page', 'store', 'عدد طلبات الشحن لكلّ صفحة', 'number', '20', false],
            ['topup.review.internal_late_hours', 'store', '⛔ عتبة «متأخّر» الداخليّة للأدمن (ساعات)', 'number', '24', false],
            ['topup.manual_credit.min', 'store', 'أقلّ قيمة يدويّة', 'number', '1', false],
            ['topup.manual_credit.max', 'store', 'أقصى قيمة يدويّة', 'number', '100000', false],
            // 🔒 مفاتيح البوّابة لمالك المنصّة وحده (19.5-ج-5)
            ['topup.gateway.enabled', 'store', 'تفعيل بوّابة الدفع', 'bool', '1', false],
            ['topup.gateway.sandbox', 'store', 'وضع الاختبار (Sandbox)', 'bool', '1', false],
            ['topup.gateway.api_key', 'store', 'مفتاح API للبوّابة', 'string', '', true],
            ['topup.gateway.vendor_key', 'store', 'مفتاح التاجر (للتحقّق من الهاش)', 'string', '', true],
            ['topup.gateway.currency', 'store', 'عملة البوّابة', 'string', 'EGP', false],
            ['topup.gateway.success_url', 'store', 'رابط النجاح', 'string', '/wallet?topup=success', false],
            ['topup.gateway.fail_url', 'store', 'رابط الفشل', 'string', '/wallet?topup=fail', false],
            ['topup.gateway.pending_url', 'store', 'رابط المعلّق', 'string', '/wallet?topup=pending', false],
            ['topup.gateway.methods', 'store', 'وسائل الدفع المفعَّلة', 'json', '["card","wallet","fawry"]', false],
            ['topup.gateway.min_amount', 'store', 'الحدّ الأدنى لعمليّة البوّابة', 'number', '50', false],
            ['topup.gateway.max_amount', 'store', 'الحدّ الأقصى لعمليّة البوّابة', 'number', '20000', false],
            ['topup.gateway.fees_on', 'store', 'تحميل الرسوم (platform/user)', 'string', 'platform', false],
            ['topup.gateway.timeout_seconds', 'store', 'مهلة نداء البوّابة (ثوانٍ)', 'number', '8', false],
            ['topup.gateway.logs_per_page', 'store', 'صفوف سجلّ الويب هوك', 'number', '25', false],
            ['topup.gateway.customer_address', 'store', 'عنوان العميل الافتراضيّ', 'string', '-', false],
            /*
            | 🔴 حرّاس الويب هوك (19.5-ج-2) — نصوصها وأرقامها من هنا لا من الكود.
            | `enabled` أعلاه **لا يُعتَدّ به** بلا `api_key` و`vendor_key` معًا:
            | توجّلٌ مرفوع فوق مفتاحٍ فارغ يَعِد بحمايةٍ غير موجودة.
            */
            ['topup.gateway.misconfig.notice', 'store', 'رسالة الأدمن حين تنقص مفاتيح البوّابة', 'text', 'بوّابة الدفع متوقّفة فعليًّا وكلّ نداء ويب هوك مرفوض. الناقص: {missing}. ومن غير مفتاح التاجر يبقى توقيع الويب هوك يقدر يحسبه أيّ حدّ، فالرفض مقصود. الحلّ: افتح لوحة الإدارة ← شحن الحساب ← بوّابة الدفع، انسخ المفاتيح من داشبورد فواتيرك واحفظها، ثمّ اضغط [اختبار الاتّصال] للتأكيد.', true],
            ['topup.gateway.misconfig.title', 'store', 'عنوان إشعار عطب ضبط البوّابة', 'string', 'بوّابة الدفع محتاجة ضبط ⚠️', true],
            ['topup.gateway.misconfig.separator', 'store', 'فاصل أسماء المفاتيح الناقصة', 'string', ' و', true],
            ['topup.gateway.misconfig.notify_cooldown_minutes', 'store', 'تبريد إشعار عطب البوّابة (دقائق)', 'number', '60', true],
            ['topup.gateway.webhook.blocked_message', 'store', 'ردّ الويب هوك حين البوّابة غير مضبوطة', 'string', 'gateway not configured', false],
            ['topup.gateway.webhook.rate_limit', 'store', 'أقصى نداءات ويب هوك في النافذة (لكلّ IP)', 'number', '60', false],
            ['topup.gateway.webhook.rate_window_minutes', 'store', 'نافذة حدّ نداءات الويب هوك (دقائق)', 'number', '1', false],
            ['topup.gateway.webhook.rate_limit_message', 'store', 'ردّ الويب هوك عند تجاوز الحدّ', 'string', 'too many requests', false],

            // ---------------- الإحصائيّات (12.8)
            ['stats.period.default_days', 'stats', 'الفترة الافتراضيّة (أيّام)', 'number', '30', false],
            ['stats.compare.default_on', 'stats', 'المقارنة مفعَّلة افتراضيًّا', 'bool', '0', false],
            ['stats.cohorts.months', 'stats', 'عدد شهور الـCohorts', 'number', '6', false],
            ['stats.geo.max_rows', 'stats', 'أقصى صفوف الخريطة الجغرافيّة', 'number', '20', false],
            ['stats.export.max_rows', 'stats', 'حدّ صفوف التصدير', 'number', '50000', false],
            // ⭐ «تصدير CSV/Excel/PDF» (24.3-خامسًا · 12.8) — الصيغ الثلاث تقع فعلًا،
            // ولافتة كلّ زرّ إعدادٌ لا نصٌّ محروق في القالب (2.13).
            ['stats.export.formats', 'stats', 'أزرار التصدير: المفتاح = لافتة الزرّ', 'json', '{"csv":"تصدير CSV","xlsx":"تصدير Excel","pdf":"تصدير PDF"}', false],
            ['stats.export.title_prefix', 'stats', 'بادئة عنوان ملفّ التصدير', 'string', 'الإحصائيّات', false],
            ['exports.xlsx_row_limit', 'stats', 'سقف صفوف ملفّ Excel', 'number', '20000', false],
            ['exports.pdf_row_limit', 'stats', 'سقف صفوف ملفّ PDF', 'number', '500', false],
            ['exports.pdf_empty_line', 'stats', 'سطر الـPDF حين لا بيانات', 'string', 'مافيش بيانات في المدى ده.', false],
            ['exports.pdf_truncated_line', 'stats', 'سطر الـPDF عند قصّ الصفوف', 'string', 'معروض أوّل :shown صفًّا من :total — الملفّ الكامل بصيغة CSV أو Excel.', false],
            ['exports.fallback_note', 'stats', 'ملاحظة تعذّر توليد الصيغة المطلوبة', 'string', 'تعذّر توليد ملفّ :format فبعتناه CSV.', false],
            ['stats.cache_minutes', 'stats', 'مدّة كاش التقرير (دقائق)', 'number', '10', false],
            ['stats.hide_finance_tab', 'stats', 'إخفاء التاب الماليّ عن غير المخوَّلين', 'bool', '1', true],
            ['stats.forbidden.message', 'stats', 'رسالة الردّ حين لا تابَّ يملكه المستخدم', 'string', 'ليس لديك صلاحيّة الوصول لهذه الصفحة.', false],
            ['stats.empty.message', 'stats', 'نصّ الحالة الفارغة في التقارير', 'string', 'لا بيانات في هذه الفترة — جرّب فترة أوسع', false],
            ['stats.compare.hint', 'stats', 'تفسير خطّ المقارنة تحت الرسم', 'string', 'الخطّ المتقطّع = الفترة السابقة.', false],

            /*
             | ⭐ تابّا **التطوّع** و**الشهادات** (24.3-خامسًا) — كانا بندين في
             | الخريطة بلا تابّ، فيفتحان لوحةً أخرى. ولافتاتهما وأعمدة جداولهما
             | إعداداتٌ لا نصوصٌ محروقة (2.13-أ: «النصوص الظاهرة للمستخدم»).
             */
            ['stats.tabs.volunteer.label', 'stats', 'لافتة تاب التطوّع', 'string', 'التطوّع', false],
            ['stats.volunteer.kpi.placed', 'stats', 'مؤشّر: التسكينات المقبولة', 'string', 'تسكينات مقبولة', false],
            ['stats.volunteer.kpi.delivered', 'stats', 'مؤشّر: المهامّ المسلَّمة', 'string', 'مهامّ مسلَّمة', false],
            ['stats.volunteer.kpi.approved', 'stats', 'مؤشّر: المهامّ المعتمَدة', 'string', 'مهامّ معتمَدة', false],
            ['stats.volunteer.kpi.sla', 'stats', 'مؤشّر: التزام نوافذ التصعيد', 'string', 'التزام نوافذ التصعيد', false],
            ['stats.volunteer.chart.placement', 'stats', 'عنوان رسم التسكين', 'string', 'طلبات التسكين عبر الفترة', false],
            ['stats.volunteer.chart.entities', 'stats', 'عنوان رسم المهامّ حسب الكيان', 'string', 'المهامّ حسب الكيان', false],
            ['stats.volunteer.table.sla', 'stats', 'عنوان جدول SLA المستويات', 'string', 'SLA مستويات التصعيد', false],
            ['stats.volunteer.col.level', 'stats', 'عمود: مستوى التصعيد', 'string', 'مستوى التصعيد', false],
            ['stats.volunteer.col.closed', 'stats', 'عمود: الحالات المغلقة', 'string', 'حالات مغلقة', false],
            ['stats.volunteer.col.on_time', 'stats', 'عمود: المحسوم داخل النافذة', 'string', 'داخل النافذة', false],
            ['stats.volunteer.col.rate', 'stats', 'عمود: نسبة الالتزام', 'string', 'نسبة الالتزام %', false],

            ['stats.tabs.certificates.label', 'stats', 'لافتة تاب الشهادات', 'string', 'الشهادات', false],
            ['stats.certificates.kpi.issued', 'stats', 'مؤشّر: الشهادات الصادرة', 'string', 'شهادات صادرة', false],
            ['stats.certificates.kpi.rate', 'stats', 'مؤشّر: معدّل الإصدار اليوميّ', 'string', 'معدّل الإصدار اليوميّ', false],
            ['stats.certificates.kpi.revoked', 'stats', 'مؤشّر: الإلغاءات', 'string', 'إلغاءات', false],
            ['stats.certificates.kpi.expired', 'stats', 'مؤشّر: المنتهية', 'string', 'منتهية', false],
            ['stats.certificates.chart.series', 'stats', 'عنوان رسم الإصدار', 'string', 'الإصدار عبر الفترة', false],
            ['stats.certificates.chart.types', 'stats', 'عنوان رسم أنواع الشهادات', 'string', 'حسب نوع الشهادة', false],
            ['stats.certificates.table.accreditations', 'stats', 'عنوان جدول جهات الاعتماد', 'string', 'حسب جهة الاعتماد', false],
            ['stats.certificates.col.accreditation', 'stats', 'عمود: جهة الاعتماد', 'string', 'جهة الاعتماد', false],
            ['stats.certificates.col.issued', 'stats', 'عمود: عدد الشهادات الصادرة', 'string', 'شهادات صادرة', false],

            /*
             | ⭐ تاب «تقرير أثر المكافآت» (24.3-خامسًا · 12.9) — كان بندًا في نفس
             | سطر التبويبات («… التطوّع · الشهادات · تقرير أثر المكافآت») ولم
             | يُبنَ. 🔒 owner_only كسائر إعدادات `manual_rewards`/`finance` (12.9
             | كلّها `is_owner_only=true`) — لا «دائمًا» كتابَي التطوّع والشهادات.
             */
            ['stats.tabs.rewards.label', 'stats', 'لافتة تاب أثر المكافآت', 'string', 'تقرير أثر المكافآت', true],
            ['stats.rewards.kpi.granted', 'stats', 'مؤشّر: إجماليّ الممنوح', 'string', 'إجماليّ الممنوح', true],
            ['stats.rewards.kpi.deducted', 'stats', 'مؤشّر: إجماليّ المخصوم', 'string', 'إجماليّ المخصوم', true],
            ['stats.rewards.kpi.net', 'stats', 'مؤشّر: الصافي', 'string', 'الصافي', true],
            ['stats.rewards.kpi.currencies', 'stats', 'مؤشّر: عدد العملات المتأثّرة', 'string', 'عملات متأثّرة', true],
            ['stats.rewards.chart.series', 'stats', 'عنوان رسم الحركة اليوميّة', 'string', 'الحركة اليوميّة الصافية', true],
            ['stats.rewards.chart.granted', 'stats', 'عنوان رسم الممنوح حسب العملة', 'string', 'الممنوح حسب العملة', true],
            ['stats.rewards.table.currencies', 'stats', 'عنوان جدول العملات', 'string', 'إجماليّ الممنوح والمخصوم لكلّ عملة', true],
            ['stats.rewards.col.currency', 'stats', 'عمود: العملة', 'string', 'العملة', true],
            ['stats.rewards.col.granted', 'stats', 'عمود: الممنوح', 'string', 'الممنوح', true],
            ['stats.rewards.col.deducted', 'stats', 'عمود: المخصوم', 'string', 'المخصوم', true],
            ['stats.rewards.col.net', 'stats', 'عمود: الصافي', 'string', 'الصافي', true],

            // ---------------- وضع الصيانة (12.7-و-1)
            ['system.maintenance.enabled', 'maintenance', 'وضع الصيانة العامّ', 'bool', '0', false],
            ['system.maintenance.message', 'maintenance', 'رسالة الصيانة', 'text', 'بنطوّر حاجة حلوة — هنرجع قريب.', false],
            ['system.maintenance.freeze_deadlines', 'maintenance', 'تجميد كلّ المهل أثناء الصيانة', 'bool', '1', false],
            ['system.maintenance.default_hours', 'maintenance', 'المدّة الافتراضيّة (ساعات)', 'number', '2', false],
            ['system.maintenance.max_hours', 'maintenance', 'أقصى مدّة صيانة (ساعات)', 'number', '168', false],
            ['system.maintenance.max_extend_hours', 'maintenance', 'أقصى تمديد بالمرّة (ساعات)', 'number', '24', false],
            ['system.maintenance.overrun_text', 'maintenance', 'نصّ ما بعد الصفر', 'string', 'قرّبنا ننتهي — دقايق', false],
            ['system.maintenance.refresh_seconds', 'maintenance', 'تحديث صفحة الصيانة (ثوانٍ)', 'number', '120', false],
            ['system.maintenance.animation', 'maintenance', 'أنيميشن صفحة الصيانة', 'bool', '1', false],
            ['system.maintenance.allow_admin_ip', 'maintenance', 'استثناء IP الأدمن', 'bool', '1', false],
            // ⚠️ قائمة الـIP مصدرها الوحيد `system.maintenance.exempt_ips` (يزرعه
            // سيدر الأمان) — وكان لها مفتاح ثانٍ `admin_ips` هنا، فقائمةٌ أمنيّة
            // بمصدرين تعني أنّ شيلَ IP من شاشةٍ لا يشيله من الأخرى (12.7-ج).
            // ---------------- الصيانة المجدولة (12.7-ج: تبدأ تلقائيًّا)
            ['system.maintenance.scheduled_at', 'maintenance', 'موعد بدء الصيانة المجدولة', 'string', '', false],
            ['system.maintenance.scheduled_message', 'maintenance', 'رسالة الصيانة المجدولة', 'text', '', false],
            ['system.maintenance.scheduled_hours', 'maintenance', 'ساعات الصيانة المجدولة', 'number', '0', false],
            ['system.maintenance.resume_toast_ar', 'maintenance', 'نصّ Toast تمديد المهلة', 'text', 'مهلتك امتدّت {hours} ساعة بسبب الصيانة.', false],

            // ---------------- مفاتيح المزايا والنظام
            // ⭐ نطاقات القيم الرقميّة — من إعدادٍ لا مشتقّةً من اسم المفتاح (2.13).
            // أدقّ بادئةٍ مطابقة هي الحاكمة، و`*` هي الافتراضيّ العامّ.
            ['ux.settings_ranges', 'ux', 'نطاقات القيم الرقميّة المسموحة', 'json', '{"*":[0,1000000],"percent":[0,100],"hours":[0,8760],"days":[0,3650],"minutes":[0,525600]}', false],

            ['features.show_beta_badge', 'features', 'شارة «تجريبيّة» للمزايا الجديدة', 'bool', '1', false],
            ['features.disabled_behavior', 'features', 'سلوك الميزة الموقوفة (hide/message)', 'string', 'hide', false],
            ['features.disabled_message', 'features', 'نصّ الميزة الموقوفة', 'text', 'الميزة دي متوقّفة مؤقّتًا — هترجع قريب.', false],
            ['articles.enabled', 'features', 'تفعيل مركز المقالات', 'bool', '1', false],
            ['images.enabled', 'features', 'تفعيل استوديو الصور', 'bool', '1', false],
            ['backups.keep_count', 'backups', 'عدد النسخ المحفوظة', 'number', '7', false],
            ['backups.daily_time', 'backups', 'وقت النسخة الدوريّة', 'string', '03:00', false],
            ['backups.disk_alert_percent', 'backups', 'عتبة تنبيه امتلاء القرص (%)', 'number', '85', false],
            ['backups.cron_alert_hours', 'backups', 'عتبة تنبيه توقّف الكرون (ساعات)', 'number', '1', false],
            ['updates.dry_run_required', 'updates', 'Dry-run إلزاميّ قبل التحديث', 'bool', '1', false],
            ['updates.batch_rows', 'updates', 'حجم دفعة الترحيل (صفوف)', 'number', '1000', false],
            ['updates.forward_only', 'updates', 'منع الرجوع لإصدار أقدم', 'bool', '1', false],
            ['settings.audit.error.missing_key', 'system', 'رسالة سجلّ الإعداد بلا مفتاح', 'text', 'مافيش مفتاح إعداد في الطلب — افتح السجلّ من جنب الحقل نفسه.', false],
            ['settings.audit.error.unknown_key', 'system', 'رسالة سجلّ إعدادٍ غير موجود', 'text', 'الإعداد ده مش موجود — يمكن يكون اتشال، حدّث الصفحة وجرّب تاني.', false],
            // ⭐ نقطة دفعة المجموعة (التحميل الكسول — 2.15-ب): رسالتا الخطأ فيها تقولان ماذا يفعل (2.17-ب)
            ['settings.batch.error.unknown_tab', 'system', 'رسالة دفعةٍ لتابٍ مجهول', 'text', 'التاب ده مش موجود — حدّث الصفحة وجرّب تاني.', false],
            ['settings.batch.error.unknown_group', 'system', 'رسالة دفعةٍ لمجموعةٍ خارج التاب', 'text', 'المجموعة دي مش في التاب ده — حدّث الصفحة.', false],
            ['countries.source', 'countries', 'مصدر بيانات الدول', 'string', 'dr5hn', false],
            ['countries.no_auto_delete', 'countries', 'لا حذف تلقائيّ — المحذوف يُخفى فقط', 'bool', '1', false],
            // عناوين أنواع الفروق (CountryDataSync::changes — 2.13-ب)
            ['countries.sync.change.added', 'countries', 'نوع الفرق: مضاف', 'string', 'مضاف', false],
            ['countries.sync.change.removed', 'countries', 'نوع الفرق: محذوف من المصدر', 'string', 'محذوف من المصدر', false],
            ['countries.sync.change.changed', 'countries', 'نوع الفرق: معدَّل', 'string', 'معدَّل', false],
            // ---------------- 12.7-د: فحص الفروق قبل الدمج بلا فقد
            ['countries.source_url', 'countries', 'رابط المصدر', 'string', 'https://github.com/dr5hn/countries-states-cities-database', false],
            ['countries.attribution', 'countries', 'نصّ الإسناد (ODbL)', 'text', 'بيانات الدول والمحافظات من dr5hn/countries-states-cities-database — برخصة ODbL v1.0.', false],
            ['countries.admin.per_page', 'countries', 'عدد الدول في جدول الإدارة', 'number', '25', false],
            ['countries.import.max_kb', 'countries', 'أقصى حجم لملفّ النسخة (KB)', 'number', '8192', false],
            ['countries.default_timezone', 'countries', 'المنطقة الزمنيّة الافتراضيّة للدولة الجديدة', 'string', 'Africa/Cairo', false],
            // ---------------- 12.7-د: «Toggle كود الهاتف في التسجيل (عرض 110px)» + «الدولة الافتراضيّة»
            // ثلاثتها منصوصة بالحرف في بلوك إعدادات «بيانات الدول»، وتستهلكها
            // **صفحة التسجيل 2.5-ب** في Select أكواد الدول بالأعلام.
            ['countries.registration.phone_code', 'countries', 'إظهار كود الهاتف في التسجيل', 'bool', '1', false],
            ['countries.registration.phone_code_width', 'countries', 'عرض حقل كود الهاتف (px)', 'number', '110', false],
            ['countries.registration.default_iso2', 'countries', 'الدولة الافتراضيّة في التسجيل (ISO2)', 'string', 'EG', false],
            ['countries.attribution_link_label', 'countries', 'نصّ رابط المصدر بجوار الإسناد', 'string', 'المصدر', false],
            // ---------------- 12.7-د: جلب المصدر عبر الشبكة (بلا أيّ حزمة خارجيّة)
            ['countries.source.url', 'countries', 'رابط جلب نسخة المصدر (JSON)', 'string', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/countries.json', false],
            // المحافظات في ملفٍّ ثانٍ عند dr5hn مربوطة بـ`country_code` — وتفريغه
            // لا يُخفي محافظةً ولا يحذفها (قاعدة المالك)، بل يجعل النسخة دولًا فقط.
            ['countries.source.states_url', 'countries', 'رابط ملفّ المحافظات (JSON)', 'string', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/states.json', false],
            ['countries.source.format', 'countries', 'شكل المصدر (dr5hn / native)', 'string', 'dr5hn', false],
            ['countries.source.timeout', 'countries', 'مهلة الجلب (ثوانٍ)', 'number', '20', false],
            ['countries.source.retries', 'countries', 'عدد محاولات الجلب', 'number', '2', false],
            ['countries.source.retry_delay_ms', 'countries', 'الانتظار بين المحاولات (مللي ثانية)', 'number', '500', false],
            // ---------------- 12.7-د: الفحص الدوريّ — الدوريّة واليوم إعدادان
            ['countries.source.check.enabled', 'countries', 'تفعيل الفحص الدوريّ للمصدر', 'bool', '1', false],
            ['countries.source.check.every_months', 'countries', 'دوريّة الفحص (كلّ كام شهر)', 'number', '1', false],
            ['countries.source.check.day_of_month', 'countries', 'يوم الفحص في الشهر', 'number', '1', false],
            ['countries.source.check.hour', 'countries', 'ساعة الفحص', 'number', '4', false],
            ['countries.source.check.timezone', 'countries', 'منطقة الفحص الزمنيّة (فاضية = توقيت المنصّة)', 'string', '', false],
            ['countries.source.check.max_recipients', 'countries', 'أقصى عدد من يصلهم إشعار الفروق', 'number', '10', false],
            ['countries.source.check.notify_category', 'countries', 'فئة إشعار الفروق', 'string', 'system', false],
            ['countries.source.check.notify_title', 'countries', 'عنوان إشعار الفروق', 'string', 'فروق جديدة في بيانات الدول', false],
            ['countries.source.check.notify_body', 'countries', 'نصّ إشعار الفروق', 'text', 'الفحص الدوريّ لقى فروق في المصدر: مضاف {added} · محذوف {removed} · معدَّل {changed} — راجعها قبل الدمج.', false],
            // ---------------- 12.7-د: نتيجة الفحص كما تُقال للمالك (2.17-ب)
            ['countries.source.check.fetched_text', 'countries', 'نصّ نجاح الجلب', 'text', 'اتجابت نسخة المصدر ✓ — لسّه ما اتدمجتش، الفروق تحت.', false],
            ['countries.source.check.diff_text', 'countries', 'نصّ وجود فروق', 'text', 'المصدر فيه فروق: مضاف {added} · محذوف {removed} · معدَّل {changed} — راجعها قبل الدمج.', false],
            ['countries.source.check.none_text', 'countries', 'نصّ «مافيش فروق»', 'text', 'المصدر مطابق لبياناتنا — مافيش فروق.', false],
            ['countries.source.check.never_text', 'countries', 'نصّ «ما اتفحصش قبل كده»', 'text', 'لسّه ما اتفحصش المصدر ولا مرّة — اضغط «فحص المصدر الآن».', false],
            ['countries.source.check.label', 'countries', 'عنوان بلوك آخر فحص', 'string', 'آخر فحص للمصدر', false],
            ['countries.source.check.button', 'countries', 'نصّ زرّ الفحص اليدويّ', 'string', 'فحص المصدر الآن', false],
            // ---------------- 12.7-د: كلّ فشل شبكة له رسالة عربيّة تقول ماذا يفعل (2.17-ب)
            ['countries.source.error.no_url', 'countries', 'رسالة: مافيش رابط مصدر', 'text', 'مافيش رابط للمصدر — اضبط «رابط جلب نسخة المصدر» من الإعدادات الأوّل، وبعدها جرّب الفحص تاني.', false],
            ['countries.source.error.timeout', 'countries', 'رسالة: انتهت المهلة', 'text', 'المصدر ما ردّش خلال {timeout} ثانية بعد {attempts} محاولة — جرّب تاني بعد شويّة أو زوّد المهلة من الإعدادات.', false],
            ['countries.source.error.http', 'countries', 'رسالة: حالة HTTP غير ناجحة', 'text', 'المصدر ردّ بحالة {status} — راجع الرابط في الإعدادات أو استنّى وجرّب تاني.', false],
            ['countries.source.error.body', 'countries', 'رسالة: الجسم ليس JSON', 'text', 'اللي رجع من المصدر مش JSON صالح — اتأكّد إنّ الرابط بيرجّع ملفّ النسخة نفسه مش صفحة.', false],
            ['countries.source.error.shape', 'countries', 'رسالة: JSON بلا قايمة دول', 'text', 'النسخة اللي رجعت من المصدر ناقصة: {reason}', false],
            ['countries.source.error.network', 'countries', 'رسالة: تعذّر الوصول للمصدر', 'text', 'ما قدرناش نوصل للمصدر: {reason} — راجع الرابط في الإعدادات وجرّب تاني.', false],
            ['audit.per_page', 'system', 'صفوف سجلّ التدقيق', 'number', '50', false],
            ['audit.retention_days', 'system', 'مدّة الاحتفاظ بالسجلّ (أيّام)', 'number', '365', false],
            ['audit.require_reason_on_finance', 'system', 'إلزام السبب في التغييرات الماليّة', 'bool', '1', true],
            // ---------------- عناوين أفعال سجلّ التدقيق (AuditTrail::actions — 2.13-ب)
            ['audit.action.role_created', 'system', 'فعل: إنشاء دور', 'string', 'إنشاء دور', false],
            ['audit.action.role_deleted', 'system', 'فعل: حذف دور', 'string', 'حذف دور', false],
            ['audit.action.role_permissions_updated', 'system', 'فعل: تعديل صلاحيّات دور', 'string', 'تعديل صلاحيّات دور', false],
            ['audit.action.role_assigned', 'system', 'فعل: إسناد دور', 'string', 'إسناد دور', false],
            ['audit.action.role_unassigned', 'system', 'فعل: سحب دور', 'string', 'سحب دور', false],
            ['audit.action.permission_user_updated', 'system', 'فعل: استثناء صلاحيّة لمستخدم', 'string', 'استثناء صلاحيّة لمستخدم', false],
            ['audit.action.user_approved', 'system', 'فعل: اعتماد حساب', 'string', 'اعتماد حساب', false],
            ['audit.action.user_rejected', 'system', 'فعل: رفض حساب', 'string', 'رفض حساب', false],
            ['audit.action.segment_created', 'system', 'فعل: إنشاء شريحة', 'string', 'إنشاء شريحة', false],
            ['audit.action.segment_deleted', 'system', 'فعل: حذف شريحة', 'string', 'حذف شريحة', false],
            ['audit.action.attestation_approved', 'system', 'فعل: اعتماد إفادة', 'string', 'اعتماد إفادة', false],
            ['audit.action.attestation_rejected', 'system', 'فعل: رفض إفادة', 'string', 'رفض إفادة', false],
            ['platform.identity.name', 'appearance', 'اسم المنصّة على الصور والفواتير', 'string', 'المنصّة', false],

            // ---------------- استوديو الصور (12.14-ح)
            ['images.admin.per_page', 'images', 'عدد القوالب لكلّ صفحة', 'number', '12', false],
            // ⭐ محرّر السحب-إفلات (12.14 — نفس محرّك 12.5-ب): شبكة المحاذاة
            ['images.studio.grid_step', 'images', 'خطوة شبكة المحاذاة (Snap) %', 'number', '5', false],
            ['images.studio.snap_enabled', 'images', 'تفعيل شبكة المحاذاة افتراضيًّا', 'bool', '1', false],
            ['images.short_name.units', 'images', 'عدد وحدات الاسم المختصر', 'number', '2', false],
            ['images.text.default_max_chars', 'images', 'حدّ الأحرف الافتراضيّ', 'number', '28', false],
            ['images.text.default_overflow', 'images', 'سلوك التجاوز الافتراضيّ', 'string', 'shrink', false],
            ['images.font.path', 'images', 'مسار خطّ Cairo المضمَّن', 'string', 'fonts/Cairo-Regular.ttf', false],
            ['images.batch.max_users', 'images', 'أقصى عدد صور في التوليد الجماعيّ', 'number', '200', false],
            ['images.preview.cache_seconds', 'images', 'كاش المعاينة (ثوانٍ)', 'number', '60', false],
            ['images.watermark.color', 'images', 'لون تاريخ اللقطة والشعار', 'color', '#9fb3c8', false],
            ['images.avatar.fallback_bg', 'images', 'خلفيّة بديل الأفاتار', 'color', '#071825', false],
            ['images.avatar.fallback_fg', 'images', 'لون أحرف بديل الأفاتار', 'color', '#00d4b8', false],
            ['images.presets', 'images', 'المقاسات الجاهزة', 'json', '{"square":{"label":"بوست مربّع","width":1080,"height":1080},"story":{"label":"ستوري","width":1080,"height":1920},"cover":{"label":"كوفر","width":1640,"height":856},"whatsapp":{"label":"واتساب","width":1080,"height":1350}}', false],
            // ---------------- 12.14-أ: رفع الفريم · مجلّدات ووسوم — أعمدةٌ كانت بلا حقل
            ['images.purposes', 'images', 'أغراض القوالب', 'json', '{"marketing":"تسويق","leaderboard":"ليدر بورد","achievement":"لقطة إنجاز","volunteer_card":"بطاقة متطوّع"}', false],
            ['images.languages', 'images', 'لغات القوالب', 'json', '{"ar":"عربيّة","en":"إنجليزيّة"}', false],
            ['images.folders.defaults', 'images', 'مجلّدات القوالب الافتراضيّة', 'json', '["تسويق","إنجازات","ليدر بورد","بطاقات"]', false],
            ['images.template.frame_label', 'images', 'عنوان حقل الفريم/الخلفيّة', 'string', 'الفريم/الخلفيّة', false],
            ['images.template.frame_hint', 'images', 'شرح حقل الفريم', 'string', 'اختَر الفريم من المكتبة أو ارفع جديدًا — والطبقات بتتبني فوقه.', false],
            ['images.template.frame_clear', 'images', 'نصّ زرّ شيل الفريم', 'string', 'شيل الفريم', false],
            ['images.template.organize_label', 'images', 'عنوان بلوك التنظيم', 'string', 'التنظيم', false],
            ['images.template.folders_label', 'images', 'عنوان حقل المجلّدات', 'string', 'المجلّدات', false],
            ['images.template.folders_hint', 'images', 'شرح حقل المجلّدات', 'string', 'افصل بينها بفاصلة.', false],
            ['images.template.tags_label', 'images', 'عنوان حقل الوسوم', 'string', 'الوسوم', false],
            ['images.template.tags_hint', 'images', 'شرح حقل الوسوم', 'string', 'افصل بينها بفاصلة.', false],
            ['images.template.purpose_label', 'images', 'عنوان حقل غرض القالب', 'string', 'الغرض', false],
            ['images.template.language_label', 'images', 'عنوان حقل لغة القالب', 'string', 'اللغة', false],
            ['images.filters.any', 'images', 'خيار «الكلّ» في فلاتر الاستوديو', 'string', 'الكلّ', false],

            // ---------------- المقالات (21.2-ي)
            ['articles.admin.per_page', 'articles', 'عدد المقالات لكلّ صفحة', 'number', '20', false],
            ['articles.show_author', 'articles', 'إظهار اسم الكاتب', 'bool', '1', false],
            ['articles.index_public_pages', 'articles', 'فهرسة صفحات المقالات', 'bool', '1', false],
            ['articles.meta_title_template', 'articles', 'قالب عنوان الميتا', 'string', '{title} — {platform}', false],
            // ---------------- 21.2-أ: «التصنيف **والوسوم**» — العمود كان بلا حقل
            ['articles.tags_label', 'articles', 'عنوان حقل وسوم المقال', 'string', 'الوسوم', false],
            ['articles.tags_hint', 'articles', 'شرح حقل وسوم المقال', 'string', 'افصل بينها بفاصلة — وبتظهر آخر المقال المنشور.', false],
            ['articles.filters.any', 'articles', 'خيار «الكلّ» في فلتر الوسوم', 'string', 'الكلّ', false],
            ['articles.categories_label', 'articles', 'عنوان بلوك تصنيفات المقالات', 'string', 'تصنيفات المقالات', false],
            ['articles.category_name_label', 'articles', 'عنوان حقل اسم التصنيف', 'string', 'اسم التصنيف', false],
            ['articles.category_order_label', 'articles', 'عنوان حقل ترتيب التصنيف', 'string', 'الترتيب', false],
            ['articles.category_add_cta', 'articles', 'نصّ زرّ إضافة تصنيف', 'string', 'إضافة تصنيف', false],

            // ---------------- الإعلان المدفوع (21.3-و)
            ['ads.audience.max_rows', 'ads', 'أقصى صفوف في تصدير الشريحة', 'number', '50000', false],
            ['ads.consent.retention_days', 'ads', 'مدّة حفظ الموافقة (أيّام)', 'number', '180', false],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default, $ownerOnly]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                // القيمة الحاليّة لا تُدهَس لو الأدمن غيّرها بالفعل
                'value' => Setting::query()->where('key', $key)->value('value') ?? $default,
                'is_sensitive' => str_contains($key, 'api_key') || str_contains($key, 'vendor_key'),
                'is_owner_only' => $ownerOnly,
            ]);
        }

        // 🔒 مفاتيح البوّابة القائمة تبقى لمالك المنصّة وحده
        Setting::query()
            ->whereIn('key', ['topup.gateway.api_key', 'topup.gateway.vendor_key'])
            ->update(['is_owner_only' => true, 'is_sensitive' => true]);
    }

    /**
     * 🖥️ **مفاتيح المزايا** (24.3) — تعريفاتها ونصوص شاشتها.
     *
     * الاسم ينتهي بـ`settings` عن قصد: `SettingDefinitionsSeeder` يكتشفه
     * ويستدعيه في **مسار الإنتاج** (`DatabaseSeeder` ⟵ `Installer::seed`)،
     * فالمفاتيح تصل لكلّ تنصيبٍ حقيقيّ بلا تسجيلٍ يدويّ (BUILD.md §3).
     *
     * ولماذا صفوف `feature_flags` هنا لا في سيدر عرض؟ لأنّ **الميزة بلا صفّ =
     * ميزةٌ لا يستطيع المالك إطفاءها**، وشاشةٌ تعرض مفاتيح لا يقرؤها أحد هي
     * عين العطب الذي تسدّه 24.3. فالصفّ تعريفٌ لا محتوى عرض.
     */
    public function featureFlagsSettings(): void
    {
        // [key, group, label, type, default, owner_only]
        $rows = [
            // ---------------- بلوك الإعدادات الخمسة (24.3)
            ['features.disabled_message_en', 'features', 'نصّ الميزة الموقوفة (إنجليزيّ)', 'text', 'This feature is paused for a moment — it will be back soon.', false],
            ['features.alert_long_outage', 'features', 'تنبيه الأدمن عند إيقاف ميزة طويلًا', 'bool', '1', false],
            ['features.alert_after_hours', 'features', 'عتبة التنبيه (ساعات)', 'number', '24', false],
            // «شارة تجريبيّة للمزايا **الجديدة**» — والجِدَّة عمرٌ لا وسمٌ يدويّ
            ['features.beta_days', 'features', 'كم يوم تُعتبَر الميزة جديدة', 'number', '30', false],

            // ---------------- لافتات المجموعات التسع المنصوصة
            ['features.groups', 'features', 'لافتات مجموعات المزايا', 'json', '{"training":"تدريب","gamification":"تلعيب","wars":"حروب","store":"متجر وماليّات","library":"مكتبة","volunteer":"تطوّع","events":"فعاليّات","guidance":"توجيه","profile":"بروفايل"}', false],

            // ---------------- حدود وسلوك
            ['features.audit.limit', 'features', 'عدد صفوف سجلّ الميزة', 'number', '30', false],
            ['features.notify.max_recipients', 'features', 'أقصى عدد من يصلهم إشعار الإيقاف', 'number', '500', false],
            ['features.notify.category', 'features', 'فئة إشعار إيقاف الميزة', 'string', 'system', false],
            ['features.notify.title', 'features', 'عنوان إشعار إيقاف الميزة', 'string', 'ميزة وقفت مؤقّتًا', false],

            // ---------------- رسائل الشاشة (2.17-ب: ماذا حدث + ماذا تفعل)
            ['features.msg.saved', 'features', 'رسالة الحفظ', 'string', 'اتحفظ ✓', false],
            ['features.msg.unknown_feature', 'features', 'رسالة ميزة غير موجودة', 'text', 'الميزة دي مش موجودة — حدّث الصفحة وجرّب تاني.', false],
            ['features.msg.reason_required', 'features', 'رسالة سبب الإيقاف الناقص', 'text', 'اكتب سبب الإيقاف — هو اللي هيفضل في السجلّ ويفهّم اللي بعدك.', false],
            ['features.msg.bad_scope', 'features', 'رسالة نطاق غير صالح', 'text', 'النطاق ده مش مظبوط — اختر دورًا أو شريحة موجودة.', false],
            ['features.msg.reset_all', 'features', 'رسالة Reset الكلّ', 'text', 'رجّعنا :count ميزة لوضعها الافتراضيّ.', false],
            ['features.msg.imported', 'features', 'رسالة الاستيراد', 'text', 'اتطبّق :applied مفتاح · اتخطّى :skipped.', false],
            ['features.msg.bad_json', 'features', 'رسالة ملفّ JSON غير صالح', 'text', 'الملفّ مش JSON صالح — صدّر نسخة وقارن الشكل.', false],
            ['features.msg.audit_missing_key', 'features', 'رسالة سجلّ بلا مفتاح ميزة', 'text', 'مافيش مفتاح ميزة في الطلب — افتح السجلّ من جنب الميزة نفسها.', false],

            // ---------------- نصوص الشاشة (24.3) — كلّها إعدادات لا حروف محروقة
            ['features.ui.purpose', 'features', 'غرض الشاشة', 'text', 'إطفاء أو تشغيل أيّ ميزة بلا نشر كود — وده البديل الوحيد للصيانة الجزئيّة الملغاة.', false],
            ['features.ui.pinned_rule', 'features', 'القاعدة المثبّتة', 'text', 'لا صيانة جزئيّة لميزة بعينها — أُلغيت؛ الإطفاء يتمّ من هنا فقط.', false],
            ['features.ui.save', 'features', 'زرّ حفظ', 'string', 'حفظ', false],
            ['features.ui.export', 'features', 'زرّ تصدير JSON', 'string', 'تصدير JSON', false],
            ['features.ui.import', 'features', 'زرّ استيراد JSON', 'string', 'استيراد JSON', false],
            ['features.ui.reset_all', 'features', 'زرّ Reset الكلّ', 'string', '↺ Reset الكلّ', false],
            ['features.ui.paused_badge', 'features', 'شارة عدد الموقوفة', 'string', 'موقوفة: :count', false],
            ['features.ui.file_label', 'features', 'لافتة ملفّ الاستيراد', 'string', 'ملفّ مفاتيح JSON', false],
            ['features.ui.long_outage', 'features', 'تنبيه الإيقاف الطويل', 'text', 'فيه :count ميزة موقوفة من أكتر من :hours ساعة — راجعها.', false],

            ['features.ui.filter_search', 'features', 'لافتة البحث', 'string', 'دوّر بالاسم أو بالمفتاح…', false],
            ['features.ui.filter_group', 'features', 'لافتة فلتر المجموعة', 'string', 'المجموعة', false],
            ['features.ui.filter_status', 'features', 'لافتة فلتر الحالة', 'string', 'الحالة', false],
            ['features.ui.filter_all', 'features', 'خيار الكلّ في الفلاتر', 'string', 'الكلّ', false],
            ['features.ui.filter_apply', 'features', 'زرّ تطبيق الفلاتر', 'string', 'فلترة', false],

            ['features.ui.status.on', 'features', 'حالة مشتغّل', 'string', 'مشتغّل', false],
            ['features.ui.status.off', 'features', 'حالة موقوف', 'string', 'موقوف', false],
            ['features.ui.status.partial', 'features', 'حالة جزئيّ', 'string', 'جزئيّ', false],
            ['features.ui.beta', 'features', 'شارة تجريبيّة', 'string', 'تجريبيّة', false],

            ['features.ui.col.feature', 'features', 'عمود الميزة', 'string', 'الميزة', false],
            // ⛔ 'features.ui.col.key' حُذفت (ولها هجرة حذفٍ من settings) — الجدول
            // لا عمود «مفتاح» مستقلًّا فيه أصلًا (يظهر تحت اسم الميزة، انظر تعليق
            // `features.blade.php`)، فلم يقرأ أحد هذا العنوان قطّ.
            ['features.ui.col.group', 'features', 'عمود المجموعة', 'string', 'المجموعة', false],
            ['features.ui.col.toggle', 'features', 'عمود التبديل', 'string', 'تشغيل/إيقاف', false],
            ['features.ui.col.scope', 'features', 'عمود النطاق', 'string', 'النطاق', false],
            ['features.ui.col.visible', 'features', 'عمود مَن يراها أثناء الإيقاف', 'string', 'مين يشوفها وهي موقوفة', false],
            ['features.ui.col.last', 'features', 'عمود آخر تبديل', 'string', 'آخر تبديل', false],
            ['features.ui.col.actions', 'features', 'عمود الإجراءات', 'string', 'إجراءات', false],

            ['features.ui.scope.global', 'features', 'نطاق عامّ', 'string', 'عامّ', false],
            ['features.ui.scope.role', 'features', 'نطاق دور', 'string', 'Override لدور', false],
            ['features.ui.scope.segment', 'features', 'نطاق شريحة', 'string', 'Override لشريحة', false],
            ['features.ui.scope.manage', 'features', 'زرّ ضبط النطاق', 'string', 'اضبط النطاق', false],
            ['features.ui.scope.title', 'features', 'عنوان بوب-أب النطاق', 'string', 'نطاق الميزة', false],
            ['features.ui.scope.type', 'features', 'لافتة نوع النطاق', 'string', 'النوع', false],
            ['features.ui.scope.target', 'features', 'لافتة هدف النطاق', 'string', 'الدور أو الشريحة', false],
            ['features.ui.scope.value', 'features', 'لافتة قرار النطاق', 'string', 'القرار داخل النطاق', false],
            ['features.ui.scope.value_on', 'features', 'قرار تشغيل داخل النطاق', 'string', 'شغّالة', false],
            ['features.ui.scope.value_off', 'features', 'قرار إيقاف داخل النطاق', 'string', 'موقوفة', false],
            ['features.ui.scope.clear', 'features', 'رفع الـOverride', 'string', 'ارفع الـOverride (رجّعها عامّة)', false],
            ['features.ui.scope.empty', 'features', 'لا Override', 'string', 'مافيش Override — الميزة عامّة.', false],

            ['features.ui.visibility.none', 'features', 'يراها أثناء الإيقاف: لا أحد', 'string', 'لا أحد', false],
            ['features.ui.visibility.admins', 'features', 'يراها أثناء الإيقاف: الأدمن', 'string', 'الأدمن فقط', false],
            ['features.ui.visibility.roles', 'features', 'يراها أثناء الإيقاف: أدوار', 'string', 'أدوار محدّدة', false],

            ['features.ui.action.details', 'features', 'إجراء التفاصيل', 'string', 'تفاصيل', false],
            ['features.ui.action.audit', 'features', 'إجراء السجلّ', 'string', 'Audit', false],
            ['features.ui.action.reset', 'features', 'إجراء الإرجاع', 'string', '↺', false],
            ['features.ui.action.turn_off', 'features', 'إجراء الإيقاف', 'string', 'أوقف', false],
            ['features.ui.action.turn_on', 'features', 'إجراء التشغيل', 'string', 'شغّل', false],

            ['features.ui.popup.disable_title', 'features', 'عنوان بوب-أب الإيقاف', 'string', 'إيقاف ميزة', false],
            ['features.ui.popup.confirm', 'features', 'نصّ تأكيد الإيقاف', 'text', 'الميزة دي هتتقفل على كلّ اللي في نطاقها فورًا. متأكّد؟', false],
            ['features.ui.popup.message_ar', 'features', 'لافتة النصّ البديل العربيّ', 'string', 'اللي المستخدم هيشوفه بدلها (عربيّ)', false],
            ['features.ui.popup.message_en', 'features', 'لافتة النصّ البديل الإنجليزيّ', 'string', 'اللي المستخدم هيشوفه بدلها (إنجليزيّ)', false],
            ['features.ui.popup.notify', 'features', 'لافتة إشعار المتأثّرين', 'string', 'ابعت إشعار للمتأثّرين', false],
            ['features.ui.popup.reason', 'features', 'لافتة سبب الإيقاف', 'string', 'سبب الإيقاف (بيدخل الـAudit)', false],
            ['features.ui.popup.behavior', 'features', 'لافتة سلوك الميزة الموقوفة', 'string', 'سلوك الميزة الموقوفة', false],
            ['features.ui.popup.behavior_inherit', 'features', 'خيار السلوك الافتراضيّ', 'string', 'زيّ الإعداد العامّ', false],
            ['features.ui.popup.behavior_hide', 'features', 'خيار الإخفاء الكامل', 'string', 'إخفاء كامل', false],
            ['features.ui.popup.behavior_message', 'features', 'خيار إظهار الرسالة', 'string', 'إظهار رسالة', false],
            ['features.ui.popup.visibility', 'features', 'لافتة مَن يراها أثناء الإيقاف', 'string', 'مين يشوفها وهي موقوفة', false],
            ['features.ui.popup.visible_roles', 'features', 'لافتة الأدوار المستثناة', 'string', 'الأدوار اللي هتفضل شايفاها', false],
            ['features.ui.popup.submit', 'features', 'زرّ تأكيد الإيقاف', 'string', 'أوقف الميزة', false],
            ['features.ui.popup.cancel', 'features', 'زرّ الإلغاء', 'string', 'إلغاء', false],
            ['features.ui.popup.details_title', 'features', 'عنوان بوب-أب التفاصيل', 'string', 'تفاصيل الميزة', false],
            ['features.ui.popup.routes', 'features', 'لافتة مسارات الميزة', 'string', 'المسارات اللي بيحكمها المفتاح', false],
            ['features.ui.popup.audit_title', 'features', 'عنوان بوب-أب السجلّ', 'string', 'سجلّ الميزة', false],
            ['features.ui.popup.audit_empty', 'features', 'سجلّ فارغ', 'string', 'مافيش تبديل مسجَّل لسه.', false],

            ['features.ui.state.empty', 'features', 'الحالة الفارغة', 'text', 'مافيش مزايا في الفلتر ده — وسّع الفلتر شويّة.', false],
            ['features.ui.state.loading', 'features', 'حالة التحميل', 'string', 'بنحمّل…', false],
            ['features.ui.state.error', 'features', 'حالة الخطأ', 'text', 'حصل خطأ وإحنا بنحفظ — جرّب تاني، ولو فضل زيّه بلّغ التقنيّ.', false],
            ['features.ui.state.denied', 'features', 'حالة بلا صلاحيّة', 'text', 'مالكش صلاحيّة على مفاتيح المزايا — كلّم مالك المنصّة لو محتاج وصولًا.', false],
            ['features.ui.never', 'features', 'لا تبديل بعد', 'string', 'لسه ما اتبدّلتش', false],
            ['features.ui.settings_title', 'features', 'عنوان بلوك الإعدادات', 'string', 'إعدادات المفاتيح', false],

            ['features.ui.unavailable.title', 'features', 'عنوان صفحة الميزة الموقوفة', 'string', 'الميزة دي واقفة دلوقتي', false],
            ['features.ui.unavailable.back', 'features', 'زرّ العودة من صفحة الميزة الموقوفة', 'string', 'ارجع للرئيسيّة', false],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default, $ownerOnly]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => Setting::query()->where('key', $key)->value('value') ?? $default,
                'is_sensitive' => false,
                'is_owner_only' => $ownerOnly,
            ]);
        }

        Cache::forget('settings');

        $this->featureFlags();
    }

    /**
     * صفوف المزايا نفسها — **مربوطةٌ بمسارات حقيقيّة** عبر `FeatureCatalog`.
     *
     * والتحديث لا يدهس قرار المالك: اللافتة والمجموعة تُحدَّثان، أمّا `enabled`
     * و`visibility` و`message_*` فهي قيمُه هو — وإعادةُ الزرع لا تفتح ميزةً أطفأها.
     */
    private function featureFlags(): void
    {
        // قبل الترحيل لا جدولَ مزايا — والتعريفُ لا يجوز أن يكسر التنصيب
        if (! Schema::hasTable('feature_flags')) {
            return;
        }

        // [key => الاسم العربيّ] — واللافتة في القاعدة لا في صنف PHP (2.13)
        $labels = [
            'training.courses' => 'التدريبات والمسارات',
            'training.lessons' => 'الدروس',
            'training.lesson_comments' => 'تعليقات الدروس',
            'training.exams' => 'الامتحانات',
            'training.certificates' => 'الشهادات وتحميلها',
            'gamification.leaderboard' => 'لوحة المتصدّرين',
            'gamification.streak' => 'السلسلة (Streak)',
            'gamification.badges' => 'الشارات',
            'gamification.reward_questions' => 'سؤال المكافأة',
            'gamification.kudos' => 'الكودوز وحائط الشكر',
            'wars.board' => 'لوحة الحروب',
            'wars.focus' => 'حرب التركيز',
            'wars.matches' => 'المواجهات والساحة',
            'store.storefront' => 'المتجر',
            'store.wallet' => 'المحفظة',
            'store.topup' => 'شحن الرصيد',
            'store.transfer' => 'التحويل بين المحافظ',
            'store.withdraw' => 'السحب',
            'store.exchange' => 'تبديل العملات',
            'library.reader' => 'المكتبة والقارئ',
            'library.cv' => 'السيرة الذاتيّة',
            'library.attestations' => 'الإفادات',
            'volunteer.panel' => 'لوحة التطوّع',
            'volunteer.meetings' => 'اجتماعات التطوّع',
            'volunteer.tasks' => 'مهامّ التطوّع',
            'volunteer.internal_library' => 'المكتبة الداخليّة',
            'events.public' => 'الفعاليّات',
            'guidance.announcements' => 'الإعلانات والتعليمات',
            'guidance.help' => 'مركز المساعدة',
            'guidance.complaints' => 'الشكاوى',
            'guidance.notifications' => 'مركز الإشعارات',
            'profile.public' => 'البروفايل العامّ',
            'profile.card' => 'كارت التعريف',
            'profile.search' => 'البحث في المنصّة',
        ];

        foreach (FeatureCatalog::definitions() as $key => $definition) {
            $existing = DB::table('feature_flags')->where('key', $key)->first();

            if ($existing) {
                DB::table('feature_flags')->where('id', $existing->id)->update([
                    'group' => $definition['group'],
                    'label_ar' => $labels[$key] ?? $key,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('feature_flags')->insert([
                'key' => $key,
                'group' => $definition['group'],
                'label_ar' => $labels[$key] ?? $key,
                'enabled' => true,
                'is_beta' => false,
                'visibility' => 'none',
                'behavior' => '',
                'notify_affected' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        FeatureGate::forget();
    }

    // ---------------------------------------------------------------- بيانات تجريبيّة

    private function store(): void
    {
        $category = ProductCategory::updateOrCreate(
            ['slug' => 'digital-books'],
            ['name_ar' => 'كتب رقميّة', 'sort_order' => 1, 'is_active' => true],
        );

        Product::updateOrCreate(
            ['slug' => 'dalil-almutatawwi'],
            [
                'product_category_id' => $category->id,
                'name_ar' => 'دليل المتطوّع العمليّ',
                'description' => 'كتيّب مختصر لكلّ مَن يبدأ رحلة التطوّع.',
                'type' => 'protected_pdf',
                'is_downloadable' => false,
                'teaser_pages' => 5,
                'price_coins' => 350,
                'status' => 'published',
            ],
        );

        Product::updateOrCreate(
            ['slug' => 'mulakhkhas-almusar'],
            [
                'product_category_id' => $category->id,
                'name_ar' => 'ملخّص المسار التأهيليّ',
                'type' => 'digital',
                'is_downloadable' => true,
                'price_coins' => 120,
                'status' => 'published',
            ],
        );

        Bundle::updateOrCreate(
            ['slug' => 'baqat-albidaya'],
            [
                'name_ar' => 'باقة البداية',
                'description' => 'كلّ ما تحتاجه في أوّل شهر.',
                'price_coins' => 400,
                'original_value' => 620,
                'status' => 'published',
            ],
        );

        Coupon::updateOrCreate(
            ['code' => 'AHLAN10'],
            [
                'type' => 'percent',
                'value' => 10,
                'max_uses' => 200,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ],
        );
    }

    private function topup(): void
    {
        $methods = [
            ['bank', 'حساب بنكيّ — بنك القاهرة', '1234567890123', 'مؤسّسة المنصّة'],
            ['wallet', 'محفظة موبايل', '01000000000', 'المنصّة'],
            ['instapay', 'إنستا باي', 'platform@instapay', 'المنصّة'],
        ];

        foreach ($methods as $index => [$type, $name, $account, $beneficiary]) {
            TransferMethod::updateOrCreate(
                ['name_ar' => $name],
                [
                    'type' => $type,
                    'account_number' => $account,
                    'beneficiary_name' => $beneficiary,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }

        // ⭐ العرض يعرض قيمته الحقيقيّة صراحةً — بلا مبالغة وبلا Dark Patterns (2.9)
        $offers = [
            ['manual', 'باقة 100', 100, 100, false],
            ['manual', 'باقة 500', 500, 550, true],
            ['manual', 'باقة 1000', 1000, 1150, false],
            ['gateway', 'باقة البوّابة 500', 500, 540, true],
        ];

        foreach ($offers as $index => [$method, $label, $pay, $credit, $popular]) {
            TopupOffer::updateOrCreate(
                ['label_ar' => $label],
                [
                    'method' => $method,
                    'pay_amount' => $pay,
                    'credit_amount' => $credit,
                    'bonus_percent' => $pay > 0 ? round((($credit - $pay) / $pay) * 100, 2) : 0,
                    'is_popular' => $popular,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }

    private function studio(): void
    {
        ImageTemplate::updateOrCreate(
            ['name' => 'كارت الإنجاز — مربّع'],
            [
                'purpose' => 'achievement',
                'width_px' => 1080,
                'height_px' => 1080,
                'preset' => 'square',
                'audience' => 'everyone',
                'is_active' => true,
                // الحقول كلّها من القائمة المقفولة — ولا حقل ممنوع أصلًا (12.14-د)
                'layers' => [
                    ['type' => 'avatar', 'name' => 'صورة المستخدم', 'x' => 400, 'y' => 180, 'w' => 280, 'h' => 280,
                        'shape' => 'circle', 'fit' => 'cover', 'rotate' => 0, 'visible' => true, 'locked' => false],
                    ['type' => 'text', 'name' => 'الاسم', 'field' => 'short_name', 'text' => '', 'x' => 540, 'y' => 520,
                        'size' => 56, 'color' => '#ffffff', 'align' => 'center', 'max_chars' => 24,
                        'overflow' => 'shrink', 'rotate' => 0, 'visible' => true, 'locked' => false],
                    ['type' => 'text', 'name' => 'الكود', 'field' => 'code', 'text' => '', 'x' => 540, 'y' => 600,
                        'size' => 32, 'color' => '#00d4b8', 'align' => 'center', 'max_chars' => 16,
                        'overflow' => 'truncate', 'rotate' => 0, 'visible' => true, 'locked' => false],
                    ['type' => 'text', 'name' => 'المحافظة', 'field' => 'governorate', 'text' => '', 'x' => 540, 'y' => 660,
                        'size' => 28, 'color' => '#9fb3c8', 'align' => 'center', 'max_chars' => 24,
                        'overflow' => 'shrink', 'rotate' => 0, 'visible' => true, 'locked' => false],
                ],
            ],
        );
    }

    private function articles(): void
    {
        $category = ArticleCategory::updateOrCreate(
            ['slug' => 'tatawwu'],
            ['name_ar' => 'التطوّع', 'sort_order' => 1],
        );

        $author = User::query()->orderBy('id')->first();

        if (! $author) {
            return;
        }

        Article::updateOrCreate(
            ['slug' => 'kayfa-tabda-tatawwuak'],
            [
                'article_category_id' => $category->id,
                'author_id' => $author->id,
                'title' => 'إزاي تبدأ تطوّعك صحّ من أوّل يوم',
                'excerpt' => 'خطوات عمليّة تخلّي أوّل شهر ليك في التطوّع مثمر ومريح.',
                'body' => '<p>ابدأ بخطوة صغيرة، وحدّد وقتك، واسأل من سبقك.</p>',
                'meta_title' => 'إزاي تبدأ تطوّعك صحّ',
                'meta_description' => 'دليل عمليّ لأوّل شهر في التطوّع.',
                // ⭐ تبدأ مسودّةً دائمًا — والنشر بيد شخص آخر (21.2-أ)
                'status' => 'draft',
            ],
        );
    }

    private function ads(): void
    {
        AdAudience::updateOrCreate(
            ['name' => 'فتح صفحة تدريب ولم يسجّل'],
            [
                'kind' => 'retargeting',
                'rule' => ['key' => 'viewed_course_not_registered'],
                'ttl_days' => 30,
                'refresh_hours' => 24,
                'is_active' => true,
            ],
        );
    }
}
