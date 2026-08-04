<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\ConsentRequest;
use App\Models\Currency;
use App\Models\EmergencyContact;
use App\Models\HelpArticle;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserPrivacySetting;
use App\Models\WalletBalance;
use App\Services\Gamification\LevelResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
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
        $this->screenTextSettings();
        $this->helpArticles();

        $users = $this->users();
        $this->complaints($users[0]);
        $this->privacy($users[0]);
        $this->devices($users[0]);
        $this->consents($users[0], $users[1]);
    }

    /**
     * **نصوص شاشات الحساب** (2.13-أ: «النصوص الظاهرة للمستخدم») — كلّ جملةٍ
     * يقرؤها المستخدم على `resources/views/account/**` لها مفتاحها هنا،
     * والوحدة **جملةٌ كاملة** كما تُقرَأ لا كلمةً مقتطعة.
     *
     * ⚠️ عناوينٌ **منصوصة حرفيًّا في الدستور**: «حسابي» و«الإعدادات»
     * و«الخصوصيّة والأمان» و«البحث» (24.5 · 12.0) وتاب «الأمان» (2.3) —
     * افتراضيُّها هو النصّ المنصوص، وتغييرُه من اللوحة يخالف الخريطة.
     *
     * ⛔ وما **لم** يُنقَل عمدًا: قيم `data-keywords` — مفاتيح بحثٍ داخليّة
     * **لا تُعرَض** على الشاشة، و2.13-أ تخصّ «النصوص الظاهرة للمستخدم».
     */
    public function screenTextSettings(): void
    {
        $rows = [
            ['account.privacy.breadcrumb_root', 'الخصوصيّة: جذر مسار التنقّل (منصوص في 24.5)', 'حسابي'],
            ['account.privacy.breadcrumb_settings', 'الخصوصيّة: مسار الإعدادات (منصوص في 24.5)', 'الإعدادات'],
            ['account.privacy.consent_expires', 'الخصوصيّة: تاريخ انتهاء الموافقة (:date)', '· بتنتهي :date'],
            ['account.privacy.consents_empty', 'الخصوصيّة: الحالة الفارغة لمَن يرى بياناتي', 'مفيش حدّ بيشوف بياناتك دلوقتي.'],
            ['account.privacy.consents_hint', 'الخصوصيّة: شرح قائمة الموافقات', 'دي الموافقات اللي إنت وافقت عليها بنفسك — وتقدر تسحبها في أيّ وقت.'],
            ['account.privacy.consents_title', 'الخصوصيّة: عنوان مَن يرى بياناتي', 'مَن يرى بياناتي'],
            ['account.privacy.field_select_aria', 'الخصوصيّة: قائمة الحقل لقارئ الشاشة (:field)', 'خصوصيّة :field'],
            ['account.privacy.fields_hint', 'الخصوصيّة: شرح خصوصيّة الحقول', 'اختار مين يشوف كلّ حقل — والحسّاس مقفول افتراضيًّا.'],
            ['account.privacy.fields_title', 'الخصوصيّة: عنوان خصوصيّة كلّ حقل', 'خصوصيّة كلّ حقل'],
            ['account.privacy.governorate_always_public', 'الخصوصيّة: قاعدة ظهور المحافظة (12.14-د)', 'المحافظة بتفضل ظاهرة للكلّ على طول — دي قاعدة ثابتة في المنصّة.'],
            ['account.privacy.revoke_action', 'الخصوصيّة: زرّ سحب الموافقة', 'سحب'],
            ['account.privacy.security_tab_link', 'الخصوصيّة: رابط تاب الأمان', 'نفس إعدادات الأمان موجودة كمان في تاب «الأمان» بصفحة الإعدادات'],
            ['account.privacy.sensitive_tag', 'الخصوصيّة: وسم الحقل الحسّاس', '· حسّاس'],
            ['account.privacy.subtitle', 'الخصوصيّة: السطر تحت العنوان', 'مين بيشوف بياناتك، وإزاي تحمي حسابك.'],
            ['account.privacy.title', 'الخصوصيّة: عنوان الشاشة (منصوص في 24.5)', 'الخصوصيّة والأمان'],
            ['account.search.empty_idle', 'البحث: الحالة قبل الكتابة', 'اكتب كلمة وابدأ البحث.'],
            ['account.search.empty_no_results', 'البحث: الحالة الفارغة بلا نتائج', 'مفيش نتائج — جرّب كود أو اسم تاني.'],
            ['account.search.input_aria', 'البحث: خانة البحث لقارئ الشاشة', 'كلمة البحث'],
            ['account.search.load_more', 'البحث: زرّ عرض المزيد', 'عرض المزيد'],
            ['account.search.place_missing', 'البحث: الدولة/المحافظة غير المضافة', 'مش مضافة'],
            ['account.search.placeholder', 'البحث: تلميح خانة البحث', 'اكتب كود أو اسم أو بريد أو رقم موبايل'],
            ['account.search.privacy_note', 'البحث: سطر سياسة الخصوصيّة', 'البحث بالبريد أو رقم الموبايل وسيلة وصول بس — النتيجة بتفتح البروفايل العامّ ومفيش أيّ بيانات حسّاسة.'],
            ['account.search.results_count', 'البحث: عدد النتائج (:total)', ':total نتيجة'],
            ['account.search.submit', 'البحث: زرّ البحث', 'إبحث'],
            ['account.search.subtitle', 'البحث: السطر تحت العنوان', 'ادخل على أيّ حدّ من الكود أو الاسم.'],
            ['account.search.title', 'البحث: عنوان الشاشة (منصوص في 24.5)', 'البحث'],
            ['account.search.view_action', 'البحث: زرّ عرض البروفايل', 'عرض'],
            ['account.security.change_password_action', 'الأمان: زرّ تغيير كلمة السرّ', 'تغيير'],
            ['account.security.confirm_password', 'الأمان: خانة تأكيد كلمة السرّ', 'تأكيد كلمة السرّ'],
            ['account.security.current_device', 'الأمان: وسم الجهاز الحاليّ', 'الجهاز الحاليّ'],
            ['account.security.current_password', 'الأمان: خانة كلمة السرّ الحاليّة', 'كلمة السرّ الحاليّة'],
            ['account.security.end_session_action', 'الأمان: زرّ إنهاء الجلسة', 'إنهاء'],
            ['account.security.export_action', 'الأمان: زرّ تحميل البيانات', 'تحميل بياناتي'],
            ['account.security.export_hint', 'الأمان: شرح ملفّ تحميل البيانات', 'ملفّ JSON فيه كلّ اللي المنصّة محتفظة بيه عنك.'],
            ['account.security.last_active', 'الأمان: آخر نشاط للجهاز (:when)', '· آخر نشاط :when'],
            ['account.security.logout_all_confirm', 'الأمان: تأكيد الخروج من كلّ الأجهزة', 'هنقفل كلّ الجلسات على كلّ الأجهزة — وهتحتاج تسجّل دخولك تاني. نكمّل؟'],
            ['account.security.logout_all_hint', 'الأمان: شرح الخروج من كلّ الأجهزة', 'بيقفل حسابك على كلّ الأجهزة — بما فيها الجهاز ده.'],
            ['account.security.new_password', 'الأمان: خانة كلمة السرّ الجديدة', 'كلمة السرّ الجديدة'],
            ['account.security.password_title', 'الأمان: عنوان بلوك كلمة السرّ', 'كلمة السرّ'],
            ['account.security.sessions_empty', 'الأمان: الحالة الفارغة للجلسات', 'مفيش جلسات مسجّلة دلوقتي.'],
            ['account.security.sessions_hint', 'الأمان: شرح الجلسات النشطة', 'دي الأجهزة اللي حسابك مفتوح عليها دلوقتي.'],
            ['account.security.sessions_title', 'الأمان: عنوان الجلسات النشطة', 'الجلسات النشطة'],
            ['account.security.unknown_device', 'الأمان: اسم الجهاز المجهول', 'جهاز'],
            ['account.settings.advanced_mode_hint', 'الإعدادات: شرح الوضع المتقدّم', 'بيفتح كلّ اللي اتخفى في الصفحات — وعلى الموبايل بيفتح كصفحة كاملة.'],
            ['account.settings.autosave_failed', 'الإعدادات: رسالة تعثّر الحفظ', 'تعذّر الحفظ'],
            ['account.settings.autosave_retry', 'الإعدادات: رسالة تعثّر الحفظ مع دعوة الإعادة', 'تعذّر الحفظ — جرّب تاني'],
            ['account.settings.avatar_hint', 'الإعدادات: شرح الصورة الشخصيّة (:kb)', 'بنقصّها مربّعة تلقائيًّا، وأقصى حجم :kb كيلوبايت.'],
            ['account.settings.avatar_label', 'الإعدادات: عنوان الصورة الشخصيّة', 'الصورة الشخصيّة'],
            ['account.settings.avatar_save', 'الإعدادات: زرّ حفظ الصورة', 'حفظ الصورة'],
            ['account.settings.breadcrumb_root', 'الإعدادات: جذر مسار التنقّل (منصوص في 24.5)', 'حسابي'],
            ['account.settings.contact_warn_badge', 'الإعدادات: شارة تنبيه تغيير التواصل', 'خُد بالك'],
            ['account.settings.contact_warn_message', 'الإعدادات: تنبيه أثر تغيير التواصل (:count)', 'لو غيّرت البريد أو رقم الموبايل، هيتوقف عرض بياناتك لـ :count من اللي وافقت لهم قبل كده — والموافقة القديمة مش بتنتقل للبيانات الجديدة.'],
            ['account.settings.country_placeholder', 'الإعدادات: خيار اختيار الدولة', 'اختر الدولة'],
            ['account.settings.email_channel_hint', 'الإعدادات: شرح قناة البريد', 'ده بيوقف رسايل المنشورات على بريدك بس — رموز الدخول واستعادة كلمة السرّ هتفضل توصلك دايمًا.'],
            ['account.settings.email_channel_off', 'الإعدادات: خيار إيقاف رسايل البريد', 'متوصلنيش'],
            ['account.settings.email_channel_on', 'الإعدادات: خيار تشغيل رسايل البريد', 'توصلني'],
            ['account.settings.emergency_add', 'الإعدادات: زرّ إضافة جهة الطوارئ', 'إضافة'],
            ['account.settings.emergency_delete', 'الإعدادات: زرّ مسح جهة الطوارئ', 'مسح'],
            ['account.settings.emergency_empty', 'الإعدادات: الحالة الفارغة لجهات الطوارئ', 'مفيش جهة طوارئ مضافة.'],
            ['account.settings.emergency_hint', 'الإعدادات: شرح جهة الطوارئ', 'بتظهر لمشرفيك وقت الحاجة بس — ومش بتظهر لباقي الناس.'],
            ['account.settings.emergency_name', 'الإعدادات: خانة اسم جهة الطوارئ', 'الاسم'],
            ['account.settings.emergency_phone', 'الإعدادات: خانة موبايل جهة الطوارئ', 'رقم الموبايل'],
            ['account.settings.emergency_relation', 'الإعدادات: خانة صلة القرابة', 'صلة القرابة'],
            ['account.settings.emergency_title', 'الإعدادات: عنوان بلوك جهة الطوارئ', 'جهة الطوارئ (اختياريّ)'],
            ['account.settings.field_advanced_mode', 'الإعدادات: عنوان حقل الوضع المتقدّم', 'وضع متقدّم'],
            ['account.settings.field_country', 'الإعدادات: عنوان حقل الدولة', 'الدولة'],
            ['account.settings.field_email', 'الإعدادات: عنوان حقل البريد', 'البريد الإلكترونيّ'],
            ['account.settings.field_email_channel', 'الإعدادات: عنوان حقل رسايل البريد', 'رسايل البريد'],
            ['account.settings.field_governorate', 'الإعدادات: عنوان حقل المحافظة', 'المحافظة'],
            ['account.settings.field_language', 'الإعدادات: عنوان حقل اللغة', 'اللغة'],
            ['account.settings.field_name', 'الإعدادات: عنوان حقل الاسم', 'الاسم'],
            ['account.settings.field_phone', 'الإعدادات: عنوان حقل رقم الموبايل', 'رقم الموبايل'],
            ['account.settings.field_simple_mode', 'الإعدادات: عنوان حقل الوضع المبسّط', 'الوضع المبسّط العامّ'],
            ['account.settings.field_sound', 'الإعدادات: عنوان حقل صوت المنصّة', 'صوت المنصّة'],
            ['account.settings.field_theme', 'الإعدادات: عنوان حقل وضع المظهر', 'الوضع'],
            ['account.settings.governorate_hint', 'الإعدادات: شرح ظهور المحافظة (12.14-د)', 'المحافظة بتظهر لكلّ الناس على بروفايلك — ودي قاعدة ثابتة في المنصّة.'],
            ['account.settings.governorate_placeholder', 'الإعدادات: خيار اختيار المحافظة', 'اختر المحافظة'],
            ['account.settings.language_ar', 'الإعدادات: اسم اللغة العربيّة في القائمة', 'العربيّة'],
            ['account.settings.notifications_note', 'الإعدادات: سطر قنوات الإشعارات', 'الإشعارات بتوصلك في التاب والجرس دايمًا، وتقدر تظبط تفاصيلها من مركز الإشعارات.'],
            ['account.settings.save_action', 'الإعدادات: زرّ الحفظ اليدويّ للحقل', 'حفظ'],
            ['account.settings.saved_flag', 'الإعدادات: علامة تمام الحفظ', 'اتحفظ ✓'],
            ['account.settings.search_aria', 'الإعدادات: خانة البحث لقارئ الشاشة', 'بحث داخل الإعدادات'],
            ['account.settings.search_empty', 'الإعدادات: الحالة الفارغة لبحث الإعدادات', 'مفيش إعداد بالاسم ده — جرّب كلمة تانية.'],
            ['account.settings.search_placeholder', 'الإعدادات: تلميح بحث الإعدادات', 'دوّر على إعداد… مثال: اللغة، الصوت، الأفاتار'],
            ['account.settings.simple_mode_hint', 'الإعدادات: شرح الوضع المبسّط', 'بيخفي «الوضع المتقدّم» من كلّ الصفحات دفعةً واحدة.'],
            ['account.settings.sound_hint', 'الإعدادات: شرح صوت المنصّة', 'بيتحكّم في أصوات الاحتفال ولحظات النجاح.'],
            ['account.settings.subtitle', 'الإعدادات: السطر تحت العنوان', 'كلّ تعديل بيتحفظ لوحده — مش محتاج تدوس حفظ.'],
            ['account.settings.tab_account', 'الإعدادات: تاب الحساب', 'الحساب'],
            ['account.settings.tab_appearance', 'الإعدادات: تاب المظهر', 'المظهر'],
            ['account.settings.tab_emergency', 'الإعدادات: تاب جهة الطوارئ', 'جهة الطوارئ'],
            ['account.settings.tab_privacy', 'الإعدادات: رابط تاب الخصوصيّة', 'الخصوصيّة'],
            ['account.settings.tab_security', 'الإعدادات: تاب الأمان (منصوص في 2.3)', 'الأمان'],
            ['account.settings.tab_sound', 'الإعدادات: تاب الصوت', 'الصوت'],
            ['account.settings.tabs_nav_aria', 'الإعدادات: شريط التابات لقارئ الشاشة', 'مجموعات الإعدادات'],
            ['account.settings.theme_dark', 'الإعدادات: خيار الوضع الداكن', 'داكن'],
            ['account.settings.theme_light', 'الإعدادات: خيار الوضع الفاتح', 'فاتح'],
            ['account.settings.title', 'الإعدادات: عنوان الشاشة (منصوص في 24.5)', 'الإعدادات'],
            ['account.settings.toggle_off', 'الإعدادات: خيار «متوقّف» في التوجلات', 'متوقّف'],
            ['account.settings.toggle_on', 'الإعدادات: خيار «مفعَّل» في التوجلات', 'مفعَّل'],
        ];

        foreach ($rows as [$key, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'account',
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        Cache::forget('settings');
    }

    /** إعدادات المجال — نمط المفتاح «المجال.الميزة.المفتاح» (2.13) */
    public function settings(): void
    {
        $rows = [
            // الشكاوى والمقترحات (11)
            // ⚠️ أسباب الشكوى **لا تُبذَر هنا**: مفتاحها الموحَّد `complaints.reasons`
            // في سيدر الإعدادات (مسار الإنتاج) — ومفتاحٌ ثانٍ يعني تحريرًا بلا أثر.
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
                'xp' => 900 + $index * 430,
                'last_seen_at' => now()->subMinutes($index * 7),
            ]);
        }

        /*
         | ⭐ **XP في المحفظة أوّلًا، والمستوى مشتقٌّ لا مزروع** (ن-2).
         |
         | كان الصفّ يكتب `'level' => 3 + $index % 4` — رقمًا لا صلة له بـXP،
         | فيقول هيدر البروفايل «مستوى الحساب 6» لمستخدمٍ يقول رادارُه «مستوى 3».
         | وكان يكتب `users.xp` وحده بلا صفّ محفظة، **والمحفظة هي مصدر XP**
         | (19.2، وعليها يرتّب الليدر بورد بـCOALESCE) — فأوّل حركةٍ حقيقيّة تفتح
         | المحفظة برصيدٍ صغير فيهبط XP المعروض من 1,330 إلى 20 فجأةً.
         |
         | فالبذرة الآن تكتب **الاثنين متطابقين**، والمستوى يُشتقّ من المصدر الواحد.
         */
        $xpCurrency = Currency::where('code', (string) setting('wallet.currency.xp_code', 'xp'))->first();
        $levels = app(LevelResolver::class);

        foreach ($users as $user) {
            if ($xpCurrency) {
                WalletBalance::updateOrCreate(
                    ['user_id' => $user->id, 'currency_id' => $xpCurrency->id],
                    ['balance' => (int) $user->xp, 'lifetime_earned' => (int) $user->xp, 'lifetime_spent' => 0],
                );
            }

            $levels->sync($user->refresh());
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
