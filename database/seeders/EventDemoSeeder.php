<?php

namespace Database\Seeders;

use App\Models\CertificateType;
use App\Models\Event;
use App\Models\EventAgendaItem;
use App\Models\Role;
use App\Models\Setting;
use Database\Seeders\Concerns\GrantsWithinMatrixCeiling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات وإعدادات مجال الفعاليّات والدعوات (13.3 · 7.6 · 21.1).
 * لا يُسجَّل في DatabaseSeeder — يُجمَّع مع بقيّة سيدرات المجالات.
 */
class EventDemoSeeder extends Seeder
{
    // كتابةُ الإسناد تمرّ بنقطة القصّ نفسها التي يمرّ بها مسار الإنتاج (12.2.2)
    use GrantsWithinMatrixCeiling;

    public function run(): void
    {
        $this->settings();
        $this->screenTextSettings();
        $this->permissions();
        $this->events();

        Cache::forget('settings');
    }

    /**
     * **نصوص شاشات الفعاليّات** (2.13-أ: «النصوص الظاهرة للمستخدم») — كلّ جملةٍ
     * يقرؤها المتدرّب على `resources/views/events/**` لها مفتاحها هنا، والوحدة
     * **جملةٌ كاملة** كما تُقرَأ لا كلمةً مقتطعة. و`:count` وأخواتها مواضع
     * استبدال لا نصًّا.
     *
     * ⚠️ «الفعاليّات» و«الرئيسيّة» عنوانان **منصوصان حرفيًّا في الدستور**
     * (24.5 · 12.0 — سايد بار المتدرّب)، فافتراضيُّهما هو النصّ المنصوص
     * وتغييرُهما من اللوحة يخالف الخريطة.
     */
    public function screenTextSettings(): void
    {
        $rows = [
            ['events.calendar.empty_month', 'التقويم: الحالة الفارغة للشهر', 'مفيش فعاليّات في الشهر ده.'],
            ['events.calendar.next_month_aria', 'التقويم: زرّ الشهر التالي لقارئ الشاشة', 'الشهر التالي'],
            ['events.calendar.prev_month_aria', 'التقويم: زرّ الشهر السابق لقارئ الشاشة', 'الشهر السابق'],
            ['events.card.ended', 'الكارت: حالة انتهت', 'انتهت'],
            ['events.card.full', 'الكارت: حالة اكتمال العدد', 'اكتمل العدد'],
            ['events.card.join_link_soon', 'الكارت: سطر رابط الانضمام (أونلاين)', 'رابط الانضمام يفتح قبل الموعد'],
            ['events.card.location_tbd', 'الكارت: المكان غير المحدَّد بعد', 'المكان يتحدّد قريبًا'],
            ['events.card.price_coins', 'الكارت: سعر بالكوينز (:coins)', ':coins كوينز'],
            ['events.card.price_free', 'الكارت: الفعاليّة المجّانيّة', 'مجّانيّ'],
            ['events.card.price_tickets', 'الكارت: سعر بالتذاكر (:tickets)', ':tickets تذكرة'],
            ['events.card.register_cta', 'الكارت: دعوة التسجيل', 'سجّل'],
            ['events.card.registered', 'الكارت: حالة مسجَّل', 'مسجَّل'],
            ['events.copy.default_label', 'زرّ النسخ: اللافتة الافتراضيّة', 'نسخ'],
            ['events.copy.done_label', 'زرّ النسخ: الردّ بعد النسخ', 'اتنسخ ✓'],
            ['events.countdown.days_few', 'العدّاد: 3–10 أيّام (:n)', ':n أيّام'],
            ['events.countdown.days_many', 'العدّاد: أكثر من 10 أيّام (:n)', ':n يوم'],
            ['events.countdown.days_one', 'العدّاد: يوم واحد', 'يوم'],
            ['events.countdown.days_two', 'العدّاد: يومان', 'يومين'],
            ['events.countdown.ended', 'العدّاد: الفعاليّة انتهت', 'انتهت'],
            ['events.countdown.hours_few', 'العدّاد: 3–10 ساعات (:n)', ':n ساعات'],
            ['events.countdown.hours_many', 'العدّاد: أكثر من 10 ساعات (:n)', ':n ساعة'],
            ['events.countdown.hours_one', 'العدّاد: ساعة واحدة', 'ساعة'],
            ['events.countdown.hours_two', 'العدّاد: ساعتان', 'ساعتين'],
            ['events.countdown.joiner', 'العدّاد: الرابط بين الوحدات', ' و'],
            ['events.countdown.live', 'العدّاد: الفعاليّة شغّالة الآن', 'شغّالة دلوقتي'],
            ['events.countdown.minutes_few', 'العدّاد: 3–10 دقايق (:n)', ':n دقايق'],
            ['events.countdown.minutes_many', 'العدّاد: أكثر من 10 دقايق (:n)', ':n دقيقة'],
            ['events.countdown.minutes_one', 'العدّاد: دقيقة واحدة', 'دقيقة'],
            ['events.countdown.minutes_two', 'العدّاد: دقيقتان', 'دقيقتين'],
            ['events.countdown.remaining', 'العدّاد: صيغة الوقت المتبقّي (:parts)', 'باقي :parts'],
            ['events.countdown.seconds_few', 'العدّاد: 3–10 ثوانٍ (:n)', ':n ثوانٍ'],
            ['events.countdown.seconds_many', 'العدّاد: أكثر من 10 ثوانٍ (:n)', ':n ثانية'],
            ['events.countdown.seconds_one', 'العدّاد: ثانية واحدة', 'ثانية'],
            ['events.countdown.seconds_two', 'العدّاد: ثانيتان', 'ثانيتين'],
            ['events.index.breadcrumb_home', 'القائمة: جذر مسار التنقّل (منصوص في 24.5)', 'الرئيسيّة'],
            ['events.index.empty_action', 'القائمة: زرّ الحالة الفارغة', 'وسّع المدى'],
            ['events.index.empty_message', 'القائمة: نصّ الحالة الفارغة', 'مفيش فعاليّات في المدى ده — جرّب توسّع الفترة.'],
            ['events.index.filter_apply', 'القائمة: زرّ تطبيق الفلاتر', 'تصفية'],
            ['events.index.filter_category', 'القائمة: عنوان فلتر التصنيف', 'التصنيف'],
            ['events.index.filter_category_all', 'القائمة: خيار كلّ التصنيفات', 'كلّ التصنيفات'],
            ['events.index.filter_mine', 'القائمة: خيار تسجيلاتي فقط', 'تسجيلاتي فقط'],
            ['events.index.filter_mode', 'القائمة: عنوان فلتر النوع', 'النوع'],
            ['events.index.filter_mode_all', 'القائمة: خيار كلّ الأنواع', 'كلّ الأنواع'],
            ['events.index.filter_period', 'القائمة: عنوان فلتر الفترة', 'الفترة'],
            ['events.index.filter_price', 'القائمة: عنوان فلتر السعر', 'السعر'],
            ['events.index.filter_price_all', 'القائمة: خيار كلّ الأسعار', 'الكلّ'],
            ['events.index.filter_price_free', 'القائمة: خيار المجّانيّ', 'مجّانيّ'],
            ['events.index.filter_price_paid', 'القائمة: خيار المدفوع', 'مدفوع'],
            ['events.index.filter_search', 'القائمة: عنوان خانة البحث', 'بحث'],
            ['events.index.filter_search_placeholder', 'القائمة: تلميح خانة البحث', 'ابحث باسم الفعاليّة أو المكان'],
            ['events.index.meta_description', 'القائمة: وصف الميتا', 'فعاليّات المنصّة: أونلاين وأوفلاين وهجين — سجّل واحضر واكسب شهادتك.'],
            ['events.index.my_tickets', 'القائمة: زرّ تذاكري (:count)', 'تذاكري (:count)'],
            ['events.index.subtitle', 'القائمة: السطر تحت العنوان', 'اختار فعاليّة، سجّل، واحضر — والشهادة والمكافأة بتتفتح بكود الحضور.'],
            ['events.index.title', 'القائمة: العنوان (منصوص في 24.5)', 'الفعاليّات'],
            ['events.index.view_calendar', 'القائمة: مبدّل العرض — تقويم', 'تقويم'],
            ['events.index.view_calendar_aria', 'القائمة: مبدّل التقويم لقارئ الشاشة', 'عرض تقويم'],
            ['events.index.view_cards', 'القائمة: مبدّل العرض — كروت', 'كروت'],
            ['events.index.view_cards_aria', 'القائمة: مبدّل الكروت لقارئ الشاشة', 'عرض كروت'],
            ['events.show.add_to_calendar', 'صفحة الفعاليّة: رابط إضافة الموعد للتقويم', 'أضِف لتقويمي'],
            ['events.show.agenda_title', 'صفحة الفعاليّة: عنوان الأجندة', 'الأجندة'],
            ['events.show.all_invites_link', 'صفحة الفعاليّة: رابط كلّ الدعوات', 'كلّ دعواتي وعمولتي'],
            ['events.show.attend_mode_legend', 'صفحة الفعاليّة: عنوان اختيار نمط الحضور', 'نمط الحضور'],
            ['events.show.attend_mode_offline', 'صفحة الفعاليّة: نمط الحضور بالمكان', 'حضور بالمكان'],
            ['events.show.attend_mode_online', 'صفحة الفعاليّة: نمط الحضور أونلاين', 'أونلاين'],
            ['events.show.attendance_title', 'صفحة الفعاليّة: عنوان بلوك الحضور', 'الحضور'],
            ['events.show.attended_badge', 'صفحة الفعاليّة: شارة تأكيد الحضور', 'حضورك مؤكَّد'],
            ['events.show.breadcrumb_events', 'صفحة الفعاليّة: مسار الفعاليّات (منصوص في 24.5)', 'الفعاليّات'],
            ['events.show.breadcrumb_home', 'صفحة الفعاليّة: جذر مسار التنقّل (منصوص في 24.5)', 'الرئيسيّة'],
            ['events.show.certificate_pending', 'صفحة الفعاليّة: الشهادة لم تُصدَر بعد', 'استحقاق الشهادة اتسجّل، وهتظهر أوّل ما تُصدَر.'],
            ['events.show.certificate_ready', 'صفحة الفعاليّة: الشهادة اتفتحت', 'شهادة الحضور اتفتحت — كودها'],
            ['events.show.checkin_action', 'صفحة الفعاليّة: زرّ تأكيد الحضور', 'أكّد حضوري'],
            ['events.show.checkin_closed', 'صفحة الفعاليّة: الكود قبل بداية الفعاليّة', 'كود الحضور بيفتح مع بداية الفعاليّة. جهّز نفسك — والكود هيتعرض في الفعاليّة نفسها.'],
            ['events.show.checkin_hint', 'صفحة الفعاليّة: شرح خانة الكود', 'أدخل الكود المعروض في الفعاليّة — وبيه تتفتح الشهادة والمكافأة.'],
            ['events.show.checkin_title', 'صفحة الفعاليّة: عنوان تشيك-إن الأوفلاين', 'تشيك-إن الحضور'],
            ['events.show.code_title', 'صفحة الفعاليّة: عنوان كود الحضور', 'كود الحضور'],
            ['events.show.copy_invite', 'صفحة الفعاليّة: زرّ نسخ رابط الدعوة', 'نسخ رابط الدعوة'],
            ['events.show.ended_note', 'صفحة الفعاليّة: سطر ما بعد الانتهاء', 'الفعاليّة دي خلصت — شوف تسجيلها فوق أو اختار فعاليّة قادمة.'],
            ['events.show.full_badge', 'صفحة الفعاليّة: شارة اكتمال العدد', 'اكتمل العدد'],
            ['events.show.full_note', 'صفحة الفعاليّة: سطر اكتمال العدد', 'العدد اكتمل في الفعاليّة دي. تابعنا — بننزل مواعيد جديدة.'],
            ['events.show.invite_hint', 'صفحة الفعاليّة: شرح الدعوة (:percent)', 'صاحبك هيفتح الصفحة دي بالظبط بعد تسجيله — وليه تذكرة ترحيب، وليك :percent% من شحناته.'],
            ['events.show.invite_title', 'صفحة الفعاليّة: عنوان بلوك الدعوة', 'ادعُ صديقك للفعاليّة دي'],
            ['events.show.join_action', 'صفحة الفعاليّة: زرّ الدخول للفعاليّة', 'ادخل الفعاليّة'],
            ['events.show.join_link_members_only', 'صفحة الفعاليّة: تنبيه أنّ الرابط للمسجَّلين', '— وبيظهر للمسجَّلين.'],
            ['events.show.join_link_opens_at', 'صفحة الفعاليّة: موعد فتح رابط الانضمام (:at)', 'رابط الانضمام بيفتح :at'],
            ['events.show.location_tbd', 'صفحة الفعاليّة: المكان غير المحدَّد بعد', 'المكان يتحدّد قريبًا.'],
            ['events.show.my_ticket', 'صفحة الفعاليّة: زرّ تذكرتي', 'تذكرتي'],
            ['events.show.open_map', 'صفحة الفعاليّة: رابط الخريطة', 'افتح الخريطة والاتجاهات'],
            ['events.show.place_title', 'صفحة الفعاليّة: عنوان بلوك المكان', 'المكان'],
            ['events.show.price_coins', 'صفحة الفعاليّة: سعر بالكوينز (:coins)', ':coins كوينز'],
            ['events.show.price_free', 'صفحة الفعاليّة: التسجيل المجّانيّ', 'التسجيل مجّانيّ'],
            ['events.show.price_paid', 'صفحة الفعاليّة: التسجيل المدفوع (:price)', 'التسجيل بـ:price'],
            ['events.show.price_tickets', 'صفحة الفعاليّة: سعر بالتذاكر (:tickets)', ':tickets تذكرة'],
            ['events.show.recording_action', 'صفحة الفعاليّة: زرّ التسجيل المرئيّ', 'تسجيل الفعاليّة'],
            ['events.show.recording_pending', 'صفحة الفعاليّة: التسجيل المرئيّ لم ينشر بعد', 'التسجيل هيتنشر هنا أوّل ما يجهز.'],
            ['events.show.register_action', 'صفحة الفعاليّة: زرّ التسجيل', 'سجّل'],
            ['events.show.register_now', 'صفحة الفعاليّة: زرّ سجّل الآن أعلى الصفحة', 'سجّل الآن'],
            ['events.show.registered_count', 'صفحة الفعاليّة: عدد المسجَّلين (:count)', ':count مسجَّل'],
            ['events.show.reward_amount', 'صفحة الفعاليّة: قيمة المكافأة (:xp · :tickets)', ':xp XP · :tickets تذكرة'],
            ['events.show.reward_full_until', 'صفحة الفعاليّة: مهلة المكافأة الكاملة (:until)', 'المكافأة الكاملة لحدّ :until.'],
            ['events.show.reward_now', 'صفحة الفعاليّة: عنوان مكافأة الحضور', 'مكافأة الحضور دلوقتي:'],
            ['events.show.seats_left', 'صفحة الفعاليّة: الأماكن المتبقّية (:seats)', '· باقي :seats مكان'],
            ['events.show.share_action', 'صفحة الفعاليّة: زرّ المشاركة', 'مشاركة'],
            ['events.show.speakers_title', 'صفحة الفعاليّة: عنوان المتحدّثين', 'المتحدّثون'],
            ['events.ticket_card.add_to_calendar', 'كارت التذكرة: زرّ إضافة الموعد للتقويم', 'أضِف لتقويمي'],
            ['events.ticket_card.attend_mode', 'كارت التذكرة: نمط الحضور (:mode)', 'نمط الحضور: :mode'],
            ['events.ticket_card.attended_badge', 'كارت التذكرة: شارة الحضور المؤكَّد', 'حضور مؤكَّد'],
            ['events.ticket_card.checkin_url', 'كارت التذكرة: رابط التشيك-إن (:url)', 'رابط التشيك-إن: :url'],
            ['events.ticket_card.code_hint', 'كارت التذكرة: شرح الكود', 'كود التذكرة — اعرضه عند الاستقبال'],
            ['events.ticket_card.confirmed_badge', 'كارت التذكرة: شارة التذكرة المؤكَّدة', 'مؤكَّدة'],
            ['events.ticket_card.copy_code', 'كارت التذكرة: زرّ نسخ الكود', 'نسخ الكود'],
            ['events.ticket_card.share_ticket', 'كارت التذكرة: زرّ مشاركة التذكرة', 'شارك تذكرتك'],
            ['events.ticket_card.title', 'كارت التذكرة: العنوان', 'تذكرتك'],
            ['events.ticket_page.breadcrumb_events', 'صفحة التذكرة: مسار الفعاليّات (منصوص في 24.5)', 'الفعاليّات'],
            ['events.ticket_page.copy_share_text', 'صفحة التذكرة: زرّ نسخ نصّ المشاركة', 'نسخ نصّ المشاركة'],
            ['events.ticket_page.event_page', 'صفحة التذكرة: زرّ صفحة الفعاليّة', 'صفحة الفعاليّة'],
            ['events.ticket_page.heading', 'صفحة التذكرة: العنوان', 'تذكرتي'],
            ['events.ticket_page.og_card', 'صفحة التذكرة: زرّ بطاقة الصورة', 'بطاقة الفعاليّة (صورة)'],
            ['events.ticket_page.subtitle', 'صفحة التذكرة: السطر تحت العنوان', 'تذكرة حضورك جاهزة للنشر — والكود ده هو إثبات دخولك.'],
            ['events.ticket_page.title', 'صفحة التذكرة: عنوان التبويب (:event)', 'تذكرتي — :event'],
            ['events.ticket_page.whatsapp', 'صفحة التذكرة: زرّ واتساب', 'واتساب'],
            // عناوين النوع والفترة (EventQuery::modes/periods — 2.13-ب)
            ['events.query.mode.online', 'فلتر النوع: أونلاين', 'أونلاين'],
            ['events.query.mode.offline', 'فلتر النوع: أوفلاين', 'أوفلاين'],
            ['events.query.mode.hybrid', 'فلتر النوع: هجين', 'هجين'],
            ['events.query.period.upcoming', 'فلتر الفترة: قادمة', 'قادمة'],
            ['events.query.period.today', 'فلتر الفترة: اليوم', 'اليوم'],
            ['events.query.period.week', 'فلتر الفترة: الأسبوع ده', 'الأسبوع ده'],
            ['events.query.period.past', 'فلتر الفترة: منتهية', 'منتهية'],
            ['events.query.period.all', 'فلتر الفترة: الكلّ', 'الكلّ'],
        ];

        foreach ($rows as [$key, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'events',
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }

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
            ['events.reward.ledger_reason', 'events', 'سبب حركة مكافأة الحضور في الدفتر', 'string', 'حضور فعاليّة'],
            ['events.checkin.window_closed_message', 'events', 'رسالة الكود قبل بداية الفعاليّة', 'text', 'كود الحضور بيشتغل مع بداية الفعاليّة. استنّى شويّة وجرّب تاني.'],

            // ---------------- تشيك-إن QR الديناميكيّ (13.3 · 12.11 · 24.3)
            ['events.checkin.qr_enabled', 'events', 'تفعيل QR التشيك-إن الديناميكيّ', 'bool', '1'],
            ['events.checkin.qr_refresh_seconds', 'events', 'ثوانٍ تجديد QR التشيك-إن', 'number', '30'],
            ['events.checkin.qr_grace_windows', 'events', 'نوافذ سماحٍ مقبولة بعد الحاليّة', 'number', '1'],
            ['events.checkin.qr_modes', 'events', 'أنواع الفعاليّة التي يعمل فيها الـQR', 'json', '["offline","hybrid"]'],
            ['events.checkin.qr_signature_length', 'events', 'طول توقيع رمز التشيك-إن', 'number', '16'],
            ['events.checkin.qr_box_px', 'events', 'ضلع وحدة الـQR بالبكسل', 'number', '5'],
            ['events.checkin.qr_quiet_zone', 'events', 'المنطقة الهادئة حول الـQR (وحدات)', 'number', '4'],
            ['events.checkin.qr_dark', 'events', 'لون وحدات الـQR', 'color', '#0b1512'],
            ['events.checkin.qr_light', 'events', 'خلفيّة الـQR', 'color', '#ffffff'],
            ['events.checkin.qr_alt', 'events', 'وصف صورة الـQR', 'string', 'رمز تشيك-إن الحضور'],

            // ---------------- أفاتارات المسجّلين — دليل اجتماعيّ (13.3 · 24.3-سطر-5040)
            ['events.show.registrant_avatars_enabled', 'events', 'Toggle أفاتارات المسجّلين (دليل اجتماعيّ)', 'bool', '1'],
            ['events.show.avatars_limit', 'events', 'أقصى عدد أفاتارات معروضة في صفحة الفعاليّة', 'number', '8'],
            ['events.checkin.qr_hint', 'events', 'شرح الـQR للمتدرّب', 'text', 'اعرض الرمز ده للمنظّم عشان يمسحه — بيتجدّد كلّ'],
            ['events.checkin.qr_seconds_word', 'events', 'كلمة الثانية', 'string', 'ثانية'],
            ['events.checkin.qr_msg_ok', 'events', 'رسالة الرمز الصحيح', 'text', 'الرمز سليم ✓'],
            ['events.checkin.qr_msg_malformed', 'events', 'رسالة الرمز غير السليم', 'text', 'الرمز ده مش رمز تشيك-إن سليم. اطلب من صاحبه يعرض الرمز من صفحة الفعاليّة تاني.'],
            ['events.checkin.qr_msg_expired', 'events', 'رسالة الرمز المنتهي', 'text', 'الرمز ده انتهت صلاحيّته — بيتجدّد كلّ نصّ دقيقة. اطلب الرمز الجديد وامسحه تاني.'],
            ['events.checkin.qr_msg_forged', 'events', 'رسالة الرمز المزوَّر', 'text', 'الرمز ده مش صادر من المنصّة. سجّل الحضور بكود الحضور بدل المسح.'],
            ['events.checkin.qr_msg_foreign', 'events', 'رسالة رمز فعاليّة أخرى', 'text', 'الرمز ده لفعاليّة تانية — اختر الفعاليّة الصحّ وامسح تاني.'],

            // ---------------- التذكيرات المجدولة (13.3 · 12.11 · 24.3)
            ['events.reminder.offsets_minutes', 'events', 'مواعيد التذكير بالدقائق قبل الموعد', 'json', '[1440,60]'],
            ['events.reminder.channels', 'events', 'قنوات التذكير (جرس/بريد)', 'json', '["bell","email"]'],
            ['events.reminder.offset_labels', 'events', 'ألفاظ مواعيد التذكير', 'json', '{"1440":"يوم","60":"ساعة"}'],
            ['events.reminder.minutes_word', 'events', 'كلمة الدقيقة', 'string', 'دقيقة'],
            ['events.reminder.category', 'events', 'فئة إشعار الفعاليّات', 'string', 'event'],
            ['events.reminder.title_template', 'events', 'قالب عنوان التذكير', 'string', 'فاكر «:title»؟ ابتدت بعد :when'],
            ['events.reminder.body_template', 'events', 'قالب نصّ التذكير', 'text', '«:title» يوم :time بتوقيت :timezone — جهّز نفسك ومكانك.'],
            ['events.reminder.time_format', 'events', 'صيغة وقت التذكير', 'string', 'Y-m-d · H:i'],
            ['events.reminder.form_label', 'events', 'اسم خانة التذكيرات في فورم الفعاليّة', 'string', 'تذكيرات مجدولة للمسجّلين'],
            ['events.reminder.form_hint', 'events', 'شرح خانة التذكيرات', 'string', 'بتتبع مواعيد التذكير في إعدادات الفعاليّات'],
            ['events.reminder.events_per_run', 'events', 'أقصى فعاليّات في مسحة التذكير', 'number', '50'],
            ['events.reminder.recipients_per_event', 'events', 'أقصى مستلِمين لكلّ فعاليّة', 'number', '2000'],

            // ---------------- إشعار المسجّلين (24.3)
            ['events.notice.max_chars', 'events', 'أقصى أحرف إشعار المسجّلين', 'number', '2000'],
            ['events.notice.per_run', 'events', 'أقصى إشعارات مجدولة في المسحة', 'number', '20'],
            ['events.notice.sent_message', 'events', 'رسالة إرسال الإشعار', 'string', 'الإشعار اتبعت للمسجّلين ✓'],
            ['events.notice.scheduled_message', 'events', 'رسالة جدولة الإشعار', 'string', 'الإشعار اتجدول ✓ — هيوصل في معاده.'],

            // ---------------- شاشة «المسجّلون والحضور» الجامعة (12.11 · 24.3)
            ['events.admin.export_limit', 'events', 'أقصى صفوف تصدير المسجّلين', 'number', '5000'],
            ['events.registrations.page_title', 'events', 'عنوان شاشة المسجّلين والحضور', 'string', 'المسجّلون والحضور'],
            ['events.registrations.page_subtitle', 'events', 'سطر شرح الشاشة', 'text', 'مين سجّل ومين حضر فعلًا — عبر كلّ الفعاليّات.'],
            ['events.registrations.crumb_admin', 'events', 'فتات المسار: لوحة الإدارة', 'string', 'لوحة الإدارة'],
            ['events.registrations.crumb_events', 'events', 'فتات المسار: الفعاليّات', 'string', 'الفعاليّات'],
            ['events.registrations.kpi_registered', 'events', 'عدّاد المسجّلين', 'string', 'مسجّل'],
            ['events.registrations.kpi_attended', 'events', 'عدّاد الحاضرين', 'string', 'حاضر'],
            ['events.registrations.kpi_absent', 'events', 'عدّاد الغائبين', 'string', 'غائب'],
            ['events.registrations.filter_search', 'events', 'عنوان البحث', 'string', 'بحث بالاسم أو الكود'],
            ['events.registrations.filter_event', 'events', 'عنوان فلتر الفعاليّة', 'string', 'الفعاليّة'],
            ['events.registrations.filter_attendance', 'events', 'عنوان فلتر حالة الحضور', 'string', 'حالة الحضور'],
            ['events.registrations.filter_mode', 'events', 'عنوان فلتر نمط الحضور', 'string', 'نمط الحضور'],
            ['events.registrations.filter_checkin', 'events', 'عنوان فلتر وقت التشيك-إن', 'string', 'وقت التشيك-إن'],
            ['events.registrations.filter_apply', 'events', 'زرّ الفلترة', 'string', 'فلترة'],
            ['events.registrations.range_today', 'events', 'مدى: آخر يوم', 'string', 'آخر يوم'],
            ['events.registrations.range_week', 'events', 'مدى: آخر أسبوع', 'string', 'آخر أسبوع'],
            ['events.registrations.range_month', 'events', 'مدى: آخر شهر', 'string', 'آخر شهر'],
            ['events.registrations.checkin_ranges', 'events', 'أيّام كلّ مدى للتشيك-إن', 'json', '{"today":1,"week":7,"month":30}'],
            ['events.registrations.all_word', 'events', 'كلمة الكلّ', 'string', 'الكلّ'],
            ['events.registrations.dash', 'events', 'علامة الفراغ في الجدول', 'string', '—'],
            ['events.registrations.attended_label', 'events', 'وسم حضر', 'string', 'حضر'],
            ['events.registrations.absent_label', 'events', 'وسم غاب', 'string', 'غاب'],
            ['events.registrations.mode_offline', 'events', 'وسم الحضور الحضوريّ', 'string', 'حضوريّ'],
            ['events.registrations.mode_online', 'events', 'وسم الحضور الأونلاين', 'string', 'أونلاين'],
            ['events.registrations.date_format', 'events', 'صيغة التاريخ في الشاشة', 'string', 'Y-m-d · H:i'],
            ['events.registrations.col_user', 'events', 'عمود المستخدم', 'string', 'المستخدم'],
            ['events.registrations.col_code', 'events', 'عمود الكود', 'string', 'الكود'],
            ['events.registrations.col_event', 'events', 'عمود الفعاليّة', 'string', 'الفعاليّة'],
            ['events.registrations.col_mode', 'events', 'عمود نمط الحضور', 'string', 'نمط الحضور'],
            ['events.registrations.col_state', 'events', 'عمود حالة الحضور', 'string', 'حالة الحضور'],
            ['events.registrations.col_checkin', 'events', 'عمود وقت التشيك-إن', 'string', 'وقت التشيك-إن'],
            ['events.registrations.col_tier', 'events', 'عمود الدرجة المستحقّة', 'string', 'الدرجة المستحقّة'],
            ['events.registrations.mark_attended', 'events', 'فعل: علّم حاضرًا', 'string', 'علّم حاضرًا'],
            ['events.registrations.mark_absent', 'events', 'فعل: علّم غائبًا', 'string', 'علّم غائبًا'],
            ['events.registrations.grant_manual', 'events', 'فعل: منح مكافأة يدويّ', 'string', 'منح مكافأة يدويّ'],
            ['events.registrations.scan_button', 'events', 'زرّ مسح QR', 'string', 'مسح QR للتشيك-إن'],
            ['events.registrations.scan_hint', 'events', 'شرح بوب-أب المسح', 'text', 'صوّر رمز المتدرّب بكاميرا موبايلك — الرابط بيفتح ويسجّل الحضور فورًا. ولو الكاميرا مش شغّالة، الصق الرمز هنا.'],
            ['events.registrations.scan_token_label', 'events', 'عنوان حقل الرمز', 'string', 'رمز التشيك-إن'],
            ['events.registrations.scan_submit', 'events', 'زرّ تسجيل الحضور', 'string', 'سجّل الحضور'],
            ['events.registrations.manual_button', 'events', 'زرّ التشيك-إن اليدويّ', 'string', 'تشيك-إن يدويّ'],
            ['events.registrations.manual_user_code', 'events', 'عنوان حقل كود المستخدم', 'string', 'كود المستخدم'],
            ['events.registrations.manual_attendance_code', 'events', 'عنوان حقل كود الحضور', 'string', 'كود الحضور'],
            ['events.registrations.notify_button', 'events', 'زرّ إشعار المسجّلين', 'string', 'إشعار المسجّلين'],
            ['events.registrations.notify_body_label', 'events', 'عنوان نصّ الإشعار', 'string', 'نصّ الإشعار'],
            ['events.registrations.notify_placeholder', 'events', 'مثال نصّ الإشعار', 'text', 'مثال: اترفع رابط التسجيل — تقدر تتفرّج عليه من صفحة الفعاليّة.'],
            ['events.registrations.notify_channel_label', 'events', 'عنوان قناة الإشعار', 'string', 'القناة'],
            ['events.registrations.notify_when_label', 'events', 'عنوان موعد الإشعار', 'string', 'الموعد (سيبه فاضي = دلوقتي)'],
            ['events.registrations.notify_submit', 'events', 'زرّ إرسال الإشعار', 'string', 'ابعت'],
            ['events.registrations.channel_bell', 'events', 'قناة الجرس', 'string', 'الجرس'],
            ['events.registrations.channel_email', 'events', 'قناة البريد', 'string', 'البريد'],
            ['events.registrations.export_button', 'events', 'زرّ تصدير CSV', 'string', 'تصدير CSV'],
            ['events.registrations.csv_filename', 'events', 'اسم ملفّ التصدير', 'string', 'event-registrations'],
            ['events.registrations.csv_headers', 'events', 'رؤوس أعمدة ملفّ التصدير', 'json', '["الفعاليّة","التاريخ","الاسم","الكود","نمط الحضور","حالة الحضور","وقت التشيك-إن"]'],
            ['events.registrations.ticket_word', 'events', 'كلمة التذكرة', 'string', 'تذكرة'],
            ['events.registrations.empty_text', 'events', 'الحالة الفارغة', 'string', 'لا مسجّلين بعد — شارك رابط الفعاليّة.'],
            ['events.registrations.empty_action', 'events', 'زرّ الحالة الفارغة', 'string', 'روح للفعاليّات'],

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

        /*
         | ⭐ كان القصّ هنا **نسخةً ثالثة** من القاعدة وأضعفَها: عند تعذّر النطاق
         | تكتب `$allowed[0]` — أوّل عنصرٍ في مصفوفةٍ **غير مرتَّبة**، فقد يكون
         | **أوسع** من المطلوب لا أضيق. فتمرّ الكتابة الآن بنقطة القصّ الواحدة
         | (12.2.2)، وهي وحدها التي تعرف أضيق ما تسمح به المصفوفة.
         */
        $this->grantKeysWithinCeiling($role->id, $keys, matrixFloor: true);
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
