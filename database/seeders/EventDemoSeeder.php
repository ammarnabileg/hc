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
    public function settings(): void
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
            // العنوان الإنجليزيّ اختياريّ، فالـslug يرتدّ للعربيّ ثمّ لهذه الكلمة (12.11)
            ['events.slug.fallback', 'events', 'كلمة الـslug الاحتياطيّة', 'string', 'event'],
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

            /*
             | ---- صيغة رابط الدعوة (7.6.2) — **حسم تعارض داخل الدستور**.
             | 7.6 يكتبها `?offer=<user_id>` و7.6.2 يكتبها `/join?ref=CODE`.
             | المعتمَد صيغة 7.6.2 لأنّها لا تكشف المعرّفات الرقميّة، و`?offer=`
             | يبقى **مقبولًا عند الاستقبال** للتوافق مع الروابط القديمة.
             */
            ['referral.join.path', 'growth', 'مسار بوّابة الدعوة', 'string', '/join'],
            ['referral.join.param', 'growth', 'اسم بارامتر كود الدعوة (المعتمَد)', 'string', 'ref'],

            // ---- الهيرو (7.6.2): الرقم الضخم والنصّ النفسيّ
            ['referral.hero.badge', 'growth', 'شارة الهيرو', 'string', 'عمولة مدى الحياة'],
            ['referral.hero.title', 'growth', 'عنوان الهيرو', 'string', ':percent% من إجماليّ شحن كلّ من دعوتهم — مدى الحياة'],
            ['referral.hero.note', 'growth', 'النصّ النفسيّ تحت الهيرو', 'text', 'مش مجرّد دعوة — كلّ شخص تجيبه بتكسب نسبة من كلّ ما يشحنه للأبد، سواء اشترى بعد سنة أو عشر سنين.'],

            // ---- الآلة الحاسبة التفاعليّة (7.6.2 — «قلب التفاعل»)
            ['referral.calc.title', 'growth', 'عنوان الآلة الحاسبة', 'string', 'احسب أرباحك المحتملة'],
            ['referral.calc.disclaimer', 'growth', 'تنويه التقدير', 'string', 'تقديرات توضيحيّة للواجهة — مش التزام ماليّ.'],
            ['referral.calc.monthly_label', 'growth', 'عنوان كارت الأرباح الشهريّة', 'string', 'أرباحك الشهريّة ($)'],
            ['referral.calc.total_label', 'growth', 'عنوان كارت الأرباح الكليّة', 'string', 'الأرباح الكليّة ($)'],
            ['referral.calc.egp_label', 'growth', 'عنوان كارت الجنيه المصريّ', 'string', 'بالجنيه المصريّ'],
            ['referral.calc.invites_label', 'growth', 'عنوان منزلق المدعوّين', 'string', 'عدد المدعوّين'],
            ['referral.calc.topup_label', 'growth', 'عنوان منزلق متوسّط الشحن', 'string', 'متوسّط الشحن الشهريّ ($)'],
            ['referral.calc.months_label', 'growth', 'عنوان منزلق الفترة', 'string', 'الفترة (شهر)'],
            ['referral.calc.invites_min', 'growth', 'أدنى عدد مدعوّين', 'number', '0'],
            ['referral.calc.invites_max', 'growth', 'أقصى عدد مدعوّين', 'number', '500'],
            ['referral.calc.invites_default', 'growth', 'القيمة الابتدائيّة للمدعوّين', 'number', '249'],
            ['referral.calc.topup_min', 'growth', 'أدنى متوسّط شحن ($)', 'number', '1'],
            ['referral.calc.topup_max', 'growth', 'أقصى متوسّط شحن ($)', 'number', '50'],
            ['referral.calc.topup_default', 'growth', 'القيمة الابتدائيّة لمتوسّط الشحن', 'number', '8'],
            ['referral.calc.months_min', 'growth', 'أدنى فترة (شهر)', 'number', '1'],
            ['referral.calc.months_max', 'growth', 'أقصى فترة (شهر)', 'number', '24'],
            ['referral.calc.months_default', 'growth', 'القيمة الابتدائيّة للفترة', 'number', '7'],
            ['referral.calc.egp_rate', 'growth', 'سعر الصرف التقريبيّ للدولار', 'number', '50'],
            ['referral.calc.passive_line', 'growth', 'شريط الدخل السلبيّ', 'string', 'دخل سلبيّ حقيقيّ — بدون أيّ مجهود بعد الدعوة'],

            // ---- «كيف يعمل؟» بأربع خطوات (7.6.2) — والشرط مذكور صراحةً بلا إخفاء
            ['referral.how.title', 'growth', 'عنوان «كيف يعمل؟»', 'string', 'كيف يعمل؟'],
            ['referral.how.steps', 'growth', 'خطوات «كيف يعمل؟»', 'json', '[{"title": "انسخ رابطك", "body": "رابط دعوة خاصّ بك وحدك — من فوق بضغطة واحدة."}, {"title": "شاركه مع أصحابك", "body": "واتساب أو فيسبوك أو تيليجرام أو نسخ مباشر."}, {"title": "صاحبك يسجّل ويكمل", "body": "يستكمل بياناته ويعدّي الاختبار التمهيديّ، ثمّ يعتمده الأدمن."}, {"title": "الاتنين تكسبوا", "body": "تذكرة لك فور تفعيله، وتذكرة له — وليك :percent% على كلّ شحناته للأبد."}]'],

            // ---- «شبكتي» (7.6.1)
            ['referral.network.title', 'growth', 'عنوان شبكتي', 'string', 'شبكتي'],
            ['referral.network.me', 'growth', 'وسم صاحب الشبكة', 'string', 'إنت'],
            ['referral.network.unknown', 'growth', 'اسم المدعوّ غير المكتمل', 'string', 'ضيف'],
            ['referral.network.empty', 'growth', 'الحالة الفارغة للشبكة', 'string', 'شبكتك لسّه فاضية — أوّل صاحب تجيبه هيبان هنا.'],
            ['referral.network.max_nodes', 'growth', 'أقصى عدد عقد معروضة', 'number', '12'],
            ['referral.network.next_prefix', 'growth', 'بادئة العتبة التالية', 'string', 'باقي'],
            ['referral.network.next_suffix', 'growth', 'لاحقة العتبة التالية', 'string', 'دعوة مفعَّلة للّقب التالي'],

            // ---- المشاركة والفلاتر (7.6.2)
            ['referral.share.facebook_label', 'growth', 'اسم زرّ فيسبوك', 'string', 'فيسبوك'],
            ['referral.share.telegram_label', 'growth', 'اسم زرّ تيليجرام', 'string', 'تيليجرام'],
            ['referral.share.whatsapp_label', 'growth', 'اسم زرّ واتساب', 'string', 'واتساب'],
            ['referral.list.title', 'growth', 'عنوان قائمة المدعوّين', 'string', ':count أشخاص دعوتهم'],
            ['referral.filter.period_label', 'growth', 'عنوان فلتر الفترة', 'string', 'الفترة'],
            ['referral.filter.status_label', 'growth', 'عنوان فلتر الحالة', 'string', 'الحالة'],
            ['referral.filter.all', 'growth', 'فلتر: الكلّ', 'string', 'الكلّ'],
            ['referral.filter.completed', 'growth', 'فلتر: مكتمل', 'string', 'مكتمل'],
            ['referral.filter.pending', 'growth', 'فلتر: في الانتظار', 'string', 'في الانتظار'],
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
