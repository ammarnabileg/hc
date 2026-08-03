<?php

namespace Database\Seeders;

use App\Models\AdAudience;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Currency;
use App\Models\Referral;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AudienceSegments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * مجال «admin-core»: ليَاوت لوحة الإدارة والقيادة والمستخدمون والأدوار (12.0 · 12.3 · 12.13 · 12.2).
 *
 * ⛔ ولا مفتاح باسم شاشة هنا: كان السيدر ينشئ `admin_panel.view` بوّابةً للسايد
 * بار، وهي **صلاحيّة باسم شاشة** يمنعها 12.2.1-أ نصًّا. الباب صار قدرةً محسوبة
 * («له أيّ صلاحيّة إداريّة» — `App\Support\Access\AdminPanelSurface`).
 *
 *  1) إعدادات المجال (2.13) — فلا رقم ولا نصّ محروق في الكود.
 *  2) بيانات تجريبيّة عربيّة واقعيّة لتجربة الشاشات.
 */
class AdminCoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->demoAccounts();
        $this->demoPendingWork();
        $this->demoSegments();
        $this->demoAudit();
    }

    /** لكلّ ميزة إعدادات كاملة (2.13) — بنمط المفتاح «المجال.الميزة.المفتاح» */
    public function settings(): void
    {
        $rows = [
            // ---------------- لوحة القيادة (12.3 · 24.1)
            ['admin.dashboard.default_days', 'admin_dashboard', 'المدى الافتراضيّ عند الفتح (أيّام)', 'number', '30'],
            ['admin.dashboard.range_options', 'admin_dashboard', 'خيارات فلتر الفترة (أيّام)', 'json', '[1,7,30,90]'],
            ['admin.dashboard.compare_previous', 'admin_dashboard', 'المقارنة بالفترة السابقة', 'bool', '1'],
            ['admin.dashboard.health_colors', 'admin_dashboard', 'تلوين صحّة المؤشّر', 'bool', '1'],
            ['admin.dashboard.kpi_max_cards', 'admin_dashboard', 'أقصى كروت KPI في الصفّ', 'number', '4'],
            ['admin.dashboard.kpi_green_percent', 'admin_dashboard', 'عتبة اللون الأخضر (% تغيّر)', 'number', '5'],
            ['admin.dashboard.kpi_red_percent', 'admin_dashboard', 'عتبة اللون الأحمر (% تغيّر)', 'number', '-5'],
            ['admin.dashboard.refresh_seconds', 'admin_dashboard', 'فترة التحديث التلقائيّ (ثوانٍ)', 'number', '120'],
            ['admin.dashboard.withdraw_late_hours', 'admin_dashboard', 'عتبة تأخّر طلب السحب (ساعات)', 'number', '48'],
            ['admin.dashboard.approval_late_days', 'admin_dashboard', 'عتبة تأخّر اعتماد الحساب (أيّام)', 'number', '2'],
            ['admin.dashboard.approvals_preview_rows', 'admin_dashboard', 'صفوف كارت «حسابات محتاجة موافقة»', 'number', '5'],
            ['admin.dashboard.pending_preview_rows', 'admin_dashboard', 'صفوف جدول «المهامّ المعلّقة»', 'number', '6'],
            ['admin.dashboard.activity_rows', 'admin_dashboard', 'صفوف سجلّ النشاطات', 'number', '10'],
            ['admin.dashboard.top_rows', 'admin_dashboard', 'صفوف جداول «أعلى 10»', 'number', '10'],
            ['admin.dashboard.title', 'admin_dashboard', 'عنوان لوحة القيادة', 'string', 'لوحة القيادة'],
            ['admin.dashboard.subtitle', 'admin_dashboard', 'سطر شرح لوحة القيادة', 'string', 'حالة المنصّة والقرارات المستنّياك'],
            ['admin.dashboard.empty_message', 'admin_dashboard', 'نصّ الحالة الفارغة', 'string', 'مفيش بيانات في الفترة دي — وسّع المدى'],
            ['admin.dashboard.alert_withdraw', 'admin_dashboard', 'نصّ تنبيه السحوبات المتأخّرة', 'string', 'في :count طلب سحب فات عليه :hours ساعة'],
            ['admin.dashboard.alert_approvals', 'admin_dashboard', 'نصّ تنبيه الاعتمادات المتأخّرة', 'string', 'في :count حساب مستنّي اعتماد من :days يوم'],

            // ---------------- التحديث التلقائيّ ومؤشّر «آخر تحديث» (12.3-5)
            ['admin.dashboard.auto_refresh', 'admin_dashboard', 'التحديث التلقائيّ للأرقام اللحظيّة', 'bool', '1'],

            // ---------------- تخصيص اللوحة لكلّ دور (12.3-3)
            ['admin.dashboard.role_layouts', 'admin_dashboard', 'تخصيص كروت لوحة القيادة لكلّ دور', 'json', '{}'],
            ['admin.dashboard.default_layout_role', 'admin_dashboard', 'الدور الافتراضيّ لحفظ التخصيص', 'string', 'platform_owner'],
            ['admin.dashboard.customize_hint', 'admin_dashboard', 'سطر شرح بوب-أب تخصيص اللوحة', 'string', 'رتّب الكروت بالسحب، وشيل اللي مش محتاجه — والترتيب ده بيتحفظ لدورك أنت.'],
            ['admin.dashboard.layout_saved_text', 'admin_dashboard', 'نصّ حفظ تخصيص اللوحة', 'string', 'اتحفظ ✓ — ترتيب اللوحة للدور ده اتسجّل.'],

            // ---------------- 🔒 الهدف الشهريّ (12.3-10)
            ['admin.dashboard.monthly_target', 'admin_dashboard', 'الهدف الشهريّ للإيرادات (كوينز)', 'number', '0'],
            ['admin.dashboard.target_label', 'admin_dashboard', 'تسمية الهدف الشهريّ', 'string', 'الهدف الشهريّ'],
            ['admin.dashboard.target_empty_text', 'admin_dashboard', 'نصّ غياب الهدف الشهريّ', 'string', 'ماحدّدتش هدفًا للشهر لسّه — اضبطه من إعدادات لوحة القيادة.'],

            // ---------------- الخريطة الحراريّة وسجلّ النشاطات (12.3-13 · 12.3-20)
            ['admin.dashboard.geo_rows', 'admin_dashboard', 'صفوف الخريطة الحراريّة الجغرافيّة', 'number', '8'],
            ['admin.dashboard.activity_export_rows', 'admin_dashboard', 'أقصى صفوف في ملفّ تصدير سجلّ النشاطات', 'number', '5000'],
            ['admin.dashboard.activity_export_label', 'admin_dashboard', 'نصّ زرّ تصدير سجلّ النشاطات', 'string', 'تصدير السجلّ'],

            // ---------------- قائمة المستخدمين (24.1)
            ['admin.users.per_page', 'admin_users', 'عدد صفوف الجدول', 'number', '25'],
            ['admin.users.default_columns', 'admin_users', 'الأعمدة الافتراضيّة للجدول', 'json', '["name","code","email","status","roles","last_seen"]'],
            ['admin.users.mask_sensitive', 'admin_users', 'تقنيع البريد والموبايل', 'bool', '1'],
            ['admin.users.empty_message', 'admin_users', 'نصّ الحالة الفارغة', 'string', 'مفيش نتائج — امسح الفلاتر وجرّب تاني'],
            // صفحة حساب المستخدم (12.1): طول جداول التابات وتاب التطوّع وصيغة ملفّ التصدير
            ['admin.users.tab_rows', 'admin_users', 'عدد صفوف جداول تابات صفحة المستخدم', 'number', '25'],
            ['admin.user_tabs.volunteer_permission', 'admin_users', 'صلاحيّة إظهار تاب التطوّع', 'string', 'memberships.view'],
            ['admin.users.export_filename', 'admin_users', 'اسم ملفّ تصدير بيانات المستخدم', 'string', 'user-{code}-{date}.json'],
            ['admin.users.export_rows', 'admin_users', 'أقصى صفوف لكلّ جدول في ملفّ التصدير', 'number', '500'],
            ['admin.users.referral_gift_note', 'admin_users', 'سطر شرح توقيت صرف هديّة الدعوة', 'string', 'الهديّة بتتصرف للطرفين بعد قبول الحساب — مش وقت التسجيل.'],
            ['admin.users.sessions_hint', 'admin_users', 'شرح الجلسات النشطة', 'string', 'الأجهزة المفتوح عليها الحساب دلوقتي — وإنهاء الجلسات بيقفلها كلّها.'],
            ['admin.users.country_pin_hint', 'admin_users', 'شرح تثبيت الدولة يدويًّا', 'string', 'الكشف التلقائيّ بيتبع مكانه دلوقتي — والتثبيت اليدويّ بيعلو عليه ومابيتدهسش.'],
            ['admin.users.notes_hint', 'admin_users', 'شرح الملاحظات الإداريّة الداخليّة', 'string', 'ملاحظات للفريق فقط — المستخدم مابيشوفهاش أبدًا.'],

            // ---------------- طلبات الاعتماد (2.5-د)
            ['admin.approvals.bulk_max', 'admin_approvals', 'حدّ الاعتماد المجمّع في العمليّة الواحدة', 'number', '50'],
            ['admin.approvals.notify_user', 'admin_approvals', 'إشعار المستخدم عند القبول/الرفض', 'bool', '1'],
            ['admin.approvals.default_role', 'admin_approvals', 'الدور الممنوح عند القبول', 'string', 'trainee'],
            ['admin.approvals.grant_referral_gift', 'admin_approvals', 'صرف هديّة الريفيرال بعد قبول الحساب', 'bool', '1'],
            ['admin.approvals.welcome_tickets', 'admin_approvals', 'تذاكر ترحيب المدعوّ', 'number', '1'],
            ['admin.approvals.reject_reasons', 'admin_approvals', 'أسباب الرفض', 'json', '["بيانات ناقصة أو غير واضحة","الاسم غير مطابق للمستندات","تكرار حساب قائم","خارج الشريحة المستهدَفة"]'],
            ['admin.approvals.accept_message', 'admin_approvals', 'نصّ إشعار القبول', 'string', 'تمّ قبول حسابك — أهلًا بيك معانا 🎉'],
            ['admin.approvals.reject_message', 'admin_approvals', 'نصّ إشعار الرفض', 'string', 'حسابك محتاج مراجعة: :reason'],
            ['admin.approvals.empty_message', 'admin_approvals', 'نصّ الحالة الفارغة', 'string', 'مفيش طلبات معلّقة — كلّ حاجة تمام'],
            ['admin.approvals.free_note', 'admin_approvals', 'سطر تذكير مجّانيّة التفعيل', 'string', 'التفعيل مجّانيّ باعتماد إداريّ — ولا رسوم على الباب'],

            // ---------------- شرائح الجمهور (12.13 · 24.1)
            ['admin.segments.preview_rows', 'admin_segments', 'صفوف معاينة أعضاء الشريحة', 'number', '10'],
            ['admin.segments.max_members', 'admin_segments', 'أقصى عدد أعضاء للشريحة', 'number', '50000'],
            ['admin.segments.empty_message', 'admin_segments', 'نصّ الحالة الفارغة', 'string', 'ابنِ شريحتك الأولى'],
            // Toggle: الديناميكيّة · الأرشفة بدل الحذف · عيّنة الأعضاء في المعاينة
            ['admin.segments.dynamic_enabled', 'admin_segments', 'تفعيل الشرائح الديناميكيّة', 'bool', '1'],
            ['admin.segments.block_delete_when_used', 'admin_segments', 'منع حذف شريحة مستخدَمة (أرشفة بدلها)', 'bool', '1'],
            ['admin.segments.show_member_sample', 'admin_segments', 'إظهار عيّنة الأعضاء في المعاينة', 'bool', '1'],
            // قيم: فترة التحديث · صفوف المعاينة والقوائم · حدود الباني
            ['admin.segments.refresh_hours', 'admin_segments', 'فترة تحديث الشريحة الديناميكيّة (ساعات)', 'number', '24'],
            ['admin.segments.per_page', 'admin_segments', 'عدد الشرائح في الصفحة', 'number', '20'],
            ['admin.segments.members_rows', 'admin_segments', 'صفوف شاشة أعضاء الشريحة', 'number', '50'],
            ['admin.segments.option_rows', 'admin_segments', 'أقصى خيارات في قوائم باني المعايير', 'number', '50'],
            ['admin.segments.usage_rows', 'admin_segments', 'أقصى أماكن استخدام تُفحَص للشريحة', 'number', '10'],
            ['admin.segments.max_groups', 'admin_segments', 'أقصى مجموعات في باني المعايير', 'number', '2'],
            ['admin.segments.max_conditions', 'admin_segments', 'أقصى شروط في المجموعة الواحدة', 'number', '8'],
            ['admin.segments.tickets_currency', 'admin_segments', 'رمز عملة التذاكر في معيار النطاق', 'string', 'tickets'],
            // نصوص: تسميات المعايير (12.13) · تحذير الحذف · شروح الشاشة
            ['admin.segments.criteria_labels', 'admin_segments', 'تسميات معايير الشريحة', 'json', json_encode([
                'path' => 'المسار',
                'course' => 'التدريب',
                'role' => 'الدور',
                'country' => 'الدولة',
                'governorate' => 'المحافظة',
                'status' => 'حالة الحساب',
                'xp' => 'نطاق XP',
                'tickets' => 'نطاق التذاكر',
                'last_active_days' => 'آخر نشاط خلال (يوم)',
                'registered_days' => 'مسجَّل خلال (يوم)',
            ], JSON_UNESCAPED_UNICODE)],
            ['admin.segments.types', 'admin_segments', 'أنواع الشرائح', 'json', json_encode([
                'dynamic' => 'ديناميكيّة (تُحدَّث تلقائيًّا)',
                'static' => 'ثابتة (تُجمَّد الآن)',
            ], JSON_UNESCAPED_UNICODE)],
            ['admin.segments.match_modes', 'admin_segments', 'منطق ربط الشروط', 'json', json_encode([
                'all' => 'كلّ الشروط (AND)',
                'any' => 'أيّ شرط (OR)',
            ], JSON_UNESCAPED_UNICODE)],
            ['admin.segments.delete_warning', 'admin_segments', 'نصّ تحذير حذف شريحة مستخدَمة', 'string', 'الشريحة دي مستخدَمة في :count مكان — أرشفها بدل ما تمسحها.'],
            ['admin.segments.duplicate_suffix', 'admin_segments', 'لاحقة اسم النسخة المكرّرة', 'string', ' — نسخة'],
            ['admin.segments.summary_all', 'admin_segments', 'ملخّص الشريحة بلا شروط', 'string', 'كلّ المستخدمين'],
            ['admin.segments.type_hint', 'admin_segments', 'سطر شرح نوع الشريحة', 'string', 'الديناميكيّة بتتحدّث لوحدها مع تغيّر البيانات، والثابتة بتتجمّد على أعضائها دلوقتي.'],
            ['admin.segments.preview_hint', 'admin_segments', 'سطر شرح حصر المعاينة بالنطاق', 'string', 'المعاينة بتتحسب في حدود نطاقك أنت — مش في المنصّة كلّها.'],
            ['admin.segments.members_empty', 'admin_segments', 'نصّ خلوّ الشريحة من الأعضاء', 'string', 'مافيش أعضاء مطابقين دلوقتي'],

            // ---------------- الأدوار والصلاحيّات (12.2)
            ['admin.roles.default_scope', 'admin_roles', 'النطاق الافتراضيّ للصلاحيّة الجديدة', 'string', 'SELF'],
            ['admin.roles.rows_per_group', 'admin_roles', 'أقصى صفوف تُعرَض من المجموعة', 'number', '400'],
            ['admin.roles.audit_hover_delay_ms', 'admin_roles', 'تأخير إظهار آخر تغيير بالـHover (ملّي ثانية)', 'number', '200'],
            ['admin.roles.escalation_message', 'admin_roles', 'نصّ رسالة منع تصعيد الامتياز', 'string', 'مقدرناش نحفظ «:permission» بنطاق :scope — مفيش حدّ يمنح صلاحيّة لا يملكها ولا نطاقًا أوسع من نطاقه. اطلبها من أدمن أعلى منك أو صغّر النطاق.'],
            ['admin.roles.deny_message', 'admin_roles', 'نصّ قاعدة المنع يغلب الإذن', 'string', 'المنع يغلب الإذن: لو الصلاحيّة ممنوعة من أيّ مصدر، المنع يكسب.'],
            ['admin.roles.owner_only_note', 'admin_roles', 'سطر عزل المجموعة المحميّة', 'string', 'المجموعة المحميّة (الماليّ والأسرار) لمالك المنصّة وحده ولا تظهر لغيره.'],
            ['admin.roles.protected_message', 'admin_roles', 'نصّ منع حذف الدور المحميّ', 'string', 'دور مالك المنصّة ثابت نظاميّ — لا يُحذَف ولا يُنسَخ عنه الحذف.'],
            ['admin.roles.assign_hint', 'admin_roles', 'سطر شرح إسناد الدور داخل عضويّة', 'string', 'الدور يحدّد «ماذا» والعضويّة تحدّد «أين» — فأدوار التطوّع تُسنَد داخل عضويّة.'],
            ['admin.roles.empty_message', 'admin_roles', 'نصّ الحالة الفارغة', 'string', 'مفيش دور مخصّص لسّه — ابدأ بنسخ قالب'],

            // ---------------- تاب التطوّع في صفحة المستخدم (12.1)
            ['admin.user_tabs.volunteer_permission', 'admin_users', 'صلاحيّة إظهار تاب التطوّع', 'string', 'memberships.view'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
            ]);
        }

        Cache::forget('settings');
    }

    /** حسابات تجريبيّة: معتمَدة ومستنّية — والتفعيل مجّانيّ باعتماد إداريّ (2.5-د) */
    private function demoAccounts(): void
    {
        $owner = $this->user('مالك المنصّة', 'owner@hc.local', 'active');
        $owner->assignRole('platform_owner');

        $support = $this->user('سلمى عبد الرحمن', 'support@hc.local', 'active');
        $support->assignRole('support_admin');

        $referrer = $this->user('أحمد محمود سليم', 'ahmed@hc.local', 'active');
        $referrer->assignRole('trainee');

        $pending = [
            'منى إبراهيم فؤاد',
            'كريم سامي عبد الله',
            'هالة عادل مصطفى',
            'يوسف طارق حسن',
            'نورهان وائل زكي',
        ];

        foreach ($pending as $index => $name) {
            $account = $this->user($name, 'pending'.($index + 1).'@hc.local', 'pending');
            $account->forceFill(['created_at' => now()->subDays($index)])->save();

            // المدعوّ له تذكرة ترحيب تُصرَف **بعد** قبول الحساب (2.5-د)
            if ($index % 2 === 0) {
                Referral::firstOrCreate(
                    ['referrer_id' => $referrer->id, 'referred_id' => $account->id],
                    ['code' => 'REF-'.$referrer->code, 'welcome_ticket_granted' => false],
                );
            }
        }
    }

    /** المهامّ المعلّقة: طلبات سحب + شكاوى مفتوحة (12.3) */
    private function demoPendingWork(): void
    {
        $coins = Currency::where('code', 'coins')->first();
        $users = User::where('status', 'active')->take(3)->get();

        if ($coins) {
            foreach ($users as $index => $user) {
                Transaction::firstOrCreate(
                    ['user_id' => $user->id, 'source' => 'withdraw', 'reason' => 'طلب سحب رقم '.($index + 1)],
                    [
                        'currency_id' => $coins->id,
                        'amount' => -1 * (250 + $index * 125),
                        'layer' => 'training',
                        'meta' => ['status' => 'pending'],
                        'created_at' => now()->subHours(12 + $index * 30),
                        'updated_at' => now()->subHours(12 + $index * 30),
                    ],
                );
            }
        }

        $complaints = [
            ['الفيديو مش بيفتح على الموبايل', 'الدرس التالت في تدريب المهارات بيقف عند الثانية 30.'],
            ['اقتراح: تنبيه قبل الديدلاين بيوم', 'يريحنا لو في تذكير قبل الموعد بيوم كامل.'],
        ];

        foreach ($complaints as $index => [$title, $body]) {
            if (! $user = $users[$index] ?? null) {
                continue;
            }

            Complaint::firstOrCreate(
                ['number' => 'CMP-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'type' => $index === 0 ? 'complaint' : 'suggestion',
                    'title' => $title,
                    'body' => $body,
                    'status' => 'open',
                    'created_at' => now()->subDays($index + 1),
                    'updated_at' => now()->subDays($index + 1),
                ],
            );
        }
    }

    /** شريحة محفوظة تُعاد الاستفادة منها في الإشعارات والمكافآت (24.1) */
    /**
     * شرائح تجريبيّة (12.13) — واحدة **ديناميكيّة** وأخرى **ثابتة** ليظهر الفرق
     * السلوكيّ بينهما من أوّل تشغيل: الثابتة تُجمَّد على أعضائها الآن.
     */
    private function demoSegments(): void
    {
        $owner = User::where('email', 'owner@hc.local')->first();
        $segments = app(AudienceSegments::class);

        $dynamic = AdAudience::updateOrCreate(
            ['name' => 'المستنّيون اعتماد من يومين'],
            [
                'kind' => AudienceSegments::KIND,
                'segment_type' => AudienceSegments::TYPE_DYNAMIC,
                'description' => 'حسابات لسّه تحت المراجعة اتسجّلت خلال يومين',
                'rule' => ['status' => 'pending', 'registered_days' => 2],
                'created_by' => $owner?->id,
                'size' => 0,
            ],
        );

        $static = AdAudience::updateOrCreate(
            ['name' => 'المتدرّبون النشطون'],
            [
                'kind' => AudienceSegments::KIND,
                'segment_type' => AudienceSegments::TYPE_STATIC,
                'description' => 'لقطة مجمَّدة للمتدرّبين النشطين وقت الحفظ',
                'rule' => ['status' => 'active', 'role' => 'trainee'],
                'created_by' => $owner?->id,
                'size' => 0,
            ],
        );

        $segments->rebuild($dynamic, $owner);
        $segments->rebuild($static, $owner);
    }

    /** سجلّ التدقيق: كلّ تغيير صلاحيّة أو إسناد له أثر (12.2.1-ز-4) */
    private function demoAudit(): void
    {
        $actor = User::where('email', 'owner@hc.local')->first();
        $role = Role::where('key', 'support_admin')->first();

        if (! $actor || ! $role) {
            return;
        }

        AuditLog::firstOrCreate(
            ['action' => 'role.permissions.updated', 'auditable_type' => $role->getMorphClass(), 'auditable_id' => $role->id],
            [
                'user_id' => $actor->id,
                'old_values' => ['granted' => 12],
                'new_values' => ['granted' => 18, 'group' => 'المستخدمون والحسابات والخصوصيّة'],
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
        );
    }

    private function user(string $name, string $email, string $status): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'secret-password',
                'code' => str()->upper(str()->random(8)),
                'status' => $status,
                'activated_at' => $status === 'active' ? now() : null,
                'last_seen_at' => $status === 'active' ? now()->subHours(3) : null,
            ],
        );
    }
}
