<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Escalation;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Org\AbsenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * ج-6 — العزل يفرّق بين العابر والدائم (الدستور 23 — القسم 5).
 *
 * الحكم النصّيّ الذي يحرس الطرفين معًا:
 *  - «أيّ حالة محتاجة قرار ⟵ صاحب القرار الأوّل = المراجِع… **عنده 24 ساعة**.
 *     فاتت ⟵ الحالة تطلع للأبلاين الأعلى» (23-5) — فحالةٌ صحيحة ماتت بخطأ
 *     عابر لم تعد تطلع لأحد، وهذا **إلغاءٌ صامت** لقاعدة الـ24 نفسها.
 *  - وفي الاتّجاه الآخر: المحرّك يعمل «كلّ خمس دقائق على المنصّة بأسرها»، فحالةٌ
 *     عالقة تُعيد إسقاط الدورة إلى الأبد تُوقف **التسويات التسع** لكلّ متطوّع.
 *
 * فالحلّ عدّاد محاولات بسقفٍ من `setting()`: العابر ينجح في محاولةٍ تالية،
 * والدائم يُعزَل **بعد استنفاد السقف** — لا فورًا ولا إلى الأبد — بإشعارٍ وسجلّ.
 */
class EscalationRetryTest extends FlowTestCase
{
    protected function tearDown(): void
    {
        FlakyAbsenceService::$remainingFailures = 0;
        FlakyAbsenceService::$alwaysFails = false;
        FlakyAbsenceService::$calls = 0;

        parent::tearDown();
    }

    /** عدد المحاولات قبل العزل — إعداد لا رقم محروق (2.13) */
    private function maxAttempts(): int
    {
        return (int) setting('workflow.escalation.max_attempts', 3);
    }

    /**
     * ⭐ العطب المقيس: تسع حالات صحيحة فاتت نوافذها، ثلاثٌ منها تصطدم بخطأ
     * **عابر** (`database is locked`) — فتموت للأبد بلا أيّ محاولة تالية.
     *
     * وبعد الإصلاح: الستّ تصعد، والثلاث **تبقى مفتوحة** بعدّاد محاولة واحدة،
     * ثمّ **تنجح في الدورة التالية** — لأنّ العابر ليس عطبًا في الحالة.
     */
    public function test_a_transient_error_does_not_kill_a_healthy_case(): void
    {
        $rows = $this->openOverdueCases(9);

        $this->bindFlakyAbsence(failures: 3);

        $first = app(EscalationEngine::class)->run();

        $this->assertSame(6, $first['escalated'], 'الستّ السليمة تصعد كالمعتاد.');
        $this->assertSame(0, $first['failed'], 'ولا حالة صحيحة تُعزَل بخطأ عابر.');
        $this->assertSame(3, $first['retried'] ?? -1, 'الثلاث تُؤجَّل لمحاولةٍ تالية بعدّادٍ مرئيّ.');

        $stuck = Escalation::query()->whereIn('id', $rows->pluck('id'))->where('level', 1)->get();

        $this->assertCount(3, $stuck);
        $stuck->each(function (Escalation $row) {
            $this->assertSame('open', $row->status, 'الحالة الصحيحة تبقى في الطابور — لا تموت صامتةً.');
            $this->assertSame(1, (int) $row->attempts, 'وعدّاد محاولاتها حقيقيّ ومكتوب.');
        });

        // الدورة التالية: العطب العابر زال ⟵ الثلاث تصعد فعلًا
        $second = app(EscalationEngine::class)->run();

        $this->assertSame(3, $second['escalated']);
        $this->assertSame(0, $second['failed']);

        Escalation::query()->whereIn('id', $rows->pluck('id'))->get()->each(function (Escalation $row) {
            $this->assertSame('open', $row->status);
            $this->assertSame(2, (int) $row->level, 'كلّ التسع طلعت للأبلاين الأعلى كما ينصّ 23-5.');
            $this->assertSame(0, (int) $row->attempts, 'والنجاح يصفّر العدّاد فلا تتراكم محاولات قديمة.');
        });
    }

