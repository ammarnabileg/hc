<?php

namespace Tests\Feature\Events;

use App\Models\AdAudience;
use App\Models\AppNotification;
use App\Models\EventNotice;
use App\Models\EventRegistration;
use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AudienceSegments;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * 🖥️ **المسجّلون والحضور** — الشاشة الجامعة (12.11 · 24.3 · خريطة 12.0).
 *
 * والحارس المُثبَت سقوطه: **الصلاحيّة**. مَن لا يملك `event_registrations.list`
 * لا يفتحها ولا يراها في السايد بار — يُخفى ولا يُعطَّل (2.15-أ-7).
 */
class EventRegistrationsScreenTest extends EventsTestCase
{
    public function test_the_collective_screen_opens_with_its_prescribed_parts(): void
    {
        $admin = $this->eventsAdmin();
        $user = $this->trainee();

        $event = $this->makeEvent(['title_ar' => 'ورشة الخطابة', 'mode' => 'offline']);
        $this->actingAs($user)->post(route('events.register', $event->slug));

        $response = $this->actingAs($admin)
            ->get(route('admin.events.registrations.index'))
            ->assertOk();

        // الهيدر + العدّادات الثلاثة المنصوصة (12.11)
        $response->assertSee('المسجّلون والحضور');
        $response->assertSee('مسجّل');
        $response->assertSee('حاضر');
        $response->assertSee('غائب');

        // الفلاتر المنصوصة (24.3)
        $response->assertSee('حالة الحضور');
        $response->assertSee('نمط الحضور');
        $response->assertSee('وقت التشيك-إن');

        // العرض: الصفّ فيه المستخدم وفعاليّته
        $response->assertSee($user->name);
        $response->assertSee('ورشة الخطابة');

        // الأفعال: تصدير CSV · مسح QR
        $response->assertSee('تصدير CSV');
        $response->assertSee('مسح QR للتشيك-إن');
    }

