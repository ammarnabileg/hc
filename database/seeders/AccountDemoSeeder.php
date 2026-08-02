<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\ConsentRequest;
use App\Models\EmergencyContact;
use App\Models\HelpArticle;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserPrivacySetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * بيانات تجريبيّة لمجال «account» (الدعم وحسابي والبروفايل والبحث)
 * ومعها **إعدادات المجال** كما تقتضي القاعدة الذهبيّة 2.13 — بلا أرقام محروقة.
 */
class AccountDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->helpArticles();

        $users = $this->users();
        $this->complaints($users[0]);
        $this->privacy($users[0]);
        $this->devices($users[0]);
        $this->consents($users[0], $users[1]);
    }

    /** إعدادات المجال — نمط المفتاح «المجال.الميزة.المفتاح» (2.13) */
    private function settings(): void
    {
        $rows = [
            // الشكاوى والمقترحات (11)
            ['account.complaints.categories', 'account', 'أسباب الشكوى/المقترح', 'json', json_encode([
                'أحد المشرفين',
                'الهيكل الإداريّ وأسلوب الإدارة',
                'اللقاءات المباشرة',
                'اللوائح والقوانين',
                'المحتوى التدريبيّ',
                'خدمة العملاء',
                'المنصّة',
                'أخرى',
            ], JSON_UNESCAPED_UNICODE)],
            ['account.complaints.number_prefix', 'account', 'بادئة رقم التذكرة', 'string', 'TK-'],
            ['account.complaints.attachment_max_kb', 'account', 'أقصى حجم مرفق التذكرة (KB)', 'number', '4096'],

            // دليل المستخدم
            ['account.help.page_size', 'account', 'عدد مقالات الدليل في الصفحة', 'number', '20'],
            ['account.help.related_count', 'account', 'عدد المقالات القريبة', 'number', '3'],

            // البروفايل (10 · 13.4-م)
            ['account.profile.online_window_minutes', 'account', 'نافذة نقطة النشاط (دقائق)', 'number', '10'],
            ['account.profile.rep_danger_below', 'account', 'حدّ Rep الأحمر', 'number', '-8'],
            ['account.profile.excellence_club_threshold', 'account', 'عتبة نادي التميّز (Rep)', 'number', '9.5'],

            // الإعدادات والخصوصيّة (24.5 · 12.14-د)
            ['account.avatar.max_kb', 'account', 'أقصى حجم الأفاتار (KB)', 'number', '2048'],
            ['account.emergency_contacts.max', 'account', 'أقصى عدد جهات الطوارئ', 'number', '2'],
            ['account.privacy.default_sensitive', 'account', 'الافتراضيّ للحقول الحسّاسة', 'string', 'supervisors'],
            ['account.privacy.default_public', 'account', 'الافتراضيّ للحقول العامّة', 'string', 'all_users'],
            ['account.privacy.min_visibility', 'account', 'الحدّ الأدنى الذي يفرضه الأدمن', 'string', 'all_users'],
            ['account.consent.duration_days', 'account', 'مدّة صلاحيّة موافقة الإظهار (أيّام)', 'number', '30'],
            ['account.export.filename_prefix', 'account', 'بادئة ملفّ «تحميل بياناتي»', 'string', 'my-data'],

            // البحث الكبير (13.1)
            ['account.search.page_size', 'account', 'عدد نتائج البحث في المرّة', 'number', '6'],
            ['account.search.min_query_length', 'account', 'أدنى طول لكلمة البحث', 'number', '2'],
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

    private function helpArticles(): void
    {
        $rows = [
            ['الشهادات', 'إزاي أحمّل شهادتي؟', 'شهاداتك كلّها موجودة في «تعلّمي ← شهاداتي». افتح الشهادة واضغط [تحميل] وهتنزل عندك PDF جاهز للطباعة أو المشاركة على لينكدإن.', ['شهادة', 'تحميل'], 41, 3],
            ['الشهادات', 'حدّ يقدر يتأكّد إنّ شهادتي حقيقيّة؟', 'أيوه. كلّ شهادة ليها كود وQR، وأيّ حدّ يفتح صفحة التحقّق ويكتب الكود هيشوف بياناتها فورًا.', ['تحقّق', 'شهادة'], 28, 1],
            ['المحفظة', 'إزاي أشحن حسابي؟', 'من «المحفظة ← رصيدي وشحن». في طريقتين: تحويل يدويّ بإيصال، أو بوّابة دفع مباشرة. الشحن اليدويّ بيتراجع من الفريق وبيتفعّل بعد المراجعة.', ['شحن', 'محفظة'], 33, 5],
            ['الحساب', 'نسيت كلمة السرّ — أعمل إيه؟', 'من صفحة الدخول اضغط «نسيت كلمة السرّ» وهيوصلك رابط على بريدك. ولو البريد مش شغّال، افتح تذكرة والفريق هيساعدك.', ['كلمة السرّ', 'دخول'], 52, 6],
            ['الحساب', 'مين بيشوف بياناتي؟', 'من «حسابي ← الخصوصيّة والأمان» تقدر تحدّد لكلّ حقل مين يشوفه. المحافظة بس بتفضل ظاهرة للكلّ على طول — دي قاعدة ثابتة في المنصّة.', ['خصوصيّة', 'بيانات'], 19, 2],
            ['التلعيب', 'إيه هو نادي الخامسة صباحًا؟', 'لو دخلت وذاكرت قبل الساعة خمسة الصبح بتتحسبلك يوم في نادي الخامسة، وكلّ يوم بيقرّبك لمستوى جديد في تاب الإنجازات.', ['ستريك', 'نادي'], 24, 4],
        ];

        foreach ($rows as [$category, $title, $body, $tags, $yes, $no]) {
            HelpArticle::updateOrCreate(['slug' => Str::slug($title, '-', 'ar') ?: Str::random(10)], [
                'category' => $category,
                'title' => $title,
                'body' => $body,
                'tags' => $tags,
                'helpful_yes' => $yes,
                'helpful_no' => $no,
                'status' => 'published',
            ]);
        }
    }

    /** @return array<int, User> */
    private function users(): array
    {
        $rows = [
            ['منى عبد الرحمن', 'mona.demo@hc.local', '+201000000101', 'UDEMO001'],
            ['أحمد سيف الدين', 'ahmed.demo@hc.local', '+201000000102', 'UDEMO002'],
            ['فاطمة الزهراء محمود', 'fatma.demo@hc.local', '+201000000103', 'UDEMO003'],
            ['يوسف عبد الله', 'youssef.demo@hc.local', '+201000000104', 'UDEMO004'],
            ['نورهان مصطفى', 'nourhan.demo@hc.local', '+201000000105', 'UDEMO005'],
            ['كريم أبو زيد', 'karim.demo@hc.local', '+201000000106', 'UDEMO006'],
            ['سلمى حسن', 'salma.demo@hc.local', '+201000000107', 'UDEMO007'],
            ['طارق آل ثاني', 'tarek.demo@hc.local', '+201000000108', 'UDEMO008'],
        ];

        $users = [];

        foreach ($rows as $index => [$name, $email, $phone, $code]) {
            $users[] = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => $phone,
                'code' => $code,
                'password' => 'password',
                'status' => 'active',
                'level' => 3 + $index % 4,
                'xp' => 900 + $index * 430,
                'last_seen_at' => now()->subMinutes($index * 7),
            ]);
        }

        return $users;
    }

    private function complaints(User $user): void
    {
        $rows = [
            ['complaint', 'المنصّة', 'الفيديو بيقف في نصّ الدرس', 'الدرس التالت في تدريب «مهارات العرض» بيقف عند الدقيقة السابعة ومش بيكمّل حتى بعد ما أعيد تحميل الصفحة.', 'answered'],
            ['suggestion', 'المنصّة', 'اقتراح: تنبيه قبل انتهاء مهلة الامتحان', 'يا ريت يجيلنا تنبيه قبل انتهاء مهلة الامتحان بيوم — ده هيقلّل نسبة اللي بيفوتهم الميعاد.', 'open'],
            ['complaint', 'خدمة العملاء', 'استفسار عن شحن الحساب', 'حوّلت من ثلاث أيّام والرصيد لسّه ما ظهرش. الإيصال مرفوع في الطلب.', 'closed'],
        ];

        foreach ($rows as $index => [$type, $category, $title, $body, $status]) {
            $complaint = Complaint::updateOrCreate(
                ['number' => 'TK-DEMO-'.($index + 1)],
                [
                    'user_id' => $user->id,
                    'type' => $type,
                    'category' => $category,
                    'title' => $title,
                    'body' => $body,
                    'status' => $status,
                    'closed_at' => $status === 'closed' ? now()->subDays(2) : null,
                ],
            );

            ComplaintMessage::firstOrCreate(
                ['complaint_id' => $complaint->id, 'user_id' => $user->id, 'body' => $body],
            );

            if ($status !== 'open') {
                ComplaintMessage::firstOrCreate([
                    'complaint_id' => $complaint->id,
                    'user_id' => $user->id,
                    'body' => 'شكرًا للمتابعة — الموضوع اتحلّ وتمام دلوقتي.',
                ]);
            }
        }
    }

    private function privacy(User $user): void
    {
        // ⭐ المحافظة مش هنا أصلًا — حقل عامّ دائمًا ولا يجوز إخفاؤه (12.14-د)
        $rows = [
            'phone' => 'supervisors',
            'email' => 'supervisors',
            'birthdate' => 'all_volunteers',
            'certificates' => 'all_users',
        ];

        foreach ($rows as $field => $visibility) {
            UserPrivacySetting::updateOrCreate(
                ['user_id' => $user->id, 'field' => $field],
                ['visibility' => $visibility],
            );
        }

        EmergencyContact::firstOrCreate(
            ['user_id' => $user->id, 'phone' => '+201000000900'],
            ['name' => 'سلوى عبد الرحمن', 'relation' => 'الوالدة'],
        );
    }

    private function devices(User $user): void
    {
        $rows = [
            ['موبايل — Android', '156.200.10.44', now()->subMinutes(3)],
            ['لابتوب — Chrome', '41.32.88.10', now()->subDays(1)],
        ];

        foreach ($rows as [$label, $ip, $seen]) {
            UserDevice::updateOrCreate(
                ['user_id' => $user->id, 'device_label' => $label],
                ['ip' => $ip, 'user_agent' => $label, 'last_active_at' => $seen, 'session_id' => Str::random(20)],
            );
        }
    }

    /** موافقات إظهار التواصل السارية — تظهر في «مَن يرى بياناتي» (13.4-م) */
    private function consents(User $owner, User $requester): void
    {
        ConsentRequest::updateOrCreate(
            ['owner_id' => $owner->id, 'requester_id' => $requester->id, 'field' => 'phone'],
            [
                'status' => 'granted',
                'request_expires_at' => now()->subDays(3),
                'granted_at' => now()->subDays(3),
                'consent_expires_at' => now()->addDays(27),
            ],
        );
    }
}
