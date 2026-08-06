<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Entity;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Position;
use App\Models\RepScore;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Services\Volunteer\Org\CardIssuer;
use Database\Seeders\Concerns\GrantsWithinMatrixCeiling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات تجريبيّة لمجال «قسمي والهيكل والسعة والبطاقة» (24.4-7 · 13.4-م/ف/ر/ص)
 * — ولا تُسجَّل في `DatabaseSeeder`.
 *
 * ويحمل معه **إعدادات المجال** (2.13) فلا رقم ولا نصّ محروق في الكود.
 */
class VolunteerOrgDemoSeeder extends Seeder
{
    // كتابةُ الإسناد تمرّ بنقطة القصّ نفسها التي يمرّ بها مسار الإنتاج (12.2.2)
    use GrantsWithinMatrixCeiling;

    public function run(): void
    {
        $this->settings();
        $this->permissions();
        $this->demoData();
    }

    /** كلّ رقم ونصّ في المجال إعدادٌ قابل للتعديل من لوحة الإدارة (2.13) */
    public function settings(): void
    {
        $rows = [
            // ---------------- كانفاس الهيكل (13.4-م-3)
            ['volunteer.org.collapse_threshold', 'volunteer', 'حدّ الداونلاين قبل الطيّ التلقائيّ', 'number', '30'],
            ['volunteer.org.default_depth', 'volunteer', 'عدد المستويات المفتوحة تحتي افتراضيًّا', 'number', '2'],
            ['volunteer.org.occupancy_warn_percent', 'volunteer_org', 'عتبة الإشغال الصفراء (%)', 'number', '80'],
            ['volunteer.org.occupancy_danger_percent', 'volunteer_org', 'عتبة الإشغال الحمراء (%)', 'number', '100'],
            ['volunteer.org.date_format', 'volunteer', 'صيغة التاريخ في شاشات قسمي', 'string', 'j F Y'],
            ['volunteer.org.service_duration.unknown', 'volunteer', 'نصّ مدّة الخدمة غير المعروفة', 'string', 'لسّه في أوّل الطريق'],
            ['volunteer.honorary.note', 'volunteer', 'سطر توضيح العنصر الشرفيّ', 'string', 'عنصر شرفيّ — بلا مؤشّرات ولا يدخل أيّ عدّاد'],

            // ---------------- التواصل والموافقة (13.4-م-2)
            ['volunteer.contact.mask_char', 'volunteer', 'رمز إخفاء الرقم', 'string', '•'],
            ['volunteer.contact.mask_fallback', 'volunteer', 'نصّ الرقم غير المكتمل', 'string', '•••• ••• ••'],
            ['volunteer.contact.whatsapp_template', 'volunteer', 'قالب رسالة واتساب', 'text', 'السلام عليكم :name، معاك زميلك من فريق التطوّع.'],
            ['volunteer.consent.request_hours', 'volunteer', 'صلاحيّة طلب إظهار الرقم (ساعات)', 'number', '72'],
            ['volunteer.consent.cooldown_hours', 'volunteer', 'تبريد إعادة الطلب (ساعات)', 'number', '72'],
            ['volunteer.consent.request_sent', 'volunteer', 'رسالة تأكيد إرسال الطلب', 'string', 'وصل طلبك — هيوصلك الردّ لمّا يتاح.'],

            // ---------------- صحّة القسم (13.4-ح)
            ['volunteer.health.default_period_days', 'volunteer', 'الفترة الافتراضيّة لصحّة القسم (أيّام)', 'number', '30'],
            ['volunteer.health.periods', 'volunteer', 'فترات صحّة القسم المتاحة', 'json', '[30,90]'],
            ['volunteer.health.leaderboard_size', 'volunteer', 'عدد صفوف لوحة القيادة', 'number', '10'],
            ['volunteer.health.list_size', 'volunteer', 'عدد صفوف بلوكات الرقابة', 'number', '10'],
            ['volunteer.health.ring.ok_percent', 'volunteer', 'عتبة المؤشّر العامّ الخضراء (%)', 'number', '70'],
            ['volunteer.health.ring.warn_percent', 'volunteer', 'عتبة المؤشّر العامّ الصفراء (%)', 'number', '45'],
            ['volunteer.health.retention_risk.threshold', 'volunteer', 'عدد إشارات مخاطر الفقدان للتنبيه', 'number', '2'],
            ['volunteer.health.retention_risk.late_tasks', 'volunteer', 'عدد المهامّ المتأخّرة كإشارة خطر', 'number', '2'],
            ['volunteer.health.retention_risk.note', 'volunteer', 'سطر تحذير مخاطر الفقدان', 'string', 'داخليّ للأبلاين فقط — ولا يُعرَض للمتطوّع عن نفسه أبدًا'],

            // ---------------- السعة والأحمال (13.4-ف) — مؤشّرات لا موانع
            ['volunteer.capacity.banner', 'volunteer', 'بانر السعة الثابت', 'string', 'السعة غير مانعة — لا توقف تسكينًا ولا ترقيةً ولا نقلًا'],
            ['volunteer.capacity.difference_line', 'volunteer', 'سطر التفريق بين السعة وسقف الانشغال', 'string', 'السعة = عدد أشخاص · سقف الانشغال = عدد مهامّ'],
            ['volunteer.capacity.suggestion.merge', 'volunteer', 'اقتراح الدمج', 'string', 'اقتراح: دمج مع كيان مجاور'],
            ['volunteer.capacity.suggestion.drop_layer', 'volunteer', 'اقتراح إلغاء الطبقة', 'string', 'اقتراح: إلغاء الطبقة'],
            ['volunteer.capacity.load_suggestion', 'volunteer', 'وسم الأقلّ حملًا', 'string', 'مقترَح للتسكين الجديد — اقتراح لا إلزام'],
            ['volunteer.capacity.load_note', 'volunteer', 'سطر شرح الأحمال', 'string', 'منطق الموازن: يقترح ولا يُلزِم'],
            ['volunteer.capacity.suggest_count', 'volunteer', 'عدد المقترَحين للتسكين الجديد', 'number', '3'],
            ['volunteer.capacity.min_entity_cap', 'volunteer', 'أدنى سقف محسوب لكيان', 'number', '5'],
            ['volunteer.capacity.breach_notice', 'volunteer', 'نصّ تنبيه التجاوز', 'string', 'تنبيه فقط — لا يمنع الإجراء'],

            // ---------------- بطاقة المتطوّع (13.4-ر)
            ['volunteer_card.code_prefix', 'volunteer', 'بادئة رقم البطاقة', 'string', 'VC'],
            ['volunteer_card.default_language', 'volunteer', 'لغة البطاقة الافتراضيّة', 'string', 'ar'],
            ['volunteer_card.qr.quiet_modules', 'volunteer', 'هامش الـQR (وحدات)', 'number', '2'],
            ['volunteer_card.verify_hint', 'volunteer', 'سطر تحت الـQR', 'string', 'امسح الكود للتحقّق من البطاقة'],
            ['volunteer_card.verify.valid_text', 'volunteer', 'نصّ البطاقة السارية', 'text', 'البطاقة سارية، وصاحبها متطوّع مُسكَّن عندنا.'],
            ['volunteer_card.verify.expired_text', 'volunteer', 'نصّ البطاقة المنتهية', 'text', 'البطاقة منتهية — انتهت عضويّة صاحبها، والسجلّ محفوظ.'],
            ['volunteer_card.image.no_template', 'volunteer', 'صورة البطاقة: لا تصميم مُفعَّل', 'string', 'مفيش تصميم بطاقة مُفعَّل بعد.'],
            ['volunteer_card.image.invite_qr_size', 'volunteer', 'صورة البطاقة: مقاس QR الدعوة (بكسل)', 'number', '220'],
            ['volunteer_card.image.invite_qr_margin', 'volunteer', 'صورة البطاقة: هامش QR الدعوة (بكسل)', 'number', '32'],
            ['volunteer_card.show.invite_toggle', 'volunteer', 'صفحة البطاقة: تفعيل QR الدعوة', 'string', 'أضِف QR دعوتي على الصورة'],
            ['volunteer_card.show.download_badge', 'volunteer', 'صفحة البطاقة: تحميل البادج', 'string', 'تحميل بادج الفعاليّات'],
            ['volunteer_card.show.download_story', 'volunteer', 'صفحة البطاقة: تحميل الستوري', 'string', 'تحميل صورة للنشر'],

            // ---------------- وضع «غائب» والتفويض المؤقّت (23-6)
            ['volunteer.absence.max_days', 'volunteer', 'أقصى غياب متّصل (أيّام)', 'number', '14'],
            ['volunteer.absence.max_per_month', 'volunteer', 'أقصى مرّات الغياب في الشهر', 'number', '2'],
            // ⚠️ المفاتيح **مفاتيح جدول `positions` بالحرف** (23-6): كان المزروع
            // `track_gm` ولا وجود له في الجدول، فمشرف المسار — وهو منصوصٌ عليه —
            // كان يُرَدّ صامتًا لأنّ القائمة تنكمش بلا خطأ.
            ['volunteer.absence.adder_positions', 'volunteer', 'بوزشنات مَن يضيف وضع «غائب»', 'json', '["volunteer_gm","track_supervisor","director"]'],
            // شاشة إدارة الغيابات في لوحة الإدارة (23-6 · 24)
            ['volunteer.absence.admin_rows', 'volunteer', 'عدد صفوف شاشة إدارة الغيابات', 'number', '50'],
            ['volunteer.absence.audit_rows', 'volunteer', 'عدد صفوف سجلّ تدقيق الغيابات', 'number', '15'],
            ['volunteer.absence.upcoming_days', 'volunteer', 'مدى «غيابات قادمة» في الكروت (أيّام)', 'number', '14'],
            ['volunteer.absence.ending_soon_days', 'volunteer', 'مدى «تنتهي قريبًا» في الكروت (أيّام)', 'number', '3'],

            // ---------------- سلّم الترقية الفوريّ (القسم 0 · 23-0.2)
            // نوافذ كسر التعادل المتناقصة — أوّل نافذة يظهر فيها فرق VXP تحسم
            ['volunteer.promotion_ladder.tiebreak_windows_days', 'volunteer', 'نوافذ كسر تعادل سلّم الترقية (أيّام، من الأكبر للأصغر)', 'json', '[30,21,10,7,3,1]'],
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
     * صلاحيّات شاشات المجال: `org_chart` و`capacity` و`team_health` و`memberships`.
     * صحّة القسم والسعة **لا تُمنَحان لكوردنيتور ولا تيم ليدر** — فالعضو العاديّ
     * لا يراهما ولا يعرف بوجودهما (2.15-أ-7 · 24.4-7).
     *
     * ⭐ وكلّ دور عند **سقفه** لا فوقه (12.2.1-ز-2): كان `memberships.list` يُكتَب
     * `ENTITY` للجميع — فالسوبرفايزر (سقفه SUBTREE) والتيم ليدر (TEAM) يقرآن قائمة
     * أعضاء **الكيان كلّه**، والكوردنيتور (سقفه SELF) كذلك. النطاق الآن نطاقُ الدور،
     * والكوردنيتور لا يأخذها أصلًا لأنّ `memberships.list` لا تُعرَّف بنطاق SELF.
     */
    private function permissions(): void
    {
        $grants = [
            'director' => [
                'memberships.list' => 'ENTITY', 'memberships.view' => 'SUBTREE',
                'org_chart.view' => 'ENTITY', 'org_chart.export' => 'ENTITY',
                'team_health.view' => 'ENTITY', 'team_health.export' => 'ENTITY',
                'capacity.view' => 'ENTITY', 'capacity.export' => 'ENTITY',
            ],
            'supervisor' => [
                'memberships.list' => 'SUBTREE', 'memberships.view' => 'SUBTREE',
                'org_chart.view' => 'SUBTREE',
                'team_health.view' => 'SUBTREE',
            ],
            'team_leader' => [
                'memberships.list' => 'TEAM', 'memberships.view' => 'TEAM',
                'org_chart.view' => 'TEAM',
            ],
            'coordinator' => [
                // عضو عاديّ: يرى نفسه والهيكل — ولا قائمةَ أعضاءٍ ولا صحّة قسم ولا سعة
                'memberships.view' => 'SELF',
                'org_chart.view' => 'SELF',
            ],
            'track_supervisor' => [
                'memberships.list' => 'ENTITY', 'memberships.view' => 'SUBTREE',
                'org_chart.view' => 'TRACK', 'team_health.view' => 'TRACK', 'capacity.view' => 'TRACK',
            ],
            'volunteer_gm' => [
                'memberships.list' => 'ENTITY', 'memberships.view' => 'SUBTREE',
                'org_chart.view' => 'TRACK', 'team_health.view' => 'ALL', 'capacity.view' => 'TRACK',
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
        $track = Track::where('key', 'department')->first() ?? Track::create([
            'key' => 'department', 'name_ar' => 'قسم',
        ]);

        $country = Country::firstOrCreate(['iso2' => 'EG'], [
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'phone_code' => '20',
        ]);
        $governorate = Governorate::firstOrCreate(
            ['country_id' => $country->id, 'name_ar' => 'القاهرة'],
            ['name_en' => 'Cairo'],
        );

        $root = Entity::firstOrCreate(
            ['track_id' => $track->id, 'parent_id' => null, 'name_ar' => 'قسم الإعلام'],
            ['name_en' => 'Media', 'status' => 'active', 'opened_at' => now()->subYears(2)],
        );

        $subs = collect(['التصميم', 'المونتاج', 'كتابة المحتوى'])->mapWithKeys(
            fn (string $name) => [$name => Entity::firstOrCreate(
                ['track_id' => $track->id, 'parent_id' => $root->id, 'name_ar' => $name],
                ['status' => 'active', 'opened_at' => now()->subYear()],
            )],
        );

        $positions = Position::pluck('id', 'key');

        // ---------- الأشخاص: أسماء عربيّة واقعيّة
        $director = $this->user('محمود عبد الرحمن حسن', 'VOL-DIR', $country, $governorate, 2);
        $s1 = $this->user('أحمد سيد الشرقاوي', 'VOL-SUP1', $country, $governorate, 3);
        $s2 = $this->user('منة الله إبراهيم', 'VOL-SUP2', $country, $governorate, 5);
        $s3 = $this->user('كريم أبو الفتوح', 'VOL-SUP3', $country, $governorate, 40);
        $tl1 = $this->user('يوسف عبد الله', 'VOL-TL1', $country, $governorate, 1);
        $tl2 = $this->user('سارة محمّد فؤاد', 'VOL-TL2', $country, $governorate, 2);
        $c1 = $this->user('عمر خالد', 'VOL-C1', $country, $governorate, 1);
        $c2 = $this->user('هدى مصطفى', 'VOL-C2', $country, $governorate, 4);
        $c3 = $this->user('طارق بن سليم', 'VOL-C3', $country, $governorate, 26);
        $c4 = $this->user('نورهان عادل', 'VOL-C4', $country, $governorate, 1);
        $honoraryUser = $this->user('أحمد فتحي', 'VOL-HON', $country, $governorate, 0);

        // ---------- العضويّات والأبلاين
        $mDirector = $this->membership($director, $root, $positions['director'], null, now()->subYears(2));
        $mS1 = $this->membership($s1, $subs['التصميم'], $positions['supervisor'], $mDirector, now()->subMonths(20));
        $mS2 = $this->membership($s2, $subs['المونتاج'], $positions['supervisor'], $mDirector, now()->subMonths(14));
        // «قائم بأعمال» لحين الاعتماد البشريّ (23) — يظهر بوسمه في كلّ الشاشات
        $mS3 = $this->membership($s3, $subs['كتابة المحتوى'], $positions['supervisor'], $mDirector, now()->subMonths(3), acting: true);

        $mTl1 = $this->membership($tl1, $subs['التصميم'], $positions['team_leader'], $mS1, now()->subMonths(12));
        $mTl2 = $this->membership($tl2, $subs['المونتاج'], $positions['team_leader'], $mS2, now()->subMonths(9));

        $mC1 = $this->membership($c1, $subs['التصميم'], $positions['coordinator'], $mTl1, now()->subMonths(7));
        $mC2 = $this->membership($c2, $subs['التصميم'], $positions['coordinator'], $mTl1, now()->subMonths(5));
        $mC3 = $this->membership($c3, $subs['المونتاج'], $positions['coordinator'], $mTl2, now()->subMonths(4));
        $mC4 = $this->membership($c4, $subs['كتابة المحتوى'], $positions['coordinator'], $mS3, now()->subMonths(2));

        // ⭐ العنصر الشرفيّ «أخوكم»: فوق الجميع، وبلا داونلاين ولا صلاحيّات ولا احتساب (13.4-ص)
        if (isset($positions['brother'])) {
            $this->membership($honoraryUser, $root, $positions['brother'], null, now()->subYears(3));
        }

        // ---------- درجات الالتزام: عضو نادي تميّز · متوازن · تحت الإنذار
        $reps = [
            $director->id => 6.5, $s1->id => 9.7, $s2->id => 3.2, $s3->id => 1.0,
            $tl1->id => 8.1, $tl2->id => -1.5, $c1->id => 2.0, $c2->id => -6.0,
            $c3->id => 0.5, $c4->id => 4.4,
        ];

        foreach ($reps as $userId => $score) {
            RepScore::updateOrCreate(['user_id' => $userId], ['score' => $score]);
        }

        // ---------- غياب بتفويض: «غائب حتى يوم كذا — البديل: فلان»
        MembershipAbsence::firstOrCreate(
            ['membership_id' => $mTl2->id, 'from_date' => today()->subDays(2)],
            [
                'delegate_membership_id' => $mC3->id,
                'to_date' => today()->addDays(6),
                'reason' => 'سفر عائليّ',
                'created_by' => $director->id,
            ],
        );

        // ---------- مهامّ تُغذّي مؤشّرات صحّة القسم
        $this->tasks($root, [
            [$c1, 'تصميم كوفر الحملة', 'approved', -6, -7, -6, 0],
            [$c1, 'بوستر الفعاليّة', 'in_progress', 3, null, null, 0],
            [$c2, 'كارت شكر المتطوّعين', 'approved', -4, -2, -1, 2],
            [$c2, 'هوّيّة الدفعة الجديدة', 'in_progress', -2, null, null, 1],
            [$c3, 'مونتاج فيديو القصص', 'approved', -8, -9, -8, 0],
            [$c4, 'كتابة سكربت التعريف', 'no_delivery', -5, null, null, 0],
            [$tl1, 'مراجعة دفعة التصاميم', 'in_progress', 5, null, null, 0],
        ]);

        // ---------- البطاقات: تُصدَر لحظة التسكين وتتحدّث عند الترقية والنقل (13.4-ر)
        $issuer = app(CardIssuer::class);

        foreach ([$mDirector, $mS1, $mTl1, $mC1, $mC2] as $membership) {
            $issuer->issueFor($membership);
        }

        $this->command?->info('بيانات قسمي التجريبيّة: '.Membership::count().' عضويّة');
    }

    private function user(string $name, string $code, Country $country, Governorate $governorate, int $idleDays): User
    {
        return User::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'email' => mb_strtolower($code).'@demo.local',
                'password' => 'demo-password',
                'status' => 'active',
                'activated_at' => now()->subYear(),
                'country_id' => $country->id,
                'governorate_id' => $governorate->id,
                'phone' => '+2010'.str_pad((string) crc32($code) % 100000000, 8, '0', STR_PAD_LEFT),
                'last_seen_at' => now()->subDays($idleDays),
            ],
        );
    }

    private function membership(
        User $user,
        Entity $entity,
        int $positionId,
        ?Membership $upline,
        Carbon $since,
        bool $acting = false,
    ): Membership {
        return Membership::updateOrCreate(
            ['user_id' => $user->id, 'entity_id' => $entity->id, 'position_id' => $positionId],
            [
                'upline_id' => $upline?->id,
                'is_primary' => true,
                'is_acting' => $acting,
                'started_at' => $since,
                'status' => 'active',
            ],
        );
    }

    /** @param  list<array{0: User, 1: string, 2: string, 3: int, 4: ?int, 5: ?int, 6: int}>  $rows */
    private function tasks(Entity $entity, array $rows): void
    {
        foreach ($rows as [$owner, $title, $status, $deadline, $delivered, $approved, $returns]) {
            Task::updateOrCreate(
                ['title' => $title, 'owner_id' => $owner->id],
                [
                    'entity_id' => $entity->id,
                    'status' => $status,
                    'priority' => $returns > 1 ? 1 : 2,
                    'deadline_at' => now()->addDays($deadline),
                    'delivered_at' => $delivered === null ? null : now()->addDays($delivered),
                    'approved_at' => $approved === null ? null : now()->addDays($approved),
                    'return_count' => $returns,
                    'late_due_to_child' => $returns > 1,
                ],
            );
        }
    }
}
