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

            // ---------------- شرائح الجمهور (24.1)
            ['admin.segments.preview_rows', 'admin_segments', 'صفوف معاينة أعضاء الشريحة', 'number', '10'],
            ['admin.segments.max_members', 'admin_segments', 'أقصى عدد أعضاء للشريحة', 'number', '50000'],
            ['admin.segments.empty_message', 'admin_segments', 'نصّ الحالة الفارغة', 'string', 'ابنِ شريحتك الأولى'],

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
    private function demoSegments(): void
    {
        AdAudience::firstOrCreate(
            ['name' => 'المستنّيون اعتماد من يومين'],
            [
                'kind' => 'retargeting',
                'rule' => ['status' => 'pending', 'registered_days' => 2],
                'size' => User::where('status', 'pending')->count(),
                'last_built_at' => now(),
            ],
        );

        AdAudience::firstOrCreate(
            ['name' => 'المتدرّبون النشطون'],
            [
                'kind' => 'retargeting',
                'rule' => ['status' => 'active', 'role' => 'trainee'],
                'size' => User::where('status', 'active')->count(),
                'last_built_at' => now(),
            ],
        );
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
