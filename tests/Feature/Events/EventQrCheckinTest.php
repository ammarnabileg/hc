<?php

namespace Tests\Feature\Events;

use App\Models\EventRegistration;
use App\Models\Permission;
use App\Models\User;
use App\Models\Transaction;
use App\Services\Events\CheckinQr;
use App\Services\Events\QrMatrix;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * **تشيك-إن QR الديناميكيّ** (13.3 · 12.11 · 24.3).
 *
 * كلّ اختبار هنا يُثبِت **سقوط حارس** لا مروره: رمزٌ مزوَّر · رمزٌ منتهٍ ·
 * رمز شخصٍ يُمسَح على تسجيل شخصٍ آخر · ومسحٌ ثانٍ بعد تسجيل الحضور.
 */
class EventQrCheckinTest extends EventsTestCase
{
    public function test_the_drawn_qr_is_a_real_readable_symbol_not_a_lookalike_grid(): void
    {
        // مصفوفة الإصدار 1 لنصٍّ معلوم — أنماط الكشف الثلاثة والتوقيت في مواضعها
        $matrix = QrMatrix::forText('hi');

        $this->assertCount(21, $matrix, 'الإصدار 1 = 21×21 وحدة');

        // نمط الكشف أعلى اليمين وأعلى اليسار وأسفل اليسار: 7×7 بإطارٍ داكن
        foreach ([[0, 0], [0, 14], [14, 0]] as [$r, $c]) {
            $this->assertTrue($matrix[$r][$c], 'ركن نمط الكشف داكن');
            $this->assertTrue($matrix[$r + 6][$c + 6], 'الركن المقابل داكن');
            $this->assertFalse($matrix[$r + 1][$c + 1], 'الحلقة البيضاء داخله');
            $this->assertTrue($matrix[$r + 3][$c + 3], 'المربّع الداكن في القلب');
        }

        // نمط التوقيت: الصفّ 6 يتناوب داكن/فاتح
        for ($i = 8; $i <= 12; $i++) {
            $this->assertSame($i % 2 === 0, $matrix[6][$i], 'نمط التوقيت متناوب');
        }

        // الوحدة الداكنة الثابتة (المواصفة) — غيابها يجعل الرمز غير مقروء
        $this->assertTrue($matrix[13][8], 'الوحدة الداكنة الثابتة');
    }

    public function test_the_qr_is_dynamic_and_bound_to_its_owner(): void
    {
        $qr = app(CheckinQr::class);
        $user = $this->trainee();
        $other = $this->trainee('سلمى فؤاد');

        $event = $this->makeEvent(['mode' => 'offline', 'location' => 'قاعة التدريب']);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $this->actingAs($other)->post(route('events.register', $event->slug));

        $mine = EventRegistration::where('user_id', $user->id)->firstOrFail();
        $theirs = EventRegistration::where('user_id', $other->id)->firstOrFail();

        // ديناميكيّ: نافذتان مختلفتان ⟵ رمزان مختلفان
        $now = $qr->window();
        $this->assertNotSame($qr->token($mine, $now), $qr->token($mine, $now - 1));

        // مربوط بصاحبه: رمزي لا يساوي رمزه في نفس النافذة
        $this->assertNotSame($qr->token($mine, $now), $qr->token($theirs, $now));

        /*
         | ⭐ **الاختبار الحاسم لـ«يمنع استخدام كود شخص لآخر» (12.11):** آخذ رمزي
         | وأبدّل فيه رقم التسجيل برقم غيري. لو كان التوقيع لا يشمل رقم التسجيل
         | لَمَرّ التبديل — وهو بالضبط ما ينهاه النصّ.
         */
        $parts = explode('-', $qr->token($mine, $now));
        $swapped = $theirs->id.'-'.$parts[1].'-'.$parts[2];

        $this->assertSame(CheckinQr::FORGED, $qr->resolve($swapped)['reason'], 'تبديل صاحب الرمز يُرَدّ');
    }

