<?php

namespace Database\Seeders;

use App\Models\CertificateType;
use App\Models\Event;
use App\Models\EventAgendaItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات وإعدادات مجال الفعاليّات والدعوات (13.3 · 7.6 · 21.1).
 * لا يُسجَّل في DatabaseSeeder — يُجمَّع مع بقيّة سيدرات المجالات.
 */
class EventDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->permissions();
        $this->events();

        Cache::forget('settings');
    }

    /** لكلّ رقم ونصّ إعداد — ممنوع أيّ قيمة محروقة في الكود (2.13) */
    private function settings(): void
    {
        $rows = [
            // ---------------- الفعاليّات (13.3)
            ['events.default_view', 'events', 'العرض الافتراضيّ (كروت/تقويم)', 'string', 'cards'],
            ['events.published_status', 'events', 'حالة الفعاليّة المنشورة', 'string', 'published'],
            ['events.list.per_page', 'events', 'عدد الفعاليّات في الصفحة', 'number', '12'],
            ['events.default_duration_minutes', 'events', 'مدّة الفعاليّة الافتراضيّة (دقايق)', 'number', '90'],
            ['events.join_link.minutes_before', 'events', 'ظهور رابط الانضمام قبل الموعد (دقايق)', 'number', '30'],
            ['events.checkin.minutes_before', 'events', 'فتح كود الحضور قبل الموعد (دقايق)', 'number', '15'],
            ['events.attendance.code_length', 'events', 'طول كود الحضور الرقميّ', 'number', '6'],
            ['events.ticket.code_prefix', 'events', 'بادئة كود التذكرة', 'string', 'TK'],
            ['events.ticket.code_length', 'events', 'طول كود التذكرة', 'number', '8'],
            ['events.reward.window_hours', 'events', 'نافذة المكافأة الكاملة بعد الفعاليّة (ساعات)', 'number', '24'],
            ['events.reward.late_percent', 'events', 'نسبة المكافأة بعد انتهاء النافذة (%)', 'number', '50'],
            ['events.reminder.minutes_before', 'events', 'تذكير التقويم قبل الموعد (دقايق)', 'number', '60'],
            ['events.certificate.default_type_key', 'events', 'مفتاح نوع شهادة الحضور', 'string', 'event'],
            ['events.certificate.code_prefix', 'events', 'بادئة كود شهادة الحضور', 'string', 'EVT'],
            ['events.share.text', 'events', 'نصّ مشاركة الفعاليّة', 'text', 'شوف الفعاليّة دي معايا:'],
            ['events.ticket.share_text', 'events', 'نصّ مشاركة التذكرة', 'text', 'هحضر الفعاليّة دي — تعالى معايا:'],
            ['events.og.accent', 'events', 'لون بطاقة المشاركة', 'color', '#00d4b8'],
            ['events.og.background', 'events', 'خلفيّة بطاقة المشاركة', 'color', '#0b1512'],
            ['events.og.text', 'events', 'لون نصّ بطاقة المشاركة', 'color', '#e8f5f2'],
            ['events.og.cache_seconds', 'events', 'كاش بطاقة المشاركة (ثوانٍ)', 'number', '3600'],

            // ---------------- الدعوات (7.6 · 21.1-ج)
            ['referral.link.path', 'growth', 'مسار رابط الدعوة', 'string', '/register'],
            ['referral.link.param', 'growth', 'اسم بارامتر كود الدعوة', 'string', 'offer'],
            ['referral.share.text', 'growth', 'نصّ مشاركة الدعوة', 'text', 'انضمّ معايا على المنصّة — هتلاقي تدريبات وشهادات حقيقيّة:'],
            ['referral.deep_links.limit', 'growth', 'عدد روابط الدعوة المقترحة لكلّ محتوى', 'number', '3'],
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
    }

    /**
     * سدّ فجوة الصلاحيّات لمجالنا وحده وبلا لمس سيدر الأدوار المشترك:
     * `events.view/list` نطاقها المسموح ALL فقط فلا يلتقطها إسناد المتدرّب بنطاق SELF،
     * وصفحة الدعوات (`invitations_page`/`friend_invite`) غير مذكورة في مصفوفته أصلًا.
     */
    private function permissions(): void
    {
        $role = Role::where('key', 'trainee')->first();

        if (! $role) {
            return;
        }

        $keys = [
            'events.view' => 'ALL',
            'events.list' => 'ALL',
            'invitations_page.view' => 'SELF',
            'invitations_page.list' => 'SELF',
            'invitations_page.export' => 'SELF',
            'friend_invite.view' => 'SELF',
            'friend_invite.create' => 'SELF',
        ];

        $permissions = Permission::query()->whereIn('key', array_keys($keys))->get();

        foreach ($permissions as $permission) {
            $scope = $keys[$permission->key];
            $allowed = $permission->allowed_scopes ?: [$scope];

            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $role->id, 'permission_id' => $permission->id],
                [
                    'scope' => in_array($scope, $allowed, true) ? $scope : $allowed[0],
                    'effect' => 'allow',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function events(): void
    {
        $certificateType = CertificateType::where('key', 'event')->first();

        $rows = [
            [
                'slug' => 'lqa-almsar-almhny-agsts',
                'title_ar' => 'لقاء المسار المهنيّ — إزّاي تبدأ صحّ',
                'description' => 'جلسة مفتوحة عن أوّل 90 يومًا في أيّ وظيفة جديدة، وإزّاي تبني سمعتك من الأسبوع الأوّل.',
                'mode' => 'online',
                'category' => 'تطوير مهنيّ',
                'starts_at' => now()->addDays(6)->setTime(20, 0),
                'ends_at' => now()->addDays(6)->setTime(21, 30),
                'join_link' => 'https://www.youtube.com/live/demo-career-session',
                'price_coins' => 0,
                'price_tickets' => 0,
                'attendance_code' => '481902',
                'xp_reward' => 150,
                'ticket_reward' => 1,
                'capacity' => 500,
                'agenda' => [
                    ['ترحيب وتعريف بالجلسة', 'إسراء منير', 0],
                    ['أوّل 90 يومًا: خطّة عمليّة', 'أحمد عبد الرحمن', 15],
                    ['أسئلة الحضور', 'إسراء منير', 60],
                ],
            ],
            [
                'slug' => 'wrsht-alkhtabt-alqahrt',
                'title_ar' => 'ورشة الخطابة والإلقاء — القاهرة',
                'description' => 'ورشة حضوريّة بتمارين عمليّة على الصوت ولغة الجسد وترتيب الأفكار.',
                'mode' => 'offline',
                'category' => 'مهارات تواصل',
                'starts_at' => now()->addDays(12)->setTime(16, 0),
                'ends_at' => now()->addDays(12)->setTime(19, 0),
                'location' => 'مركز التدريب — مدينة نصر، القاهرة',
                'lat' => 30.0605,
                'lng' => 31.3300,
                'price_coins' => 150,
                'price_tickets' => 0,
                'attendance_code' => '735164',
                'xp_reward' => 300,
                'ticket_reward' => 2,
                'capacity' => 40,
                'agenda' => [
                    ['تسجيل الحضور', null, 0],
                    ['تمارين الصوت والتنفّس', 'منى الشاذلي', 20],
                    ['وقفة عمليّة أمام الجمهور', 'منى الشاذلي', 80],
                ],
            ],
            [
                'slug' => 'ywm-almsharye-alhjyn',
                'title_ar' => 'يوم المشاريع — حضور هجين',
                'description' => 'عرض مشاريع المتدرّبين مع لجنة تحكيم، تقدر تحضره بالمكان أو أونلاين.',
                'mode' => 'hybrid',
                'category' => 'مجتمع المنصّة',
                'starts_at' => now()->addDays(20)->setTime(18, 0),
                'ends_at' => now()->addDays(20)->setTime(21, 0),
                'location' => 'قاعة المؤتمرات — الإسكندريّة',
                'join_link' => 'https://www.linkedin.com/events/demo-projects-day',
                'price_coins' => 0,
                'price_tickets' => 1,
                'attendance_code' => '902347',
                'xp_reward' => 250,
                'ticket_reward' => 1,
                'capacity' => 120,
                'agenda' => [
                    ['افتتاح اليوم', 'اللجنة', 0],
                    ['عروض المشاريع', null, 20],
                    ['إعلان النتائج', 'اللجنة', 150],
                ],
            ],
            [
                'slug' => 'jlst-alqyadt-almktmlt',
                'title_ar' => 'جلسة القيادة المصغّرة — العدد اكتمل',
                'description' => 'جلسة محدودة بعشرين مقعدًا للنقاش المباشر مع مشرفي المسارات.',
                'mode' => 'online',
                'category' => 'تطوير مهنيّ',
                'starts_at' => now()->addDays(3)->setTime(21, 0),
                'ends_at' => now()->addDays(3)->setTime(22, 0),
                'join_link' => 'https://meet.example.com/demo-leadership',
                'attendance_code' => '110022',
                'xp_reward' => 120,
                'ticket_reward' => 0,
                'capacity' => 0,
                'agenda' => [
                    ['نقاش مفتوح', 'فريق الإشراف', 0],
                ],
            ],
            [
                'slug' => 'mltqa-almtdrbyn-almady',
                'title_ar' => 'ملتقى المتدرّبين — النسخة الماضية',
                'description' => 'ملتقى سنويّ اتعمل الشهر اللي فات، والتسجيل متاح للجميع.',
                'mode' => 'online',
                'category' => 'مجتمع المنصّة',
                'starts_at' => now()->subDays(25)->setTime(20, 0),
                'ends_at' => now()->subDays(25)->setTime(22, 0),
                'join_link' => 'https://www.youtube.com/live/demo-past-meetup',
                'recording_link' => 'https://drive.google.com/file/d/demo-recording/view',
                'attendance_code' => '556677',
                'xp_reward' => 100,
                'ticket_reward' => 1,
                'capacity' => null,
                'agenda' => [
                    ['كلمة الافتتاح', 'فريق المنصّة', 0],
                    ['قصص المتدرّبين', null, 30],
                ],
            ],
        ];

        foreach ($rows as $row) {
            $agenda = $row['agenda'];
            unset($row['agenda']);

            $event = Event::updateOrCreate(['slug' => $row['slug']], [
                ...$row,
                'status' => 'published',
                'certificate_type_id' => $certificateType?->id,
            ]);

            $event->agenda()->delete();

            foreach ($agenda as $index => [$title, $speaker, $offset]) {
                EventAgendaItem::create([
                    'event_id' => $event->id,
                    'title' => $title,
                    'speaker' => $speaker,
                    'starts_at' => $event->starts_at->copy()->addMinutes($offset),
                    'sort_order' => $index,
                ]);
            }
        }

        $this->command?->info('فعاليّات تجريبيّة: '.Event::count());
    }
}
