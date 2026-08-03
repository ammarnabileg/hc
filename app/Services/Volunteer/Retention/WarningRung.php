<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Membership;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Notifications\Notifier;
use App\Services\Volunteer\Escalation\HandlerChain;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Profile\NotesPanel;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * ⭐ **الدرجة الأولى من سلّم العتبات: الإنذار عند −8** (23-0.2 — البند 1).
 *
 * ================== النصّ الحاكم حرفيًّا ==================
 * «**عند تخطّي −8 (الإنذار):** يظهر المؤشّر الأحمر · إشعار **لكلّ أبلايناته
 * النشطين** عبر عضويّاته · التزام التواصل الموثَّق خلال **48 ساعة** يقع على
 * **أبلاين العضويّة التي وقعت فيها المعاملة الكاسرة لحاجز −8** · ويُسجَّل
 * التواصل في **الملاحظات الإداريّة** بالبروفايل.» (23-0.2 — إجراء عتبات الهبوط، 1)
 *
 * فالدرجة **أربعة أشياء** لا واحد. والمبنيّ قبل هذا الملفّ كان **المؤشّر الأحمر
 * وحده** (`RepService::isRedIndicator()` ⟵ `RepBadge`) — أي **ربعُ الدرجة**:
 * لونٌ على الشاشة بلا أن يعلم به أحدٌ ممّن يقدر أن يفعل شيئًا.
 *
 * ================== ثلاثة أسئلة يحسمها النصّ ==================
 * **مَن يُشعَر؟** «كلّ أبلايناته النشطين **عبر عضويّاته**» — والجملة تشرح نفسها:
 * الجمع جاء من **تعدّد العضويّات** (23-0.2-عضويّات: قسم + محافظة + ملفّ)، فلكلّ
 * عضويّةٍ أبلاينُها المباشر. فهم **أبلاينو عضويّاته** لا سلسلةُ السلّم كلّها
 * إلى القمّة — وإلّا لصار إنذارُ كوردنيتورٍ بريدًا لخمسة مستويات.
 *
 * **على مَن يقع الالتزام؟** على **واحدٍ بعينه**: «أبلاين العضويّة التي وقعت فيها
 * **المعاملة الكاسرة**». ولذلك تُختَم المعاملة الكاسرة وكيانها في الصفّ — فبغيرها
 * لا يُعرَف صاحب الواجب أصلًا، ويصير الالتزام واجبًا على الجميع أي على لا أحد.
 *
 * **ولماذا يُكتَب التواصل ولا يُكتفى بوقوعه؟** لأنّ النصّ يستدعيه بعد درجتين في
 * ملفّ لجنة التحقيق (23-0.2-4-لجنة-4): «**توثيق تواصل الإنذار عند −8 (حدث أم لا
 * — وهو ما يحاسب الأبلاين أيضًا)**». فالصفّ هنا هو ما تقرأه اللجنة هناك، و**عدمُ
 * التواصل واقعةٌ مسجَّلة** كوقوعه سواءً بسواء (`breached_at`).
 *
 * ⚠️ **وحدود هذا الملفّ معلَنة:** لا يمسّ المؤشّر الأحمر (مبنيٌّ ويعمل)، ولا
 * يخصم شيئًا — النصّ لم يرتّب على −8 خصمًا، والخصم المزدوج ممنوع (23-6).
 */