    public function test_a_full_journey_generate_scan_record_then_a_second_scan_is_refused(): void
    {
        $qr = app(CheckinQr::class);
        $organizer = $this->organizer();
        $user = $this->trainee();

        $event = $this->makeEvent([
            'mode' => 'offline',
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(30),
            'reward_tiers' => json_encode([['hours' => 6, 'xp' => 200, 'tickets' => 1]]),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $registration = EventRegistration::where('event_id', $event->id)->firstOrFail();

        // 1) توليد الرمز: صورة SVG حقيقيّة من مسارٍ يخصّ صاحبه وحده
        $this->actingAs($user)
            ->get(route('events.qr', $event->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertSee('<svg', false);

        // 2) المسح: المنظِّم يفتح الرابط الذي قرأته الكاميرا ⟵ الحضور يُسجَّل
        $this->actingAs($organizer)
            ->get(route('admin.events.scan', ['token' => $qr->token($registration)]))
            ->assertRedirect();

        $registration->refresh();
        $this->assertTrue($registration->attended, 'الحضور اتسجّل بالمسح');
        $this->assertNotNull($registration->attended_at);

        // 3) المكافأة صُرِفت **مرّة واحدة** ومن دفتر الأستاذ لا من خارجه
        $credits = Transaction::where('user_id', $user->id)->where('source', 'event')->get();
        $this->assertSame(2, $credits->count(), 'صفّان: XP وتذكرة — لا أكثر');
        $this->assertEqualsWithDelta(200.0, (float) $credits->firstWhere('amount', 200)?->amount, 0.01);

        // 4) إعادة المسح: تُرَدّ — ولا صفّ ثالث في الدفتر
        $this->actingAs($organizer)
            ->get(route('admin.events.scan', ['token' => $qr->token($registration)]))
            ->assertRedirect();

        $this->assertSame(
            2,
            Transaction::where('user_id', $user->id)->where('source', 'event')->count(),
            'الإعادة لا تصرف مرّة ثانية',
        );
    }

    public function test_the_atomic_guard_stops_a_double_grant_on_a_stale_read(): void
    {
        $user = $this->trainee();

        $event = $this->makeEvent([
            'mode' => 'offline',
            'starts_at' => now()->subMinutes(20),
            'ends_at' => now()->addMinutes(40),
            'reward_tiers' => json_encode([['hours' => 4, 'xp' => 70, 'tickets' => 1]]),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $registration = EventRegistration::where('event_id', $event->id)->firstOrFail();

        $attendance = app(\App\Services\Events\AttendanceService::class);
        $attendance->grant($event, $user, $registration);

        $before = Transaction::where('user_id', $user->id)->where('source', 'event')->count();
        $this->assertSame(2, $before);

        /*
         | ⭐ **السباق نفسه لا شكلُه:** نسخةٌ من الصفّ قُرِئت **قبل** التسجيل
         | فما زالت ترى `attended=false` في الذاكرة والقاعدة تقول `true`. الفرع
         | المبكّر لا يمسكها — والذي يمسكها هو **التحديث الشرطيّ الذرّيّ** وحده.
         */
        $stale = new EventRegistration;
        $stale->exists = true;
        $stale->forceFill($registration->getOriginal())->setAttribute('attended', false);
        $stale->syncOriginal();
        $stale->setAttribute('id', $registration->id);

        $attendance->grant($event, $user, $stale);

        $this->assertSame(
            $before,
            Transaction::where('user_id', $user->id)->where('source', 'event')->count(),
            'الحارس الذرّيّ منع الصرف الثاني',
        );
    }

    public function test_a_forged_token_is_refused_and_records_nothing(): void
    {
        $qr = app(CheckinQr::class);
        $organizer = $this->organizer();
        $user = $this->trainee();

        $event = $this->makeEvent([
            'mode' => 'offline',
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(50),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $registration = EventRegistration::where('event_id', $event->id)->firstOrFail();

        // نفس الشكل، توقيعٌ ملفَّق — والحارس يسقط عليه
        $forged = $registration->id.'-'.$qr->window().'-'.str_repeat('a', 16);

        $this->actingAs($organizer)
            ->get(route('admin.events.scan', ['token' => $forged]))
            ->assertRedirect();

        $this->assertFalse($registration->refresh()->attended, 'المزوَّر لا يسجّل حضورًا');
        $this->assertSame(0, Transaction::where('user_id', $user->id)->where('source', 'event')->count());
    }

    public function test_an_expired_token_is_refused(): void
    {
        $qr = app(CheckinQr::class);
        $organizer = $this->organizer();
        $user = $this->trainee();

        $event = $this->makeEvent([
            'mode' => 'offline',
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(50),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $registration = EventRegistration::where('event_id', $event->id)->firstOrFail();

        // نافذةٌ أقدم من نافذة السماح ⟵ منتهٍ (والتوقيع سليمٌ تمامًا)
        $stale = $qr->token($registration, $qr->window() - $qr->graceWindows() - 3);

        $this->assertSame(CheckinQr::EXPIRED, $qr->resolve($stale)['reason']);

        $this->actingAs($organizer)->get(route('admin.events.scan', ['token' => $stale]))->assertRedirect();

        $this->assertFalse($registration->refresh()->attended, 'المنتهي لا يسجّل حضورًا');
    }

    public function test_the_qr_route_never_hands_one_users_symbol_to_another(): void
    {
        $user = $this->trainee();
        $other = $this->trainee('هدير سامي');

        $event = $this->makeEvent(['mode' => 'offline']);
        $this->actingAs($user)->post(route('events.register', $event->slug));

        // غير المسجَّل لا رمز له أصلًا — فلا سبيل لاستخراج رمز غيره
        $this->actingAs($other)->get(route('events.qr', $event->slug))->assertNotFound();
        $this->actingAs($other)->get(route('events.ticket', $event->slug))->assertNotFound();

        // وصاحب التسجيل يأخذ رمزه هو
        $this->actingAs($user)->get(route('events.qr', $event->slug))->assertOk();
    }

    public function test_the_qr_is_hidden_for_online_only_events(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent(['mode' => 'online']);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        // 13.3 يعلّق الـQR بالأوفلاين (والهجين يرثه) — والأونلاين كود OTP وحده
        $this->actingAs($user)->get(route('events.qr', $event->slug))->assertNotFound();
    }

    /** منظِّمٌ يملك صلاحيّة التشيك-إن ودخول اللوحة — بالصلاحيّة لا بدورٍ جاهز */
    private function organizer(): User
    {
        $user = $this->trainee('منظّم الفعاليّة');

        foreach (['event_attendance.create', 'event_registrations.list', 'event_registrations.export', 'events.list'] as $key) {
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
}
