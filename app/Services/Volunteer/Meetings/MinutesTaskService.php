<?php

namespace App\Services\Volunteer\Meetings;

use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Services\Volunteer\Goals\RollupService;
use App\Services\Volunteer\Tasks\TaskCreation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * **توليد مهمّة «تنفيذ» من بند المحضر** (الدستور 23-0.3).
 *
 * نصّ القاعدة حرفيًّا: «~~اجتماع (Meeting)~~ ⟵ الفعاليّات بكود الحضور —
 * **وتقدر تولّد مهمّة «تنفيذ» لبنود المحضر**». فالاجتماع ليس نوع مهمّة، لكنّ
 * ما تقرّر فيه لا يجوز أن يبقى نصًّا حرًّا خارج شجرة التنفيذ: بندٌ في المحضر
 * يصير **مهمّةً حقيقيّة** مربوطةً ببندِ حزمةٍ ولها مالك وديدلاين وعدّاد.
 *
 * **ولماذا يدويًّا لا باستخراجٍ آليّ؟** لأنّ الدستور قال «تقدر تولّد» ولم يذكر
 * حرفًا عن استنباطٍ ذكيّ لبنود الفعل ولا عن وسمٍ خاصّ في نصّ المحضر. فالمراجِع
 * يقرأ المحضر ويضغط على البند الذي يستحقّ مهمّة — وما عداه اختراعٌ فوق النصّ.
 *
 * **والبند = السطر.** المحضر يُحفَظ نصًّا حرًّا، وأقرب بنيةٍ فيه بلا فرضِ صيغةٍ
 * جديدة على مَن يكتبه هي السطر: نرفع عنه علامات التعداد الشائعة (-، •، 1.) إن
 * وُجدت، ولا نطلبها.
 *
 * والمهمّة بعد توليدها **مهمّةٌ عاديّة تمامًا**: تمرّ بعقد `TaskCreation` نفسه
 * الذي تمرّ به «مهمّة جديدة» — الفريق والسقف والغياب المعذور والربط الإلزاميّ
 * ببند — وتدخل الـRoll-up ولوحات المهام كإخوتها بلا استثناء.
 */
class MinutesTaskService
{
    public function __construct(
        private readonly TaskCreation $creation,
        private readonly MeetingScope $scope,
    ) {}

    /** نوع المهمّة المتولَّدة — «تنفيذ» (23-0.3)، ومفتاحه إعداد لا نصّ محروق (2.13) */
    public function defaultTypeKey(): string
    {
        return (string) setting('meetings.minutes.task_type_key', 'execution');
    }

    public function defaultType(): ?TaskType
    {
        return TaskType::query()->where('key', $this->defaultTypeKey())->first();
    }

    /** أقلّ عدد حروفٍ حتى يُعَدّ السطر بندًا — فالسطر «…» ليس بندًا */
    public function minItemLength(): int
    {
        return max(1, (int) setting('meetings.minutes.min_item_length', 3));
    }

    /** سقف البنود المعروضة — قائمةٌ بلا سقف تقتل الصفحة (2.15-د) */
    public function maxItems(): int
    {
        return max(1, (int) setting('meetings.minutes.max_items', 60));
    }

    /**
     * بنود المحضر مرقَّمةً — الترقيم هو ما يرسله الفورم فيُعاد التحقّق منه خادميًّا.
     *
     * @return Collection<int, array{index:int, text:string}>
     */
    public function items(Meeting $meeting): Collection
    {
        $lines = preg_split('/\R/u', (string) $meeting->minutes) ?: [];

        return collect($lines)
            ->map(fn ($line) => $this->clean((string) $line))
            ->filter(fn (string $line) => mb_strlen($line) >= $this->minItemLength())
            ->values()
            ->take($this->maxItems())
            ->map(fn (string $text, int $index) => ['index' => $index, 'text' => $text]);
    }

    /**
     * تنظيف السطر: علامات التعداد الشائعة تُرفَع **إن وُجدت** ولا تُطلَب —
     * فالمحضر نصٌّ حرّ، ولا نفرض على كاتبه صيغةً جديدة ليصير بنده قابلًا للتوليد.
     */
    private function clean(string $line): string
    {
        $line = trim(str_replace(["\u{200f}", "\u{200e}"], '', $line));

        // `\p{Nd}` يغطّي الأرقام اللاتينيّة والهنديّة معًا بلا كتابة مدًى عربيّ حرفيًّا
        return trim((string) preg_replace('/^(?:[-–—*•·]+|\p{Nd}+[.)\-–])\s*/u', '', $line));
    }

    /** نصّ البند رقم كذا — أو `null` لو لم يعد موجودًا (المحضر يُعدَّل) */
    public function itemAt(Meeting $meeting, int $index): ?string
    {
        return $this->items($meeting)->firstWhere('index', $index)['text'] ?? null;
    }

    /** هل يملك هذا المستخدم توليد مهامّ من محضر هذا الاجتماع؟ */
    public function canGenerate(User $user, Meeting $meeting): bool
    {
        return $this->scope->canManage($user, $meeting) && $user->allows('tasks.create');
    }

    /**
     * التوليد: البند مصدر العنوان والبريف، والاجتماع مصدر الأصل — وما عداهما
     * يملؤه المراجِع بنفس فورم المهمّة الجديدة.
     *
     * @param  array<string, mixed>  $data  ما تحقّق منه المتحكّم بقواعد `TaskCreation`
     */
    public function generate(Meeting $meeting, User $actor, int $itemIndex, array $data): Task
    {
        $item = $this->itemAt($meeting, $itemIndex);

        if ($item === null) {
            // المحضر اتعدّل بعد ما اتفتحت الشاشة: نرفض بدل ما نولّد مهمّة لبندٍ مات
            throw ValidationException::withMessages([
                'minutes_item' => (string) setting('meetings.minutes.stale_item', 'البند ده مبقاش موجود في المحضر — اقفل الصفحة وافتحها تاني وشوف المحضر الحاليّ.'),
            ]);
        }

        /** @var Membership|null $membership */
        $membership = $actor->activeMembership();

        $data['title'] = trim((string) ($data['title'] ?? '')) !== ''
            ? $data['title']
            : mb_substr($item, 0, 180);

        $data['brief'] = trim((string) ($data['brief'] ?? '')) !== ''
            ? $data['brief']
            : strtr((string) setting('meetings.minutes.brief_template', 'بند من محضر اجتماع «:meeting»: :item'), [
                ':meeting' => (string) $meeting->title,
                ':item' => $item,
            ]);

        $data['task_type_id'] = $data['task_type_id'] ?? $this->defaultType()?->id;

        $task = $this->creation->create($data, $actor, $membership, [
            // الأصل صريح: المهمّة تعرف من أيّ اجتماعٍ تولّدت، والاجتماع يعرف مهامّه
            'source' => 'meeting_minutes',
            'source_meeting_id' => $meeting->id,
        ]);

        // النسبة تصعد وحدها من لحظة الميلاد — مهمّة ⟵ بند ⟵ حزمة ⟵ مَعلَم ⟵ هدف (23-1.7)
        app(RollupService::class)->recalcFromTask($task);

        return $task;
    }
}