    public function test_the_empty_state_is_one_line_and_one_button(): void
    {
        $admin = $this->eventsAdmin();

        $this->actingAs($admin)
            ->get(route('admin.events.registrations.index'))
            ->assertOk()
            ->assertSee('لا مسجّلين بعد — شارك رابط الفعاليّة.');
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل «لا مسجّلين بعد». */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $admin = $this->eventsAdmin();
        $user = $this->trainee();
        $event = $this->makeEvent(['title_ar' => 'ورشة الخطابة', 'mode' => 'offline']);
        $this->actingAs($user)->post(route('events.register', $event->slug));

        $response = $this->actingAs($admin)
            ->get(route('admin.events.registrations.index', ['q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee('لا مسجّلين بعد — شارك رابط الفعاليّة.');
    }

    public function test_the_screen_is_hidden_from_whoever_lacks_the_permission(): void
    {
        $outsider = $this->trainee('بلا صلاحيّة');

        // بلا صلاحيّة = الباب مقفول فعلًا لا معطَّلًا شكلًا (12.2.1 · 2.15-أ-7)
        $this->actingAs($outsider)
            ->get(route('admin.events.registrations.index'))
            ->assertForbidden();

        /*
         | ⭐ **والحالة الحاسمة:** أدمنٌ يفتح اللوحة فعلًا (يملك `events.list`)
         | لكنّه **لا يملك `event_registrations.list`**. باب اللوحة يمرّره،
         | فالذي يجب أن يردّه هو **حارس الصلاحيّة على المسار وحده**.
         */
        $partial = $this->partialAdmin();

        $this->actingAs($partial)->get(route('admin.events.index'))->assertOk();
        $this->actingAs($partial)->get(route('admin.events.registrations.index'))->assertForbidden();

        // ولا يراه في السايد بار أصلًا — يُخفى لا يُعطَّل (2.15-أ-7)
        $this->actingAs($partial)
            ->get(route('admin.events.index'))
            ->assertDontSee(route('admin.events.registrations.index'), false);
    }

    public function test_the_sidebar_entry_exists_and_points_at_the_screen(): void
    {
        $admin = $this->eventsAdmin();

        // البند يظهر لمن يملكه…
        $this->actingAs($admin)
            ->get(route('admin.events.registrations.index'))
            ->assertOk()
            ->assertSee(route('admin.events.registrations.index'), false)
            ->assertSee('المسجّلون والحضور');

        // …ولا يظهر لمن لا يملكه: المتدرّب لا يرى الرابط في أيّ صفحة يفتحها
        $trainee = $this->trainee('متدرّب عاديّ');

        $this->actingAs($trainee)
            ->get(route('events.index'))
            ->assertOk()
            ->assertDontSee(route('admin.events.registrations.index'), false);
    }

    public function test_the_filters_actually_narrow_the_rows(): void
    {
        $admin = $this->eventsAdmin();
        $present = $this->trainee('حاضرة');
        $absent = $this->trainee('غائبة');

        $event = $this->makeEvent([
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(30),
            'attendance_code' => '424242',
        ]);

        $this->actingAs($present)->post(route('events.register', $event->slug));
        $this->actingAs($absent)->post(route('events.register', $event->slug));
        $this->actingAs($present)->post(route('events.checkin', $event->slug), ['code' => '424242']);

        $this->actingAs($admin)
            ->get(route('admin.events.registrations.index', ['attended' => 'yes']))
            ->assertOk()
            ->assertSee('حاضرة')
            ->assertDontSee('غائبة');

        $this->actingAs($admin)
            ->get(route('admin.events.registrations.index', ['attended' => 'no']))
            ->assertOk()
            ->assertSee('غائبة')
            ->assertDontSee('حاضرة');
    }

    public function test_csv_export_follows_the_screen_filters(): void
    {
        $admin = $this->eventsAdmin();
        $user = $this->trainee('سلمى للتصدير');

        $event = $this->makeEvent(['title_ar' => 'لقاء التصدير']);
        $this->actingAs($user)->post(route('events.register', $event->slug));

        $response = $this->actingAs($admin)
            ->get(route('admin.events.registrations.export'))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('لقاء التصدير', $csv);
        $this->assertStringContainsString($user->code, $csv);
        $this->assertStringContainsString('غاب', $csv);

        // ⭐ وبفلترٍ: التصدير يتبع الشاشة لا الجدول كلّه
        $other = $this->trainee('برّه الفلتر');
        $another = $this->makeEvent(['title_ar' => 'لقاء تاني']);
        $this->actingAs($other)->post(route('events.register', $another->slug));

        $filtered = $this->actingAs($admin)
            ->get(route('admin.events.registrations.export', ['event_id' => $event->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($user->code, $filtered);
        $this->assertStringNotContainsString($other->code, $filtered, 'المصدَّر يتبع الفلتر');
    }

    public function test_notifying_registrants_reaches_them_now_and_can_be_scheduled(): void
    {
        $admin = $this->eventsAdmin();
        $user = $this->trainee();

        $event = $this->makeEvent();
        $this->actingAs($user)->post(route('events.register', $event->slug));

        // الآن: يُبَثّ في نفس الطلب
        $this->actingAs($admin)
            ->post(route('admin.events.registrations.notify', $event), [
                'body' => 'اترفع رابط التسجيل.',
                'channel' => 'bell',
            ])
            ->assertRedirect();

        $sent = EventNotice::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('sent', $sent->status);
        $this->assertSame(1, $sent->recipients);

        // مجدول: يبقى معلَّقًا **بمستقرٍّ ومُلتقِط** لا في الهواء
        $this->actingAs($admin)
            ->post(route('admin.events.registrations.notify', $event), [
                'body' => 'تذكير أخير.',
                'channel' => 'bell',
                'send_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $scheduled = EventNotice::where('event_id', $event->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $scheduled->status);

        // المُلتقِط يلتقطه حين يحين — ومرّةً واحدة
        $this->travel(3)->hours();
        $this->artisan('events:remind')->assertSuccessful();

        $this->assertSame('sent', $scheduled->refresh()->status);

        $bell = AppNotification::where('user_id', $user->id)->count();

        $this->artisan('events:remind')->assertSuccessful();

        $this->assertSame(1, $scheduled->refresh()->recipients, 'ما اتبعتش مرّتين');
        $this->assertSame(
            $bell,
            AppNotification::where('user_id', $user->id)->count(),
            'ولا إشعار مكرّر في الجرس',
        );
    }

    /** ⭐ 12.11: دعوة أعضاء شريحةٍ محفوظة لفعاليّة تصل فعليًّا لعضوٍ من الشريحة */
    public function test_inviting_a_segment_actually_notifies_its_members(): void
    {
        $admin = $this->eventsAdmin();
        $member = $this->trainee('عضو الشريحة');

        $event = $this->makeEvent(['title_ar' => 'حفل التخرّج']);
        $segment = $this->staticSegment('خريجو الدفعة', [$member->id]);

        $this->actingAs($admin)
            ->post(route('admin.events.registrations.invite-segment', $event), [
                'segment_id' => $segment->id,
                'body' => 'تعالَ احتفل معانا.',
                'channel' => 'bell',
            ])
            ->assertRedirect();

        $notice = EventNotice::where('event_id', $event->id)->firstOrFail();
        $this->assertSame($segment->id, $notice->segment_id);
        $this->assertSame('sent', $notice->status);
        $this->assertSame(1, $notice->recipients);

        $this->assertTrue(
            AppNotification::where('user_id', $member->id)->where('title', 'حفل التخرّج')->exists(),
            'عضو الشريحة لازم يوصله الجرس فعليًّا',
        );
    }

    /** ⭐ 12.11: عضو الشريحة المسجَّل بالفعل في نفس الفعاليّة يُستبعَد فلا يصله ازدواج */
    public function test_a_segment_member_already_registered_for_the_event_is_excluded(): void
    {
        $admin = $this->eventsAdmin();
        $registered = $this->trainee('مسجَّلة بالفعل');

        $event = $this->makeEvent();
        $this->actingAs($registered)->post(route('events.register', $event->slug));

        $before = AppNotification::where('user_id', $registered->id)->count();

        $segment = $this->staticSegment('كلّ المتدرّبين', [$registered->id]);

        $this->actingAs($admin)
            ->post(route('admin.events.registrations.invite-segment', $event), [
                'segment_id' => $segment->id,
                'body' => 'دعوة عامّة.',
                'channel' => 'bell',
            ])
            ->assertRedirect();

        $notice = EventNotice::where('event_id', $event->id)->where('segment_id', $segment->id)->firstOrFail();
        $this->assertSame('sent', $notice->status, 'يُقفَل مُرسَلًا حتّى بصفر مستلِم — لا يبقى معلَّقًا');
        $this->assertSame(0, $notice->recipients, 'المسجَّل مسبقًا مُستبعَدٌ من دعوة الشريحة');

        $this->assertSame(
            $before,
            AppNotification::where('user_id', $registered->id)->count(),
            'ولا إشعار مزدوج وصله عبر مسار الشريحة',
        );
    }

    /** ⭐ 12.11: الزرّ والمسار محجوبان عمّن لا يملك events.edit (2.15-أ-7) */
    public function test_invite_segment_is_hidden_from_whoever_lacks_events_edit(): void
    {
        $viewer = $this->viewOnlyAdmin();
        $event = $this->makeEvent(['title_ar' => 'فعاليّة محجوبة']);
        $segment = $this->staticSegment('شريحة', []);

        $this->actingAs($viewer)
            ->get(route('admin.events.registrations.index', ['event_id' => $event->id]))
            ->assertOk()
            ->assertDontSee('دعوة شريحة');

        $this->actingAs($viewer)
            ->post(route('admin.events.registrations.invite-segment', $event), [
                'segment_id' => $segment->id,
                'body' => 'دعوة.',
                'channel' => 'bell',
            ])
            ->assertForbidden();
    }

    /** ⭐ 12.13: شريحةٌ مؤرشفة أو من kind مختلف لا تظهر في المنتقي ولا تُقبَل مباشرةً */
    public function test_archived_or_wrong_kind_segments_are_rejected(): void
    {
        $admin = $this->eventsAdmin();
        $event = $this->makeEvent();

        $archived = $this->staticSegment('شريحة مؤرشفة', []);
        $archived->update(['archived_at' => now()]);

        $adSegment = AdAudience::create([
            'name' => 'جمهور إعلان',
            'kind' => 'retargeting',
            'segment_type' => AudienceSegments::TYPE_STATIC,
            'rule' => [],
            'is_active' => true,
        ]);

        // المنتقي لا يعرض أيًّا منهما
        $this->actingAs($admin)
            ->get(route('admin.events.registrations.index', ['event_id' => $event->id]))
            ->assertOk()
            ->assertDontSee('شريحة مؤرشفة')
            ->assertDontSee('جمهور إعلان');

        // والإرسال المباشر بمعرّفها مرفوضٌ من الحارس لا فقط من الواجهة
        $this->actingAs($admin)
            ->post(route('admin.events.registrations.invite-segment', $event), [
                'segment_id' => $archived->id,
                'body' => 'دعوة.',
                'channel' => 'bell',
            ])
            ->assertSessionHasErrors('segment_id');

        $this->actingAs($admin)
            ->post(route('admin.events.registrations.invite-segment', $event), [
                'segment_id' => $adSegment->id,
                'body' => 'دعوة.',
                'channel' => 'bell',
            ])
            ->assertSessionHasErrors('segment_id');

        $this->assertSame(0, EventNotice::where('event_id', $event->id)->count());
    }

    public function test_manual_check_in_still_goes_through_the_ledger(): void
    {
        $admin = $this->eventsAdmin();
        $user = $this->trainee();

        $event = $this->makeEvent([
            'starts_at' => now()->subMinutes(20),
            'ends_at' => now()->addMinutes(40),
            'attendance_code' => '778899',
            'reward_tiers' => json_encode([['hours' => 5, 'xp' => 90, 'tickets' => 1]]),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        $this->actingAs($admin)
            ->post(route('admin.events.check-in', $event), [
                'code' => $user->code,
                'attendance_code' => '778899',
            ])
            ->assertRedirect();

        $this->assertTrue(EventRegistration::where('event_id', $event->id)->value('attended'));
    }

    /** ⭐ 24.2: شاشة مسجّلي فعاليّةٍ بعينها — بحثٌ بلا نتائج يقول كده صراحةً. */
    public function test_per_event_registrations_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $admin = $this->eventsAdmin();
        $user = $this->trainee();
        $event = $this->makeEvent(['title_ar' => 'ورشة الخطابة', 'mode' => 'offline']);
        $this->actingAs($user)->post(route('events.register', $event->slug));

        $response = $this->actingAs($admin)
            ->get(route('admin.events.registrations', [$event, 'q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.events.registrations.la_msjlyn_bad_shark_rabt_alfaalya', 'لا مسجّلين بعد — شارك رابط الفعاليّة.'),
            false,
        );
    }

    /** وفعاليّةٌ فارغةٌ فعليًّا (بلا مسجّلين ولا فلتر) تفضل تعرض رسالة البداية الأصليّة. */
    public function test_per_event_registrations_actually_empty_without_filters_keeps_the_original_start_message(): void
    {
        $admin = $this->eventsAdmin();
        $event = $this->makeEvent(['title_ar' => 'ورشة بلا مسجّلين']);

        $response = $this->actingAs($admin)
            ->get(route('admin.events.registrations', $event))
            ->assertOk();

        $response->assertSee(
            setting('admin.events.registrations.la_msjlyn_bad_shark_rabt_alfaalya', 'لا مسجّلين بعد — شارك رابط الفعاليّة.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    /** أدمن فعاليّات بصلاحيّاته المنصوصة وحدها — لا دورًا مفتوحًا */
    private function eventsAdmin(): User
    {
        $user = $this->trainee('أدمن الفعاليّات');

        $keys = [
            'events.list', 'events.edit', 'event_registrations.list',
            'event_registrations.export', 'event_attendance.create', 'event_attendance.edit',
        ];

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->first();

            if (! $permission) {
                continue;
            }

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user->fresh();
    }

    /**
     * شريحةٌ ثابتة بأعضائها مباشرةً في `audience_segment_members` — بلا مرور
     * بحلّ الشرط أو نطاق مالكٍ، فالاختبار يقيس مصدر المستلمين لا بناء الشريحة.
     *
     * @param  list<int>  $userIds
     */
    private function staticSegment(string $name, array $userIds): AdAudience
    {
        $segment = AdAudience::create([
            'name' => $name,
            'kind' => AudienceSegments::KIND,
            'segment_type' => AudienceSegments::TYPE_STATIC,
            'rule' => [],
            'is_active' => true,
            'size' => count($userIds),
        ]);

        foreach ($userIds as $userId) {
            DB::table('audience_segment_members')->insert([
                'ad_audience_id' => $segment->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $segment;
    }

    /** أدمنٌ يرى شاشة المسجّلين لكن **بلا events.edit** — لاختبار حارس دعوة الشريحة وحده */
    private function viewOnlyAdmin(): User
    {
        $user = $this->trainee('أدمن بلا events.edit');

        $keys = ['events.list', 'event_registrations.list'];

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->first();

            if (! $permission) {
                continue;
            }

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user->fresh();
    }

    /** أدمنٌ يفتح اللوحة ولا يملك صلاحيّة شاشة المسجّلين — لاختبار الحارس وحده */
    private function partialAdmin(): User
    {
        $user = $this->trainee('أدمن بلا مسجّلين');
        $permission = Permission::where('key', 'events.list')->firstOrFail();

        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id,
            'user_id' => $user->id,
            'membership_id' => null,
            'scope' => 'ALL',
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);

        return $user->fresh();
    }
}
