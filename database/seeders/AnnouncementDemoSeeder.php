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
                    'push_to_notifications' => $index === 0,
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
            ['announcements.types', 'announcements', 'أنواع المنشورات في الفلتر', 'json', '{"pinned":"مثبَّت","critical":"يحتاج إقرار","general":"عامّ"}'],
            ['announcements.empty.message', 'announcements', 'رسالة الحالة الفارغة', 'string', 'لا تعليمات جديدة'],

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