    /**
     * ⭐ والوجه الآخر: حالة معطوبة **حقًّا** لا تعود تُسقِط المحرّك ولا تدور
     * إلى الأبد — تُعزَل **بعد استنفاد السقف** بإشعارٍ يجعل العزل مرئيًّا.
     */
    public function test_a_permanently_broken_case_is_quarantined_after_the_cap(): void
    {
        $row = $this->openOverdueCases(1)->first();

        $this->bindFlakyAbsence(always: true);

        for ($attempt = 1; $attempt < $this->maxAttempts(); $attempt++) {
            $result = app(EscalationEngine::class)->run();

            $this->assertSame(0, $result['failed'], "المحاولة {$attempt} لا تعزل — السقف لم يُستنفَد بعد.");
            $this->assertSame('open', $row->refresh()->status);
            $this->assertSame($attempt, (int) $row->attempts);
        }

        // المحاولة الأخيرة تستنفد السقف ⟵ العزل
        $last = app(EscalationEngine::class)->run();

        $this->assertSame(1, $last['failed']);
        $this->assertSame('failed', $row->refresh()->status, 'العالقة تخرج من الطابور فلا تُسقِط الدورة كلّ خمس دقائق.');
        $this->assertSame($this->maxAttempts(), (int) $row->attempts);
        $this->assertStringContainsString((string) $this->maxAttempts(), (string) $row->decision_note);

        // والعزل مرئيّ لا صامت: إشعارٌ لصاحب النافذة ولطالب الحالة
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->reviewer->id,
            'category' => 'escalation',
            'requires_action' => true,
            'title' => 'حالة اتوقفت وعايزة مراجعة يدويّة',
        ]);

        // وما عاد المحرّك يلمسها بعد العزل
        $after = app(EscalationEngine::class)->run();
        $this->assertSame(0, $after['failed'] + $after['escalated'] + $after['settled']);
    }

    /**
     * ⭐ والسقف **رقمٌ يملكه المالك** لا رقمٌ محروق (2.13): يغيّره من اللوحة
     * فيتغيّر سلوك المحرّك فعلًا — لا يبقى ثلاثة مهما كتب.
     */
    public function test_the_attempt_cap_follows_the_owner_setting(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'workflow.escalation.max_attempts'],
            ['group' => 'workflow', 'label_ar' => 'محاولات المعالجة', 'type' => 'number', 'value' => '5'],
        );
        Cache::forget('settings');

        $this->assertSame(5, $this->maxAttempts());

        $row = $this->openOverdueCases(1)->first();
        $this->bindFlakyAbsence(always: true);

        // ثلاث دورات — وهي سقف الافتراضيّ — ولا عزل، لأنّ الحاكم هو إعداد المالك
        for ($i = 0; $i < 3; $i++) {
            app(EscalationEngine::class)->run();
        }

        $this->assertSame('open', $row->refresh()->status, 'الرقم المحروق (3) لا يحكم — إعداد المالك هو الذي يحكم.');
        $this->assertSame(3, (int) $row->attempts);

        app(EscalationEngine::class)->run();
        app(EscalationEngine::class)->run();

        $this->assertSame('failed', $row->refresh()->status, 'وعند الخامسة — سقف المالك — يقع العزل.');
        $this->assertSame(5, (int) $row->attempts);
    }

    /** والنوع المجهول عطبٌ **دائم بطبيعته** — يُعزَل فورًا بلا انتظار السقف */
    public function test_an_unknown_case_type_is_quarantined_immediately(): void
    {
        $broken = Escalation::create([
            'case_type' => 'objection', // نوع لا يعرفه CaseCatalog
            'subject_type' => (new Task)->getMorphClass(),
            'subject_id' => $this->makeTask()->id,
            'requested_by' => $this->owner->id,
            'current_handler_id' => $this->reviewer->id,
            'level' => 1,
            'window_due_at' => now()->subHour(),
            'status' => 'open',
        ]);

        $result = app(EscalationEngine::class)->run();

        $this->assertSame(1, $result['failed']);
        $this->assertSame('failed', $broken->refresh()->status, 'ما لا يُصلحه تكرارُ المحاولة لا يُكرَّر.');
    }

    /** والدليل التشغيليّ: الأمر المجدول نفسه يعرض عدد المؤجَّلات والمعزولات */
    public function test_the_scheduled_command_reports_retries_and_quarantines(): void
    {
        $this->openOverdueCases(2);
        $this->bindFlakyAbsence(failures: 1);

        Artisan::call('escalations:run');
        $output = Artisan::output();

        $this->assertStringContainsString('حالات مؤجَّلة لمحاولة تالية', $output);
        $this->assertStringContainsString('حالات معزولة تحتاج مراجعة', $output);
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * حالات صحيحة تمامًا فاتت نوافذها — لا شيء فيها معطوب.
     *
     * @return Collection<int, Escalation>
     */
    private function openOverdueCases(int $count): Collection
    {
        return collect(range(1, $count))->map(function (int $i) {
            $task = $this->makeTask();

            $row = app(EscalationEngine::class)->open(CaseCatalog::EXTENSION, $task, $this->owner);
            $row->forceFill(['window_due_at' => now()->subMinutes(60 + $i)])->save();

            return $row;
        });
    }

    private function bindFlakyAbsence(int $failures = 0, bool $always = false): void
    {
        FlakyAbsenceService::$remainingFailures = $failures;
        FlakyAbsenceService::$alwaysFails = $always;
        FlakyAbsenceService::$calls = 0;

        $this->app->singleton(AbsenceService::class, fn () => new FlakyAbsenceService);
    }
}

/**
 * محاكاة الخطأ **العابر** الذي قتل ثلاث حالات صحيحة: `database is locked`.
 * لا يعطب الحالة نفسها — يقع في لحظةٍ ثمّ يزول.
 */
class FlakyAbsenceService extends AbsenceService
{
    public static int $remainingFailures = 0;

    public static bool $alwaysFails = false;

    public static int $calls = 0;

    public function isAbsent(?User $user, ?int $entityId = null): bool
    {
        self::$calls++;

        if (self::$alwaysFails || self::$remainingFailures > 0) {
            self::$remainingFailures--;

            throw new RuntimeException('database is locked');
        }

        return parent::isAbsent($user, $entityId);
    }
}
