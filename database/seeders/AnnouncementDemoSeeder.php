<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات تجريبيّة للتعليمات ومركز الإشعارات (13.2 · 2.8) — ولا تُسجَّل في DatabaseSeeder.
 * ويحمل معه **إعدادات المجال** (2.13) فلا رقم ولا نصّ محروق في الكود.
 */
class AnnouncementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();

        $author = User::query()->orderBy('id')->first();

        $announcements = [
            [
                'title' => 'تحديث مواعيد التسليم في التدريبات',
                'body' => "بنبدأ من الأسبوع الجاي نظام مواعيد أوضح لكلّ درس، وهتلاقي العدّاد قدّامك في صفحة التدريب.\nلو حابب تعرف التفاصيل، افتح الدليل من الزرّ تحت.",
                'cta_label' => 'افتح الدليل',
                'cta_url' => '/help',
                'audience' => ['type' => 'all'],
                'reactions_enabled' => true,
                'requires_acknowledge' => false,
                'acknowledge_xp' => 0,
                'is_pinned' => true,
                'expires_at' => null,
            ],
            [
                'title' => 'سياسة الغياب في الجلسات المباشرة',
                'body' => 'برجاء قراءة السياسة الجديدة للغياب قبل بداية الدفعة، وتأكيد قراءتك من الزرّ عشان نعرف إنّها وصلتك.',
                'cta_label' => null,
                'cta_url' => null,
                'audience' => ['type' => 'role', 'keys' => ['trainee']],
                'reactions_enabled' => false,
                'requires_acknowledge' => true,
                'acknowledge_xp' => 10,
                'is_pinned' => false,
                'expires_at' => null,
            ],
            [
                'title' => 'ميزة جديدة: لقطة الإنجاز',
                'body' => 'دلوقتي كلّ شهادة بتطلعلك بلقطة جاهزة للمشاركة باسمك وتاريخك — جرّبها من صفحة شهاداتي.',
                'cta_label' => 'شهاداتي',
                'cta_url' => '/learning/certificates',
                'audience' => ['type' => 'all'],
                'reactions_enabled' => true,
                'requires_acknowledge' => false,
                'acknowledge_xp' => 0,
                'is_pinned' => false,
                'expires_at' => null,
            ],
            [
                'title' => 'صيانة قصيرة ليلة الجمعة',
                'body' => 'هيكون في صيانة من 2 لـ3 صباحًا. شغلك محفوظ ومش هتخسر أيّ تقدّم.',
                'cta_label' => null,
                'cta_url' => null,
                'audience' => ['type' => 'all'],
                'reactions_enabled' => false,
                'requires_acknowledge' => false,
                'acknowledge_xp' => 0,
                'is_pinned' => false,
                'expires_at' => now()->subDay(), // منتهي الصلاحيّة: يُؤرشَف تلقائيًّا فلا يظهر
            ],
        ];

        foreach ($announcements as $index => $row) {
            Announcement::updateOrCreate(
                ['title' => $row['title']],
                $row + [
                    'status' => 'published',
                    // القنوات الموحّدة (12.6-أ): التاب دائمًا، والإشعار للأوّل،
                    // والبريد للثاني — فتظهر الثلاث قنوات مستقلّةً من أوّل تشغيل.
                    'show_in_feed' => true,
                    'push_to_notifications' => $index === 0,
                    'email_enabled' => $index === 1,
                    'scheduled_at' => now()->subDays($index + 1),
                    'created_by' => $author?->id,
                ],
            );
        }

        $this->notifications();
    }

    /** إشعارات تجريبيّة على الطبقتين مع مهل وإجراءات مباشرة. */
    private function notifications(): void
    {
        $users = User::query()->orderBy('id')->limit(3)->get();

        foreach ($users as $user) {
            if ($user->notificationsFeed()->exists()) {
                continue;
            }

            Notifier::send($user, 'announcement', 'تعليمات جديدة من الإدارة', 'تحديث مواعيد التسليم في التدريبات', '/announcements');
            Notifier::send($user, 'certificate', 'صدرت شهادتك 🎓', 'شهادة «أساسيّات التسويق» جاهزة للتحميل', '/learning/certificates');
            Notifier::send($user, 'exam', 'امتحانك قرب يقفل', 'باقي أقلّ من يوم على قفل امتحان القسم الثاني', '/learning/courses', 'platform', now()->addHours(6), true);
            Notifier::send($user, 'wallet', 'اتشحن رصيدك', 'اتضافت 50 كوينز لمحفظتك', '/wallet');

            if ($user->isVolunteer()) {
                Notifier::send($user, 'task', 'مهمّة مستنّية تسليمك', 'تصميم بوستر الفعاليّة', '/volunteer/tasks', 'volunteer', now()->addDays(3), true);
                Notifier::send($user, 'meeting', 'اجتماع الفريق بكرة', 'الساعة 8 مساءً على زوم', '/volunteer/meetings', 'volunteer');
            }
        }
    }

    /** إعدادات المجال (2.13) — بنمط «المجال.الميزة.المفتاح» ولكلّ إعداد قيمة افتراضيّة. */
    public function settings(): void
    {
        $rows = [
            // ---------------- التعليمات (13.2)
            ['announcements.feed.per_page', 'announcements', 'عدد المنشورات في الدفعة', 'number', '10'],
            ['announcements.feed.max_items', 'announcements', 'سقف المنشورات الحيّة المقروءة', 'number', '200'],
            ['announcements.status.published', 'announcements', 'حالة المنشور المنشور', 'string', 'published'],
            ['announcements.reactions.allowed', 'announcements', 'الإيموجي المسموح للتفاعل', 'json', '["👍","❤️","🎉","👏","🙏"]'],
            ['announcements.acknowledge.label', 'announcements', 'نصّ زرّ الإقرار', 'string', 'قرأتُ وفهمت'],
            ['announcements.acknowledge.max_xp', 'announcements', 'سقف مكافأة الإقرار (XP)', 'number', '50'],
            ['announcements.acknowledge.currency', 'announcements', 'عملة مكافأة الإقرار', 'string', 'xp'],
            // ⭐ سقف **يوميّ** فوق سقف المنشور الواحد — «بحذر بلا إغراق» (13.2)؛ و0 = بلا سقف
            ['announcements.acknowledge.daily_max_xp', 'announcements', 'السقف اليوميّ لمكافآت الإقرار (XP)', 'number', '100'],
            ['announcements.acknowledge.max_tickets', 'announcements', 'سقف تذاكر الإقرار للمنشور', 'number', '20'],
            ['announcements.acknowledge.ledger_source', 'announcements', 'دلو مصدر الإقرار في دفتر الأستاذ', 'string', 'announcement'],
            // ⭐ فرض «قبل المتابعة» على الخادم (13.2): بلا هذا المفتاح يبقى الإقرار بوب-أبًا يُغلَق
            ['announcements.acknowledge.enforce', 'announcements', 'منع تصفّح المنصّة قبل إقرار التوجيه الحرج', 'bool', '1'],
            ['announcements.acknowledge.notice', 'announcements', 'سطر شرح إلزاميّة الإقرار في البوب-أب', 'string', 'توجيه حرج — لازم تقرّ بقراءته قبل ما تكمّل تصفّح المنصّة.'],
            ['announcements.acknowledge.wall_notice', 'announcements', 'رسالة التحويلة عند حجب التصفّح للإقرار', 'string', 'في توجيه مهمّ مستنّي إقرارك — اقراه وأقِرّ بيه عشان تكمّل.'],
            // ⚠️ الأبواب التي تبقى مفتوحة رغم الإقرار المعلّق — وإلّا صار الإقرار سجنًا
            ['announcements.acknowledge.exempt_paths', 'announcements', 'المسارات المفتوحة رغم الإقرار المعلّق', 'json', json_encode([
                'announcements', 'announcements/*',
                'logout', 'impersonate/stop',
                'settings/security', 'settings/security/*',
                'help', 'help/*',
                'up', 'webhooks/*',
            ], JSON_UNESCAPED_UNICODE)],
            ['announcements.types', 'announcements', 'أنواع المنشورات في الفلتر', 'json', '{"pinned":"مثبَّت","critical":"يحتاج إقرار","general":"عامّ"}'],
            ['announcements.empty.message', 'announcements', 'رسالة الحالة الفارغة', 'string', 'لا تعليمات جديدة'],
            ['announcements.status.archived', 'announcements', 'حالة المنشور المؤرشف', 'string', 'archived'],

            // ---------------- استطلاع داخل المنشور (12.6-أ): عامّ النتيجة أو مخفيّها
            ['announcements.poll.hidden_notice', 'announcements', 'نصّ إخفاء نتيجة الاستطلاع', 'string', 'النتيجة مخفيّة لحدّ ما الاستطلاع يقفل.'],

            // ---------------- سلسلة Onboarding متدرّجة (12.6-أ)
            ['announcements.onboarding.enabled', 'announcements', 'تفعيل سلسلة الـOnboarding المتدرّجة', 'bool', '1'],

            // ---------------- التخصيص الديناميكيّ في نصّ المنشور (12.6-أ)
            ['announcements.personalization.tokens', 'announcements', 'وسوم التخصيص الديناميكيّ', 'json', '{"[اسم]":"الاسم الأوّل للقارئ","[الاسم]":"الاسم الكامل للقارئ","[الكود]":"كود المستخدم","[التدريب]":"اسم أحدث تدريب نشط","[الديدلاين]":"ديدلاين ذلك التدريب"}'],
            ['announcements.personalization.fallback_name', 'announcements', 'بديل الاسم حين يغيب', 'string', 'صاحبنا'],
            ['announcements.personalization.fallback_course', 'announcements', 'بديل اسم التدريب حين يغيب', 'string', 'تدريبك'],
            ['announcements.personalization.fallback_deadline', 'announcements', 'بديل الديدلاين حين يغيب', 'string', 'الموعد المحدَّد'],
            ['announcements.personalization.enrollment_status', 'announcements', 'حالة التسجيل المعتمَدة في التخصيص', 'string', 'active'],

            // ---------------- ⭐ القنوات الموحّدة من مكان واحد (12.6-أ)
            ['announcements.channels', 'announcements', 'قنوات المنشور في المحرّر', 'json', json_encode([
                'feed' => 'تاب التعليمات',
                'push' => 'إشعار / Toast',
                'email' => 'بريد',
            ], JSON_UNESCAPED_UNICODE)],
            ['announcements.channels.feed_default_on', 'announcements', 'قناة التاب مفعّلة افتراضيًّا في المحرّر', 'bool', '1'],
            ['announcements.audience.segment_hint', 'announcements', 'سطر شرح استهداف شريحة محفوظة', 'string', 'الشريحة بتتحلّ لأعضائها على السيرفر لحظة الإرسال — مش لحظة الحفظ.'],
            ['announcements.audience.segments_empty', 'announcements', 'نصّ غياب الشرائح المحفوظة', 'string', 'مفيش شرائح محفوظة لسّه — ابنِ واحدة'],

            // ---------------- ⭐ قناة البريد (12.6-أ) وحدّ هدوئها (12.6-ب)
            ['announcements.email.enabled', 'announcements', 'تفعيل قناة البريد للمنشورات', 'bool', '1'],
            ['announcements.email.require_verified', 'announcements', 'إرسال البريد لمن بريده موثَّق فقط', 'bool', '1'],
            ['announcements.email.rate_limit.per_user_per_day', 'announcements', 'أقصى رسائل بريد للمستخدم في اليوم', 'number', '2'],
            ['announcements.email.defer_hours', 'announcements', 'مدّة تأجيل الرسالة الزائدة عن حدّ الهدوء (ساعات)', 'number', '24'],
            ['announcements.email.max_attempts', 'announcements', 'أقصى محاولات إرسال قبل التوقّف', 'number', '3'],
            ['announcements.email.max_recipients', 'announcements', 'أقصى مستقبِلين في الدفعة الواحدة', 'number', '2000'],
            ['announcements.email.max_announcements_per_run', 'announcements', 'أقصى منشورات في تشغيلة الإرسال', 'number', '50'],
            ['announcements.email.recipient_status', 'announcements', 'حالة الحساب المستقبِلة للبريد', 'string', 'active'],
            ['announcements.email.body_limit', 'announcements', 'أقصى حروف نصّ الرسالة', 'number', '2000'],
            ['announcements.email.subject_template', 'announcements', 'قالب عنوان الرسالة (:title)', 'string', ':title'],
            ['announcements.email.cta_fallback_label', 'announcements', 'نصّ زرّ الرسالة حين يغيب CTA', 'string', 'افتح التعليمات'],
            ['announcements.email.footer', 'announcements', 'تذييل رسالة المنشور', 'string', 'وصلتك الرسالة دي لأنّك مفعّل قناة البريد — تقدر توقّفها من إعدادات حسابك.'],
            ['announcements.email.editor_hint', 'announcements', 'سطر شرح قناة البريد في المحرّر', 'string', 'البريد بيروح لمن بريده موثَّق ومفعّل القناة بس — والزيادة بتتأجّل احترامًا لحدّ الهدوء.'],
            ['announcements.email.skip_no_address', 'announcements', 'سبب الاستبعاد: بلا عنوان بريد', 'string', 'بلا عنوان بريد'],
            ['announcements.email.skip_unverified', 'announcements', 'سبب الاستبعاد: بريد غير موثَّق', 'string', 'البريد غير موثَّق'],
            ['announcements.email.skip_optout', 'announcements', 'سبب الاستبعاد: أوقف قناة البريد', 'string', 'أوقف قناة البريد'],
            ['announcements.email.defer_reason', 'announcements', 'سبب التأجيل بحدّ الهدوء', 'string', 'اتأجّل احترامًا لحدّ الهدوء'],

            // ---------------- مركز الإشعارات (2.8)
            ['notifications.per_page', 'notifications', 'عدد الإشعارات في الدفعة', 'number', '20'],
            ['notifications.layers.allowed', 'notifications', 'طبقات الإشعارات', 'json', '["platform","volunteer"]'],
            ['notifications.deadline.soon_hours', 'notifications', 'ساعات «المهلة قرب» قبل التحوّل للأصفر', 'number', '24'],
            ['notifications.action.default_label', 'notifications', 'نصّ زرّ الإجراء المباشر', 'string', 'نفّذ الآن'],
            ['notifications.empty.message', 'notifications', 'رسالة الحالة الفارغة', 'string', 'مفيش إشعارات جديدة'],
            ['notifications.category.default_icon', 'notifications', 'أيقونة الفئة الافتراضيّة', 'string', '🔔'],
            ['notifications.category.icons', 'notifications', 'أيقونة لكلّ فئة (قاموس مقفول 2.16-ج)', 'json', json_encode([
                'announcement' => '📢', 'account' => '👤', 'certificate' => '🎓', 'exam' => '📝',
                'wallet' => '💰', 'ticket' => '🎟️', 'challenge' => '⚔️', 'streak' => '🔥',
                'referral' => '👥', 'order' => '🧾', 'task' => '🧩', 'meeting' => '📅',
                'escalation' => '⚠️', 'objection' => '⚖️', 'complaint' => '📮', 'event' => '📅',
                'system' => '🔔',
            ], JSON_UNESCAPED_UNICODE)],
            // ⭐ فئات إشعارات التطوّع العشر (13) — لافتة كلّ فئة + خريطة القيم الخام
            ['notifications.category.bucket_label_tasks', 'notifications', 'فئة الفلتر: مهامّ', 'string', 'مهامّ'],
            ['notifications.category.bucket_label_contributions', 'notifications', 'فئة الفلتر: مساهمات ونقاط تفتيش', 'string', 'مساهمات ونقاط تفتيش'],
            ['notifications.category.bucket_label_decisions', 'notifications', 'فئة الفلتر: نوافذ قرار', 'string', 'نوافذ قرار'],
            ['notifications.category.bucket_label_meetings', 'notifications', 'فئة الفلتر: اجتماعات', 'string', 'اجتماعات'],
            ['notifications.category.bucket_label_transactions', 'notifications', 'فئة الفلتر: معاملات واعتراضات', 'string', 'معاملات واعتراضات'],
            ['notifications.category.bucket_label_escalations', 'notifications', 'فئة الفلتر: تصعيدات وتحكيمات', 'string', 'تصعيدات وتحكيمات'],
            ['notifications.category.bucket_label_academy', 'notifications', 'فئة الفلتر: أكاديمية وتسجيلات', 'string', 'أكاديمية وتسجيلات'],
            ['notifications.category.bucket_label_recognition', 'notifications', 'فئة الفلتر: تقدير', 'string', 'تقدير'],
            ['notifications.category.bucket_label_structure', 'notifications', 'فئة الفلتر: هيكل وترقيات', 'string', 'هيكل وترقيات'],
            ['notifications.category.bucket_label_recruitment', 'notifications', 'فئة الفلتر: توظيف', 'string', 'توظيف'],
            ['notifications.category.bucket_map', 'notifications', 'خريطة قيم category الخام ⟵ فئة الفلتر', 'json', json_encode([
                'task' => 'tasks', 'task_delivered' => 'tasks', 'task_extension' => 'tasks',
                'task_apology' => 'tasks', 'task_flag' => 'tasks', 'task_blocked' => 'tasks',
                'contribution' => 'contributions', 'goal' => 'decisions', 'meeting.' => 'meetings',
                'objection' => 'transactions', 'consent' => 'transactions', 'escalation' => 'escalations',
                'arbitration' => 'escalations', 'academy' => 'academy', 'recognition' => 'recognition',
                'account' => 'structure', 'volunteer' => 'structure', 'recruitment' => 'recruitment',
            ], JSON_UNESCAPED_UNICODE)],
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

        Cache::forget('settings');
    }
}
