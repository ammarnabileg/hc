<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingPost;
use App\Models\MeetingQuestion;
use App\Models\Permission;
use App\Models\RepRule;
use App\Models\Setting;
use App\Models\User;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Objections\ObjectionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات تجريبيّة لمجال الاجتماعات والمعاملات والاعتراضات.
 * ولا تُسجَّل في `DatabaseSeeder` — تُجمَّع مركزيًّا.
 *
 * ومعها إعدادات المجال (2.13): كلّ رقم في الشاشات مصدره هنا لا الكود.
 */
class VolunteerMeetingsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->repRules();

        Cache::forget('settings');
        Cache::forget('rep_rules');

        $users = User::query()->whereHas('memberships', fn ($q) => $q->where('status', 'active'))->take(6)->get();

        if ($users->isEmpty()) {
            $this->command?->warn('مفيش متطوّعين مُسكَّنين — شغّل سيدر العضويّات الأوّل.');

            return;
        }

        $owner = $users->first();
        $entity = Entity::query()->find($owner->memberships()->where('status', 'active')->value('entity_id'));

        $this->grantDemoPermissions($users);

        $ended = $this->endedMeeting($owner, $entity, $users);
        $this->openWindowMeeting($owner, $entity, $users);
        $this->upcomingMeeting($owner, $entity);
        $this->objection($ended, $users);

        $this->command?->info('اجتماعات تجريبيّة: '.Meeting::count());
    }

    // ------------------------------------------------------------------ الإعدادات

    /** إعدادات المجال بنمط «المجال.الميزة.المفتاح» ولكلّها قيمة افتراضيّة */
    public function settings(): void
    {
        $rows = [
            ['meetings.attendance.tier1_hours', 'meetings', 'حدّ الشريحة الأولى لتسجيل الحضور (ساعات)', 'number', '3'],
            ['meetings.attendance.tier2_hours', 'meetings', 'حدّ الشريحة الثانية لتسجيل الحضور (ساعات)', 'number', '12'],
            ['meetings.attendance.default_window_hours', 'meetings', 'نافذة التسجيل الافتراضيّة (ساعات)', 'number', '12'],
            ['meetings.attendance.max_window_hours', 'meetings', 'أقصى نافذة تسجيل (ساعات)', 'number', '48'],
            ['meetings.reminder.hours_before', 'meetings', 'التذكير قبل الموعد (ساعات)', 'number', '2'],
            ['rep.objection.sla_hours', 'rep', 'مهلة ردّ المسؤول على الاعتراض (ساعات)', 'number', '24'],
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
     * قيمة التسجيل بعد انتهاء الشريحتين المنصوصتين (13.4-ن-ب):
     * نافذة أطول يفتحها المسؤول، وقيمتها إعداد مستقلّ افتراضيّه صفر.
     */
    private function repRules(): void
    {
        RepRule::updateOrCreate(['key' => 'meeting.late_registration'], [
            'group' => 'meetings',
            'label_ar' => 'تسجيل الحضور بعد الشريحتين المنصوصتين',
            'value' => 0.0,
            'note' => 'يسري فقط لو فتح المسؤول نافذةً أطول من الشريحة الثانية.',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------ بيانات

    /** الشاشات لا تعمل بلا صلاحيّاتها — والديمو يمنحها صراحةً لمستخدميه */
    private function grantDemoPermissions($users): void
    {
        $keys = [
            'meetings.list' => 'ENTITY',
            'meetings.view' => 'ENTITY',
            'meeting_attendance.create' => 'SELF',
            'meeting_attendance.view' => 'SELF',
            'meeting_minutes.view' => 'ENTITY',
            'meeting_posts.create' => 'ENTITY',
            'rep_transactions.list' => 'SELF',
            'rep_transactions.view' => 'SELF',
            'rep_transactions.export' => 'SELF',
            'vxp_transactions.list' => 'SELF',
            'objections.create' => 'SELF',
            'objections.view' => 'SELF',
        ];

        foreach ($users as $index => $user) {
            $grants = $keys;

            // أوّل مستخدم صاحب الاجتماعات: يملك إنشاءها وإدارتها
            if ($index === 0) {
                $grants['meetings.create'] = 'ENTITY';
                $grants['meetings.manage'] = 'ENTITY';
            }

            foreach ($grants as $key => $scope) {
                $permissionId = Permission::query()->where('key', $key)->value('id');

                if (! $permissionId) {
                    continue;
                }

                DB::table('permission_user')->updateOrInsert(
                    ['user_id' => $user->id, 'permission_id' => $permissionId, 'scope' => $scope],
                    ['effect' => 'allow', 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    private function upcomingMeeting(User $owner, ?Entity $entity): Meeting
    {
        return Meeting::updateOrCreate(
            ['title' => 'اجتماع تخطيط الشهر الجديد'],
            [
                'description' => "أجندة الاجتماع:\n١) مراجعة أهداف الشهر الماضي\n٢) توزيع بنود الحملة\n٣) أسئلة مفتوحة",
                'entity_id' => $entity?->id,
                'audience' => 'entity',
                'owner_id' => $owner->id,
                'scheduled_at' => now()->addDays(3)->setTime(20, 0),
                'external_link' => 'https://meet.example.com/monthly-plan',
                'status' => 'scheduled',
                'attendance_code' => null,
            ],
        );
    }

    /** اجتماع منتهٍ ونافذته مفتوحة — يظهر معه البانر الأحمر المتحرّك */
    private function openWindowMeeting(User $owner, ?Entity $entity, $users): Meeting
    {
        $meeting = Meeting::updateOrCreate(
            ['title' => 'اجتماع المتابعة الأسبوعيّ'],
            [
                'description' => 'متابعة المهامّ المفتوحة ونقاط التعثّر.',
                'entity_id' => $entity?->id,
                'audience' => 'entity',
                'owner_id' => $owner->id,
                'scheduled_at' => now()->subHours(2),
                'external_link' => 'https://meet.example.com/weekly',
                'status' => 'ended',
                'ended_at' => now()->subHour(),
                'attendance_window_hours' => (int) setting('meetings.attendance.default_window_hours', 12),
                'attendance_closes_at' => now()->addHours(11),
                'attendance_code' => 'HC-2026',
                'minutes' => "المحضر:\n- اتراجعت 12 مهمّة، 9 منها مسلَّمة.\n- اتفقنا نقفل بند التصميم الأربع الجاي.",
            ],
        );

        MeetingQuestion::updateOrCreate(
            ['meeting_id' => $meeting->id, 'prompt' => 'إيه البند اللي اتقرّر إغلاقه الأربع الجاي؟'],
            [
                'created_by' => $owner->id,
                'type' => 'choice',
                'options' => ['بند التصميم', 'بند المحتوى', 'بند التوظيف'],
                'correct_answer' => 'بند التصميم',
            ],
        );

        // تسجيل حضور واحد داخل الشريحة الأولى ليظهر أثره في الجداول
        $early = $users->skip(1)->first() ?? $owner;

        if ($early) {
            $attendance = MeetingAttendance::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $early->id],
                [
                    'status' => 'registered',
                    'registered_at' => now()->subMinutes(30),
                    'hours_after_end' => 0,
                    'rep_value' => rep_rule('meeting.within_3h'),
                ],
            );

            $transaction = app(MeetingLedger::class)->rep(
                $early,
                rep_rule('meeting.within_3h'),
                'meeting',
                'تسجيل حضور اجتماع: '.$meeting->title,
                $meeting,
            );

            if ($transaction) {
                $attendance->forceFill(['transaction_id' => $transaction->id])->save();
            }
        }

        MeetingPost::updateOrCreate(
            ['meeting_id' => $meeting->id, 'body' => 'ملخّص القرارات مثبَّت هنا للرجوع السريع.'],
            ['user_id' => $owner->id, 'is_pinned' => true, 'votes' => 4],
        );

        return $meeting;
    }

    /** اجتماع منتهٍ ونافذته اتقفلت — عليه غياب موثَّق قابل للاعتراض */
    private function endedMeeting(User $owner, ?Entity $entity, $users): Meeting
    {
        $meeting = Meeting::updateOrCreate(
            ['title' => 'اجتماع إغلاق حملة رمضان'],
            [
                'description' => 'مراجعة نتائج الحملة والدروس المستفادة.',
                'entity_id' => $entity?->id,
                'audience' => 'entity',
                'owner_id' => $owner->id,
                'scheduled_at' => now()->subDays(6),
                'status' => 'ended',
                'ended_at' => now()->subDays(6)->addHours(2),
                'attendance_window_hours' => 12,
                'attendance_closes_at' => now()->subDays(5),
                'attendance_code' => 'HC-CLOSE',
                'minutes' => "المحضر:\n- الحملة وصلت 118% من المستهدف.\n- الدرس: نبدأ التجهيز قبلها بأسبوعين.",
            ],
        );

        $absent = $users->skip(2)->first() ?? $owner;

        $attendance = MeetingAttendance::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $absent->id],
            ['status' => 'absent', 'rep_value' => rep_rule('meeting.unexcused_absence')],
        );

        if (! $attendance->transaction_id) {
            $transaction = app(MeetingLedger::class)->rep(
                $absent,
                rep_rule('meeting.unexcused_absence'),
                'meeting',
                'غياب بلا اعتذار: '.$meeting->title,
                $meeting,
            );

            if ($transaction) {
                $attendance->forceFill(['transaction_id' => $transaction->id])->save();
            }
        }

        return $meeting;
    }

    /** اعتراض مفتوح على معاملة الغياب — ليظهر تخطيط «قائمة + بانل» ببيانات حقيقيّة */
    private function objection(Meeting $meeting, $users): void
    {
        $attendance = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('status', 'absent')
            ->whereNotNull('transaction_id')
            ->first();

        if (! $attendance || ! $attendance->transaction) {
            return;
        }

        $service = app(ObjectionService::class);
        $user = $attendance->user;

        if (! $user || $service->existingFor($attendance->transaction)) {
            return;
        }

        $service->file(
            $attendance->transaction,
            $user,
            'كنت في مهمّة ميدانيّة وقت الاجتماع وبعتّ اعتذاري على الواتساب — أرفقت لقطة الرسالة.',
        );
    }
}