class WarningRung
{
    public const TABLE = 'volunteer_rep_warnings';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly RepService $rep,
        private readonly HandlerChain $chain,
        private readonly NotesPanel $notes,
    ) {}

    // ------------------------------------------------------------------ القراءة

    /** عتبة الإنذار (−8) — من جدول Rep الموحّد لا رقمًا محروقًا (2.13 · 13.4-ن) */
    public function threshold(): float
    {
        return rep_rule('limit.red_indicator', -8);
    }

    /** مهلة التواصل الموثَّق (48 ساعة) — إعداد لا رقم (2.13) */
    public function contactHours(): int
    {
        return max(1, (int) setting('volunteer.rep_warning.contact_hours', 48));
    }

    /**
     * إنذار الدورة الجارية — والدورة تنتهي **بالتصفير الشهريّ** (13.4-ن-ز).
     *
     * ⚠️ **اجتهادٌ مُعلَن:** النصّ لم يقل صراحةً «إنذارٌ واحد لكلّ شهر». والقرينة
     * أنّ الدرجة معلَّقة بـ«**تخطّي** −8» — وهو حدثٌ يقع مرّةً في هبوطةٍ واحدة —
     * وأنّ الرقم يعود صفرًا كلّ شهر فتبدأ هبوطةٌ جديدة. ولولا هذا القيد لَصار
     * كلّ خصمٍ تحت −8 إنذارًا جديدًا: بريدٌ يوميّ لأبلايناته وعدّاد 48 ساعة
     * يُولَد من جديد كلّ مرّة — فيتحوّل الإنذار إلى ضجيجٍ لا يُقرَأ، وهو نقيض
     * غرضه. والدورة إعدادٌ ضمنيّ: موعد التصفير نفسه (`rep.reset.*`).
     */
    public function openWarningOf(User $user): ?object
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('cycle_ends_at')->orWhere('cycle_ends_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /**
     * ⭐ **«كلّ أبلايناته النشطين عبر عضويّاته»** — أبلاين كلّ عضويّة نشطة له،
     * بلا تكرار. ويمرّ كلٌّ منهم على `HandlerChain` فيُستبدَل الغائبُ ببديله
     * (23-6): «كلّ نوافذ القرار الواردة إليه **تُوجَّه للبديل مباشرةً**» —
     * والإنذارُ نافذةُ قرارٍ بمهلة، فإرساله لمكتب غائبٍ إسقاطٌ للالتزام.
     *
     * @return Collection<int,User>
     */
    public function activeUplinesOf(User $user): Collection
    {
        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $found = collect();

        foreach ($memberships as $membership) {
            $upline = $this->chain->firstHandlerFor($user, (int) $membership->entity_id);

            if ($upline && (int) $upline->id !== (int) $user->id) {
                $found->put((int) $upline->id, $upline);
            }
        }

        return $found->values();
    }

    /** هل فات التزام التواصل بلا توثيق؟ — سؤال اللجنة الأوّل (23-0.2-4-لجنة-4) */
    public function isBreached(object $warning): bool
    {
        return $warning->contacted_at === null
            && $warning->contact_due_at !== null
            && Carbon::parse($warning->contact_due_at)->isPast();
    }

    // ------------------------------------------------------------------ التنفيذ

    /**
     * «عند **تخطّي** −8» — تنفيذ الدرجة إن بلغ الرقم الظاهر العتبة ولم تقع بعدُ
     * في هذه الدورة. رخيصةٌ ومحروسة: تقرأ الرصيد فإن لم يبلغ العتبة خرجت فورًا.
     *
     * @param  Transaction|null  $breaking  المعاملة الكاسرة — يمرّرها الجسر الذي كتبها
     */
    public function enforce(?User $user, ?Transaction $breaking = null): ?array
    {
        if (! $user || ! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $displayed = round($this->ledger->balance($user, LedgerService::REP), 2);

        if ($displayed > $this->threshold()) {
            return null;
        }

        if ($this->openWarningOf($user) !== null) {
            return null;
        }

        return $this->apply($user, $breaking, $displayed);
    }

    /**
     * الدرجة كاملةً كما ينصّ 23-0.2-1.
     *
     * @return array{warning_id:int,rep:float,uplines:int,responsible_id:?int,contact_due_at:string}
     */
    public function apply(User $user, ?Transaction $breaking = null, ?float $displayed = null): array
    {
        $displayed ??= round($this->ledger->balance($user, LedgerService::REP), 2);

        $entityId = $breaking?->entity_id ? (int) $breaking->entity_id : null;

        // ⭐ صاحب الالتزام: «أبلاين العضويّة التي وقعت فيها المعاملة الكاسرة».
        // ولو جاءت المعاملة بلا كيان (حركة شخصيّة كخصم الخمول) فالعضويّة
        // الأساسيّة هي المرجع — وهي ما يعود به `HandlerChain` بلا كيان.
        $responsible = $this->chain->firstHandlerFor($user, $entityId);

        $uplines = $this->activeUplinesOf($user);

        /*
         | ⭐⭐ الصفّ يُكتَب **قبل** الإشعارات لا بعدها — نفس درس صفّ البتر:
         | لو أسقط الإشعارُ العمليّةَ في منتصفها لبقي الالتزام بلا صفّ يحمله،
         | فيُعاد الإنذار كلّه في الحركة التالية ويُغرَق الأبلاينو مرّتين.
         */
        $warningId = (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $user->id,
            'threshold' => $this->threshold(),
            'rep_at_warning' => $displayed,
            'breaking_transaction_id' => $breaking?->id,
            'breaking_entity_id' => $entityId,
            'responsible_upline_id' => $responsible?->id,
            'uplines_notified' => 0,
            'uplines' => null,
            'contact_due_at' => now()->addHours($this->contactHours()),
            'cycle_ends_at' => $this->rep->nextResetAt(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notified = [];

        foreach ($uplines as $upline) {
            $isResponsible = $responsible && (int) $upline->id === (int) $responsible->id;

            $this->notifyUpline($upline, $user, $displayed, $isResponsible);

            $notified[] = [
                'user_id' => (int) $upline->id,
                'name' => (string) $upline->name,
                'is_responsible' => $isResponsible,
            ];
        }

        // الأبلاين المسؤول قد لا يكون بين أبلاينِي عضويّاته النشطة (كيان المعاملة
        // انتهت عضويّته فيه مثلًا) — فلا يسقط الالتزام عنه لأنّ القائمة لم تسعه.
        if ($responsible && ! collect($notified)->contains('user_id', (int) $responsible->id)) {
            $this->notifyUpline($responsible, $user, $displayed, true);

            $notified[] = [
                'user_id' => (int) $responsible->id,
                'name' => (string) $responsible->name,
                'is_responsible' => true,
            ];
        }

        DB::table(self::TABLE)->where('id', $warningId)->update([
            'uplines_notified' => count($notified),
            'uplines' => json_encode($notified, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        AuditTrail::log(null, 'volunteer_rep_warning.apply', null, [], [
            'warning_id' => $warningId,
            'user_id' => $user->id,
            'rep' => $displayed,
            'threshold' => $this->threshold(),
            'breaking_transaction_id' => $breaking?->id,
            'breaking_entity_id' => $entityId,
            'responsible_upline_id' => $responsible?->id,
            'uplines_notified' => count($notified),
        ]);

        return [
            'warning_id' => $warningId,
            'rep' => $displayed,
            'uplines' => count($notified),
            'responsible_id' => $responsible?->id,
            'contact_due_at' => (string) DB::table(self::TABLE)->where('id', $warningId)->value('contact_due_at'),
        ];
    }

    /**
     * ⭐ **«ويُسجَّل التواصل في الملاحظات الإداريّة بالبروفايل»** — التوثيق نفسه.
     *
     * والملاحظة تُكتَب بـ`NotesPanel` لا بإدراجٍ مباشر: فهي القناة المنصوصة
     * (13.4-م-5) بسرّيّتها وحدّها الأدنى للطول وسجلّ تدقيقها. وسرّيّتها هنا
     * مقصودة: الإنذار شأنٌ بين المتطوّع وسلسلته، لا يراه صاحب البروفايل نفسه
     * ولا الفريق.
     *
     * @throws ValidationException
     */
    public function documentContact(int $warningId, User $author, string $body): array
    {
        $warning = DB::table(self::TABLE)->where('id', $warningId)->first();

        if (! $warning) {
            throw ValidationException::withMessages([
                'warning' => setting('volunteer.rep_warning.error_missing', 'الإنذار ده مش موجود.'),
            ]);
        }

        if ($warning->contacted_at !== null) {
            throw ValidationException::withMessages([
                'warning' => setting('volunteer.rep_warning.error_done', 'التواصل ده متوثَّق خلاص — مفيش توثيق تاني لنفس الإنذار.'),
            ]);
        }

        $owner = User::query()->find($warning->user_id);

        if (! $owner) {
            throw ValidationException::withMessages([
                'warning' => setting('volunteer.rep_warning.error_missing', 'الإنذار ده مش موجود.'),
            ]);
        }

        /*
         | مَن يوثّق؟ **صاحب الالتزام** بالحرف («يقع على أبلاين العضويّة الكاسرة»)
         | — ومعه مَن يملك صلاحيّة كتابة الملاحظات الإداريّة على هذا البروفايل،
         | فالأدمن يصحّح ما فات ولا يبقى الصفّ رهينةَ شخصٍ واحد.
         */
        $isResponsible = (int) $warning->responsible_upline_id === (int) $author->id;

        if (! $isResponsible && ! $author->allows('admin_notes.create', $owner)) {
            throw ValidationException::withMessages([
                'warning' => setting('volunteer.rep_warning.error_actor', 'التزام التواصل ده واقع على أبلاين العضويّة اللي وقعت فيها المعاملة — مش عليك.'),
            ]);
        }

        $noteId = $this->notes->write($author, $owner, $this->fill(setting('volunteer.rep_warning.note_body', 'توثيق تواصل إنذار درجة الالتزام (:score): :body'), [':score' => number_format((float) $warning->rep_at_warning, 2), ':body' => trim($body)]));

        DB::table(self::TABLE)->where('id', $warningId)->update([
            'contacted_at' => now(),
            'contacted_by' => $author->id,
            'contact_note_id' => $noteId,
            'updated_at' => now(),
        ]);

        AuditTrail::log($author, 'volunteer_rep_warning.contact', $owner, [], [
            'warning_id' => $warningId,
            'note_id' => $noteId,
            'on_time' => ! $this->isBreached($warning),
        ]);

        return ['warning_id' => $warningId, 'note_id' => $noteId];
    }

    /**
     * ⭐ **ختم الإخلال**: فاتت الـ48 ساعة بلا توثيق ⟵ يُختَم الصفّ `breached_at`.
     *
     * ولماذا يُختَم ولا يُترَك محسوبًا بالمقارنة؟ لأنّ اللجنة تسأل عن حالٍ **وقت
     * الكسر** لا وقت قراءتها للملفّ، والختم يجعل الواقعة ثابتةً لا تُعاد قراءتها
     * بمعايير اليوم. ومعه إشعارٌ لصاحب الالتزام: «وهو ما **يحاسب الأبلاين أيضًا**».
     *
     * @return array{scanned:int,breached:int}
     */
    public function sweepOverdue(): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return ['scanned' => 0, 'breached' => 0];
        }

        $rows = DB::table(self::TABLE)
            ->whereNull('contacted_at')
            ->whereNull('breached_at')
            ->whereNotNull('contact_due_at')
            ->where('contact_due_at', '<=', now())
            ->get();

        foreach ($rows as $row) {
            DB::table(self::TABLE)->where('id', $row->id)->update([
                'breached_at' => now(),
                'updated_at' => now(),
            ]);

            $responsible = $row->responsible_upline_id ? User::query()->find($row->responsible_upline_id) : null;
            $owner = User::query()->find($row->user_id);

            if ($responsible && $owner) {
                Notifier::send(
                    $responsible,
                    'account',
                    $this->fill(setting('volunteer.rep_warning.breach_title', 'فاتت مهلة التواصل الموثَّق')),
                    $this->fill(setting('volunteer.rep_warning.breach_body', 'عدّت :hours ساعة ولسّه مفيش توثيق تواصل مع :name بعد إنذار درجة الالتزام. التوثيق ده بيتقرا في ملفّ لجنة التحقيق لو الدرجة كمّلت نزول.'), [':hours' => (string) $this->contactHours(), ':name' => (string) $owner->name]),
                    null,
                    'volunteer',
                );
            }

            AuditTrail::log(null, 'volunteer_rep_warning.breach', null, [], [
                'warning_id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'responsible_upline_id' => $row->responsible_upline_id,
            ]);
        }

        return ['scanned' => $rows->count(), 'breached' => $rows->count()];
    }

    /**
     * شبكة الأمان اليوميّة خلف «فورًا» — ومعها ختمُ الإخلال.
     *
     * @return array{scanned:int,warned:int,breached:int}
     */
    public function sweep(): array
    {
        $userIds = Membership::query()
            ->where('status', 'active')
            // «أخوكم» خارج كلّ العدّادات والمسارات التشغيليّة (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->pluck('user_id')->unique()->values()->all();

        $result = ['scanned' => count($userIds), 'warned' => 0, 'breached' => 0];

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            if ($this->enforce($user) !== null) {
                $result['warned']++;
            }
        }

        $result['breached'] = $this->sweepOverdue()['breached'];

        return $result;
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * الإشعار نوعان بنصّين: **علمٌ** لكلّ أبلاين، و**التزامٌ بموعد** لصاحبه —
     * ولو وُحِّد النصّان لَما عرف صاحب الواجب أنّه هو (2.17-ب: ماذا حدث + ماذا تفعل).
     */
    private function notifyUpline(User $upline, User $owner, float $displayed, bool $isResponsible): void
    {
        $score = number_format($displayed, 2);

        if ($isResponsible) {
            Notifier::send(
                $upline,
                'account',
                $this->fill(setting('volunteer.rep_warning.duty_title', 'مطلوب منك تواصل موثَّق خلال :hours ساعة'), [':hours' => (string) $this->contactHours()]),
                $this->fill(setting('volunteer.rep_warning.duty_body', 'درجة الالتزام لـ:name وصلت :score، والمعاملة اللي كسرت الحاجز وقعت في عضويّتك معاه. كلّمه وسجّل التواصل في الملاحظات الإداريّة خلال :hours ساعة.'), [':name' => (string) $owner->name, ':score' => $score, ':hours' => (string) $this->contactHours()]),
                null,
                'volunteer',
                now()->addHours($this->contactHours()),
                true,
            );

            return;
        }

        Notifier::send(
            $upline,
            'account',
            $this->fill(setting('volunteer.rep_warning.upline_title', 'إنذار درجة الالتزام لواحد من فريقك')),
            $this->fill(setting('volunteer.rep_warning.upline_body', 'درجة الالتزام لـ:name وصلت :score — المؤشّر الأحمر شغّال. التواصل الموثَّق واقع على أبلاين العضويّة اللي وقعت فيها المعاملة، وأنت شايف الحالة عشان تسند.'), [':name' => (string) $owner->name, ':score' => $score]),
            null,
            'volunteer',
        );
    }

    /**
     * ملءُ متغيّرات نصٍّ **جاء من الإعدادات** (2.13).
     *
     * ⚠️ ولماذا يُمرَّر النصّ ولا يُمرَّر مفتاحه؟ لأنّ `settings:hardcoded` يقرأ
     * **الاستدعاء الأقرب** للنصّ الحرفيّ: ما كان داخل `setting()` ممتثلٌ لأنّه
     * قيمةٌ افتراضيّة يعدّلها المالك، وما كان داخل غلافٍ خاصّ **يُحسَب محروقًا**
     * ولو قرأ الإعدادَ بنفسه. والحارس محقٌّ: غلافٌ كهذا يخفي المفتاح عن كلّ
     * أداةٍ تمسح الكود بحثًا عن النصوص القابلة للتعديل. فيبقى `setting()`
     * ظاهرًا في موضع الاستعمال، وهذه تملأ المتغيّرات وحدها.
     *
     * @param  array<string,string>  $vars
     */
    private function fill(mixed $value, array $vars = []): string
    {
        $value = (string) $value;

        return $vars === [] ? $value : str_replace(array_keys($vars), array_values($vars), $value);
    }
}
