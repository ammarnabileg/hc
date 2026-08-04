<?php

namespace Database\Seeders;

use App\Models\ConsentRequest;
use App\Models\EmergencyContact;
use App\Models\Kudos;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPrivacySetting;
use Database\Seeders\Concerns\GrantsWithinMatrixCeiling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات تجريبيّة لمجال «بروفايل المتطوّع» (13.4-م · 10.0) — ولا تُسجَّل في `DatabaseSeeder`.
 *
 * وتحمل معها **إعدادات المجال** (2.13): كلّ نصّ ورقم في الشاشات مصدره هنا،
 * فلا كلمة محروقة في الكود ولا في الواجهة.
 *
 * تعتمد على `VolunteerOrgDemoSeeder` في بناء الهيكل والأشخاص (VOL-*).
 */
class VolunteerProfileDemoSeeder extends Seeder
{
    // كتابةُ الإسناد تمرّ بنقطة القصّ نفسها التي يمرّ بها مسار الإنتاج (12.2.2)
    use GrantsWithinMatrixCeiling;

    public function run(): void
    {
        $this->settings();
        $this->permissions();
        $this->demoData();
    }

    public function settings(): void
    {
        $rows = [
            // ---------------- التابات ومستويات المشاهدة (13.4-م)
            ['volunteer.profile.tab.overview', 'volunteer', 'اسم تاب أوفر فيو التطوّع', 'string', 'التطوّع'],
            ['volunteer.profile.tab.contact', 'volunteer', 'اسم تاب التواصل', 'string', 'التواصل'],
            ['volunteer.profile.tab.organization', 'volunteer', 'اسم تاب الهيكل التنظيميّ', 'string', 'الهيكل التنظيميّ'],
            ['volunteer.profile.tab.performance', 'volunteer', 'اسم تاب الأداء', 'string', 'الأداء'],
            ['volunteer.profile.tab.notes', 'volunteer', 'اسم تاب الملاحظات الإداريّة', 'string', 'ملاحظات إداريّة'],
            ['volunteer.profile.level.owner', 'volunteer', 'سطر مستوى المشاهدة — صاحب البروفايل', 'string', 'دي صفحتك — بتشوف كلّ حاجة عدا الملاحظات الإداريّة'],
            ['volunteer.profile.level.upline', 'volunteer', 'سطر مستوى المشاهدة — الأبلاين', 'string', 'مشاهدة مشرف'],
            ['volunteer.profile.level.admin', 'volunteer', 'سطر مستوى المشاهدة — الأدمن', 'string', 'مشاهدة إداريّة'],
            ['volunteer.profile.level.peer', 'volunteer', 'سطر مستوى المشاهدة — الزميل', 'string', 'المشاهدة العامّة'],

            // ---------------- أوفر فيو (13.4-م-1)
            ['volunteer.profile.overview.upline_alert', 'volunteer', 'تنبيه الأبلاين عند حدّ الإنذار', 'string', 'درجة الالتزام عند :name وصلت لحدّ الإنذار — كلمة منك دلوقتي بتفرق.'],
            ['volunteer.profile.overview.kudos_preview', 'volunteer', 'عدد الشكرات المعروضة في الأوفر فيو', 'number', '3'],
            ['volunteer.profile.overview.certificates_preview', 'volunteer', 'عدد الشهادات المعروضة', 'number', '4'],
            ['volunteer.profile.overview.tasks_preview', 'volunteer', 'عدد المهامّ الجارية المعروضة لصاحبه', 'number', '3'],
            ['volunteer.profile.promotion.min_months', 'volunteer', 'أدنى مدّة خدمة لبار البوزشن الجاي (شهور)', 'number', '6'],
            ['volunteer.profile.promotion.min_rep', 'volunteer', 'درجة الالتزام المطلوبة للبوزشن الجاي', 'number', '5'],

            // ---------------- التواصل والموافقة (13.4-م-2)
            ['volunteer.profile.contact.request_label', 'volunteer', 'نصّ زرّ طلب الإظهار', 'string', 'اطلب إظهار :field'],
            ['volunteer.profile.contact.time_format', 'volunteer', 'صيغة عرض التوقيت المحليّ', 'string', 'g:i A'],
            ['volunteer.profile.contact.transparency', 'volunteer', 'سطر الشفافيّة المسبقة', 'text', 'مشرفيك يشوفوا بيانات تواصلك — ده حقّ نظاميّ للتنسيق، مش موافقة تتسحب.'],
            ['volunteer.profile.consent.reason_max', 'volunteer', 'أقصى طول لسبب الطلب', 'number', '300'],
            ['volunteer.profile.consent.neutral', 'volunteer', 'النصّ المحايد للطالب في كلّ الحالات', 'string', 'غير متاح / انتهت المدّة'],
            ['volunteer.profile.consent.notify_title', 'volunteer', 'عنوان إشعار طلب الإظهار', 'string', ':name طالب إظهار :field'],
            ['volunteer.profile.consent.notify_body', 'volunteer', 'نصّ إشعار الطلب بسبب مكتوب', 'string', 'السبب: :reason'],
            ['volunteer.profile.consent.notify_no_reason', 'volunteer', 'نصّ إشعار الطلب بلا سبب', 'string', 'من غير سبب مكتوب — القرار ليك.'],
            ['volunteer.profile.consent.approved_message', 'volunteer', 'رسالة الموافقة', 'string', 'سهّلت التعاون 🤝 — بياناتك هتبان له للمدّة المحدّدة بس.'],
            ['volunteer.profile.consent.denied_message', 'volunteer', 'رسالة الرفض الصامت لصاحب البيانات', 'string', 'تمام — الطلب اتقفل، وبياناتك زيّ ما هي.'],
            ['volunteer.profile.consent.ledger_size', 'volunteer', 'عدد صفوف سجلّ الطلبات', 'number', '50'],
            ['volunteer.profile.consent.outcome.granted', 'volunteer', 'نتيجة: موافقة', 'string', 'اتوافق'],
            ['volunteer.profile.consent.outcome.denied', 'volunteer', 'نتيجة: رفض', 'string', 'اترفض'],
            ['volunteer.profile.consent.outcome.expired', 'volunteer', 'نتيجة: انتهاء', 'string', 'انتهت المدّة'],
            ['volunteer.profile.consent.outcome.revoked', 'volunteer', 'نتيجة: سحب', 'string', 'اتسحبت'],
            ['volunteer.profile.consent.outcome.pending', 'volunteer', 'نتيجة: معلّق', 'string', 'مستنّي ردّ'],

            // ---------------- الهيكل والأداء (13.4-م-3 · 13.4-م-4)
            ['volunteer.profile.org.movements_size', 'volunteer', 'عدد صفوف سجلّ الحركات التنظيميّة', 'number', '20'],
            ['volunteer.profile.performance.months', 'volunteer', 'عدد شهور التطوّر الشهريّ', 'number', '6'],
            ['volunteer.profile.performance.anonymous_note', 'volunteer', 'سطر تنبيه مجهوليّة التقييمات', 'string', 'التقييمات متوسّطات مجهولة — مفيش أسماء ولا درجات فرديّة.'],

            // ---------------- الملاحظات الإداريّة (13.4-م-5)
            ['volunteer.profile.notes.list_size', 'volunteer', 'عدد الملاحظات المعروضة', 'number', '30'],
            ['volunteer.profile.notes.audit_size', 'volunteer', 'عدد صفوف تدقيق الملاحظات', 'number', '20'],
            ['volunteer.profile.notes.min_chars', 'volunteer', 'أدنى طول للملاحظة', 'number', '5'],
            ['volunteer.profile.notes.max_chars', 'volunteer', 'أقصى طول للملاحظة', 'number', '2000'],
            ['volunteer.profile.notes.saved', 'volunteer', 'رسالة حفظ الملاحظة', 'string', 'اتحفظت الملاحظة ✓ — سرّيّة ومسجّلة في التدقيق.'],

            // ---------------- تقرير الترقية (13.4-م-1)
            ['volunteer.profile.report.days', 'volunteer', 'مدى تقرير الترقية (أيّام)', 'number', '30'],
            ['volunteer.profile.report.prefix', 'volunteer', 'بادئة رقم مرجع التقرير', 'string', 'VPR'],
            ['volunteer.profile.report.reference_format', 'volunteer', 'صيغة رقم مرجع التقرير', 'string', ':prefix-:date-:code-:id'],

            // ---------------- قائمة «إجراءات» (13.4-ن-هـ)
            ['volunteer.profile.actions.behavior', 'volunteer', 'عنصر إجراءات: معاملة سلوك', 'string', 'معاملة سلوك (Rep)'],
            ['volunteer.profile.actions.org', 'volunteer', 'عنصر إجراءات: نقل وترقية', 'string', 'نقل / ترقية / تغيير أبلاين'],
            ['volunteer.profile.actions.offboarding', 'volunteer', 'عنصر إجراءات: إنهاء خدمة', 'string', 'إنهاء خدمة / تعليق'],
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

    /**
     * صلاحيّات شاشات المجال. القاعدة: **الأبلاين المخوَّل** يرى الأداء والتواصل
     * والملاحظات داخل نطاقه فقط، والكوردنيتور لا يرى شيئًا من ذلك عن غيره (2.15-أ-7).
     */
    private function permissions(): void
    {
        $grants = [
            'director' => [
                'contact_consent.create' => 'SELF', 'contact_consent.approve' => 'SELF',
                'contact_consent.reject' => 'SELF', 'contact_consent.list' => 'ENTITY',
                'contact_consent.delete' => 'SELF',
                'kudos.create' => 'SELF', 'user_profile.view' => 'SELF',
                'admin_notes.view' => 'SUBTREE', 'admin_notes.create' => 'SUBTREE',
                'reports_volunteer.view' => 'SUBTREE', 'audit_logs.view' => 'SUBTREE',
                'rep_manual.create' => 'SUBTREE', 'memberships.edit' => 'SUBTREE',
                'retention_risk.view' => 'SUBTREE',
            ],
            'supervisor' => [
                'contact_consent.create' => 'SELF', 'contact_consent.approve' => 'SELF',
                'contact_consent.reject' => 'SELF', 'contact_consent.delete' => 'SELF',
                'kudos.create' => 'SELF', 'user_profile.view' => 'SELF',
                'admin_notes.view' => 'SUBTREE', 'admin_notes.create' => 'SUBTREE',
                'reports_volunteer.view' => 'SUBTREE', 'audit_logs.view' => 'SUBTREE',
                'rep_manual.create' => 'SUBTREE', 'retention_risk.view' => 'SUBTREE',
            ],
            'team_leader' => [
                'contact_consent.create' => 'SELF', 'contact_consent.approve' => 'SELF',
                'contact_consent.reject' => 'SELF', 'contact_consent.delete' => 'SELF',
                'kudos.create' => 'SELF', 'user_profile.view' => 'SELF',
                'reports_volunteer.view' => 'TEAM', 'retention_risk.view' => 'TEAM',
            ],
            'coordinator' => [
                // عضو عاديّ: يطلب ويوافق ويشكر — ولا يرى ملاحظات ولا مخاطر فقدان عن غيره
                'contact_consent.create' => 'SELF', 'contact_consent.approve' => 'SELF',
                'contact_consent.reject' => 'SELF', 'contact_consent.delete' => 'SELF',
                'kudos.create' => 'SELF', 'user_profile.view' => 'SELF',
            ],
            'super_admin' => [
                'users.view' => 'ALL', 'admin_notes.view' => 'ALL', 'admin_notes.create' => 'ALL',
                'audit_logs.view' => 'ALL', 'reports_volunteer.view' => 'ALL',
                'contact_consent.list' => 'ALL', 'contact_consent.create' => 'SELF',
                'contact_consent.approve' => 'SELF', 'contact_consent.reject' => 'SELF',
                'kudos.create' => 'SELF', 'user_profile.view' => 'SELF',
            ],
        ];

        /*
         | ⭐ الكتابة بنقطة القصّ نفسها (12.2.2): كانت الحلقة تكتب النطاق المطلوب
         | كما هو، فيدخل من هذا الباب الخلفيّ صفٌّ فوق سقف المصفوفة بينما مسار
         | الإنتاج (`RolePermissionSeeder`) يقصّه. والحكم واحدٌ لا حكمان.
         |
         | و`matrixFloor` **لازم لا زائد**: مفاتيحُ هنا نطاقُها المنصوص **كلُّه
         | أوسع من سقف الدور**، وأظهرها `contact_consent.create` — «**TEAM ·
         | ENTITY**» بلا SELF («إرسال «طلب إظهار» صالح 72 ساعة» — 12.2.2).
         | فطلبُها `@SELF` كان **اختراعَ نطاقٍ لا تعرفه المصفوفة**، وإسقاطُها
         | يقفل مسار 13.4-م-2 في وجه الكوردنيتور — وهو صاحبه. فتُكتَب بأضيق ما
         | تسمح به المصفوفة (TEAM): لا فوق السقف، ولا سحبَ منحٍ يفتحه النصّ.
         */
        foreach ($grants as $roleKey => $permissions) {
            $this->grantKeysWithinCeiling(Role::where('key', $roleKey)->value('id'), $permissions, matrixFloor: true);
        }
    }

    private function demoData(): void
    {
        $c1 = User::where('code', 'VOL-C1')->first();
        $c2 = User::where('code', 'VOL-C2')->first();
        $c3 = User::where('code', 'VOL-C3')->first();
        $tl1 = User::where('code', 'VOL-TL1')->first();

        if (! $c1 || ! $c2 || ! $c3 || ! $tl1) {
            $this->command?->warn('شغّل VolunteerOrgDemoSeeder الأوّل — بيانات البروفايل مبنيّة عليه.');

            return;
        }

        // ---------- شكر بأسبابه: القصّة أقوى من العدّاد (13.4-م-1)
        foreach ([
            [$tl1, $c1, 'سلّم تصميم الحملة قبل الديدلاين بيوم وراجعه بنفسه.'],
            [$c2, $c1, 'ساعدني في تجهيز الكارت وأنا مضغوطة.'],
            [$c3, $c1, 'شرح للفريق الجديد إزّاي يستخدموا القالب.'],
        ] as [$sender, $receiver, $reason]) {
            Kudos::firstOrCreate(
                ['sender_id' => $sender->id, 'receiver_id' => $receiver->id, 'reason' => $reason],
                ['vxp_awarded' => (float) setting('kudos.vxp_value', 20)],
            );
        }

        // ---------- خصوصيّة الحقول: واحد فتح رقمه لكلّ المتطوّعين، وواحد قافله على مشرفيه
        UserPrivacySetting::updateOrCreate(
            ['user_id' => $c3->id, 'field' => 'phone'],
            ['visibility' => 'all_volunteers'],
        );
        UserPrivacySetting::updateOrCreate(
            ['user_id' => $c2->id, 'field' => 'phone'],
            ['visibility' => 'supervisors'],
        );

        // ---------- جهة الطوارئ: ظاهرة دائمًا للأبلاينز (13.4-م-2)
        EmergencyContact::firstOrCreate(
            ['user_id' => $c1->id, 'name' => 'خالد عبد العزيز'],
            ['phone' => '+201000000001', 'relation' => 'الوالد'],
        );

        // ---------- طلبات إظهار في حالاتها الأربع — مادّة مؤشّرات الثقة (13.4-ك)
        $requests = [
            // معلّق مستنّي ردًّا — بسبب مكتوب يرفع نسبة القبول
            [$c1, $c2, 'phone', 'pending', 'محتاج أنسّق معاكي تسليم الكارت النهارده.', null, null],
            // موافقة سارية — الحقل مفتوح للمدّة المحدّدة ثمّ يرجع مقفولًا
            [$c3, $c1, 'phone', 'granted', 'تنسيق مونتاج الفيديو.', now()->subDays(2), null],
            // رفض صامت — والتبريد يبدأ من لحظة الرفض لا من الإنشاء
            [$c2, $c3, 'email', 'denied', null, null, now()->subHours(5)],
            // انتهاء في صمت — منتهٍ لا مرفوض
            [$c1, $c3, 'email', 'expired', 'حابب أبعتله ملفّ الهوّيّة.', null, now()->subDays(4)],
        ];

        foreach ($requests as [$requester, $owner, $field, $status, $reason, $grantedAt, $respondedAt]) {
            ConsentRequest::updateOrCreate(
                ['requester_id' => $requester->id, 'owner_id' => $owner->id, 'field' => $field],
                [
                    'status' => $status,
                    'reason' => $reason,
                    'request_expires_at' => $status === 'pending'
                        ? now()->addHours((int) setting('volunteer.consent.request_hours', 72))
                        : now()->subHours(6),
                    'granted_at' => $grantedAt,
                    'responded_at' => $respondedAt ?? $grantedAt,
                    'decided_by' => in_array($status, ['granted', 'denied'], true) ? $owner->id : null,
                    'consent_expires_at' => $status === 'granted'
                        ? now()->addDays((int) setting('account.consent.duration_days', 30))
                        : null,
                    'cooldown_until' => in_array($status, ['denied', 'expired'], true)
                        ? now()->addHours((int) setting('volunteer.consent.cooldown_hours', 72))
                        : null,
                ],
            );
        }

        // ---------- ملاحظة إداريّة سرّيّة واحدة — أداة قرار لا تصفية حساب (13.4-م-5)
        if (DB::table('volunteer_profile_notes')->where('user_id', $c1->id)->doesntExist()) {
            DB::table('volunteer_profile_notes')->insert([
                'user_id' => $c1->id,
                'author_id' => $tl1->id,
                'body' => 'جاهز للترقية لتيم ليدر: بيقود مهامّ الفريق فعليًّا من شهرين وبيراجع شغل غيره.',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);
        }

        $this->command?->info('بيانات بروفايل المتطوّع التجريبيّة: '.ConsentRequest::count().' طلب إظهار');
    }
}
