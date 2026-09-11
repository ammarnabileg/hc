<?php

namespace App\Services\Volunteer\Meetings;

use App\Models\MediaItem;
use App\Models\Meeting;
use App\Models\MeetingPost;
use App\Models\MeetingQuestion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * **إدارة الاجتماع الواحد**: إنشاؤه · كودُه وأسئلته · محضرُه ومرفقاتُه
 * وتسجيلُه · تثبيت بوستٍ فيه · إلغاؤه بسبب (13.4-ح · 13.4-ن-ب · 24.2-أوّلًا).
 *
 * ⭐ **لماذا وُلِدت هذه الخدمة؟** لأنّ 24.2-أوّلًا يصف هذه الأفعال نفسها
 * **شاشةَ لوحةِ إدارة**، وكانت مكتوبةً حرفيًّا داخل `MeetingController` وحده.
 * ونسخُها إلى متحكّم اللوحة كان سيصنع **بابين بعقدين**: اجتماعٌ أُنشئ من
 * اللوحة لا يُشبه اجتماعًا أُنشئ من لوحة التطوّع، ومحضرٌ رُفِع من هنا لا يمنح
 * ما يمنحه المرفوع من هناك. فالمنطق انتقل إلى موضعٍ واحد، والمتحكّمان
 * كلاهما **يستدعيان** ولا يكرّران — وهذا هو معنى «المرآة» أصلًا.
 *
 * والحرّاس تبقى في مكانها: الصلاحيّة على المسار (12.2.1)، و`MeetingScope`
 * تقول مَن يملك إدارة هذا الاجتماع بعينه (صاحبه أو أيّ أبلاين فوقه حتى
 * السقف). هذه الخدمة **تنفّذ** ولا تقرّر مَن يُسمح له.
 */
class MeetingManager
{
    public function __construct(
        private readonly MeetingScope $scope,
        private readonly MeetingLedger $ledger,
    ) {}

    /** جماهير الاجتماع التي يقبلها الإنشاء — `specific` محجوزة للجنة التحقيق (23-0.2-4-5) */
    public const AUDIENCES = ['entity', 'sub_entity', 'all'];

    /**
     * قواعد التحقّق لفورم «+ اجتماع» — **واحدة** للوحتين، فلا يقبل بابٌ
     * ما يرفضه الآخر.
     *
     * @return array<string,array<int,string>>
     */
    public function creationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'scheduled_at' => ['required', 'date'],
            'audience' => ['required', 'in:'.implode(',', self::AUDIENCES)],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'external_link' => ['nullable', 'url', 'max:500'],
            'recording_url' => ['nullable', 'url', 'max:500'],
            'reminder_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'attendance_code' => ['nullable', 'string', 'max:32'],
            'questions' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:8192'],
        ];
    }

    /** لافتات الحقول في رسائل الخطأ — من الإعدادات لا محروقة (2.13) */
    public function creationAttributes(): array
    {
        return [
            'title' => (string) setting('meetings.screen.store_msg', 'العنوان'),
            'scheduled_at' => (string) setting('meetings.screen.store_msg_2', 'الموعد'),
            'audience' => (string) setting('meetings.screen.store_msg_3', 'الجمهور'),
        ];
    }

    /**
     * إنشاء اجتماع — والجمهور محدود بما تسمح به صلاحيّة المُنشئ.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile|null>  $attachments
     * @param  array<int,array<string,mixed>>  $questions
     */
    public function create(User $actor, array $data, array $questions = [], array $attachments = [], bool $restricted = false): Meeting
    {
        $audience = $this->resolveAudience($actor, (string) ($data['audience'] ?? 'entity'));
        $entityId = $this->resolveEntityId($actor, $audience, $data['entity_id'] ?? null);

        $meeting = Meeting::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'entity_id' => $audience === 'all' ? null : $entityId,
            'audience' => $audience,
            'owner_id' => $actor->id,
            'scheduled_at' => $data['scheduled_at'],
            'external_link' => $data['external_link'] ?? null,
            'recording_url' => $data['recording_url'] ?? null,
            'status' => 'scheduled',
            'attendance_code' => ($data['attendance_code'] ?? null) ?: null,
        ]);

        $this->saveQuestions($meeting, $actor->id, $questions);
        $this->saveAttachments($meeting, $actor->id, $attachments, $restricted);

        // تذكير قبل الموعد بعدد ساعات يحدّده المنشئ
        $hours = (int) ($data['reminder_hours'] ?? setting('meetings.reminder.hours_before', 2));

        foreach ($this->scope->audienceUserIds($meeting) as $id) {
            $this->ledger->notify(
                User::find($id),
                'meeting',
                strtr((string) setting('meetings.screen.store_msg_4', 'اجتماع جديد: :a1'), [':a1' => (string) $meeting->title]),
                strtr((string) setting('meetings.screen.store_msg_5', 'الموعد :a1 — هنفكّرك قبلها بـ:a2 ساعة.'), [
                    ':a1' => (string) $meeting->scheduled_at->format('Y-m-d H:i'),
                    ':a2' => (string) $hours,
                ]),
                route('volunteer.meetings.show', $meeting),
            );
        }

        return $meeting;
    }

    /**
     * ⭐ **رفع المحضر والمرفقات والتسجيل** بعد الانتهاء — فعلٌ مستقلّ عن
     * «إنهاء الاجتماع»: النافذة تُفتَح مرّةً واحدة، أمّا المحضر فيُستكمَل
     * لاحقًا (ولذلك يوجد فلتر «بلا محضر» أصلًا).
     *
     * و`meeting.managed` **لا يُمنَح هنا مرّةً ثانية**: `AttendanceService::end()`
     * تمنحه لحظة الإنهاء بمحضر، ومنحُه هنا أيضًا يضاعف درجةَ فعلٍ واحد.
     *
     * @param  array<int,UploadedFile|null>  $attachments
     * @return array{ok:bool,message:string}
     */
    public function saveMinutes(
        Meeting $meeting,
        User $actor,
        ?string $minutes = null,
        ?string $recordingUrl = null,
        array $attachments = [],
        bool $restricted = false,
    ): array {
        if ($meeting->status === 'cancelled') {
            return ['ok' => false, 'message' => (string) setting('meetings.manager.minutes_cancelled', 'الاجتماع ده ملغيّ — مافيش محضر لاجتماع ما انعقدش.')];
        }

        $uploaded = $this->saveAttachments($meeting, $actor->id, $attachments, $restricted);

        // فورمٌ فارغ لا يُحسَب رفعًا — ولا يُلمَس الصفّ أصلًا فيبقى `updated_at` صادقًا
        if (blank($minutes) && blank($recordingUrl) && $uploaded === 0) {
            return ['ok' => false, 'message' => (string) setting('meetings.manager.minutes_empty', 'مافيش حاجة اترفعت — اكتب المحضر أو ارفع مرفقًا أو حطّ رابط التسجيل.')];
        }

        // الفراغ لا يمحو: مَن رفع مرفقًا وحده لا يفقد محضرًا مكتوبًا قبله
        $meeting->forceFill([
            'minutes' => filled($minutes) ? $minutes : $meeting->minutes,
            'recording_url' => filled($recordingUrl) ? $recordingUrl : $meeting->recording_url,
        ])->save();

        return ['ok' => true, 'message' => strtr(
            (string) setting('meetings.manager.minutes_ok', 'اتحفظ المحضر ✓ ومعاه :a1 مرفقًا.'),
            [':a1' => (string) $uploaded],
        )];
    }

    /**
     * ⭐ الأسئلة والـOTP: صاحب الاجتماع أو أيّ أبلاين فوقه حتى السقف (13.4-ن-ب).
     *
     * @param  array<int,array<string,mixed>>  $questions
     * @return array{ok:bool,message:string}
     */
    public function saveCodeAndQuestions(Meeting $meeting, User $actor, ?string $code, array $questions = [], bool $codeGiven = false): array
    {
        if ($codeGiven) {
            $meeting->forceFill(['attendance_code' => $code])->save();
        }

        $added = $this->saveQuestions($meeting, $actor->id, $questions);

        return [
            'ok' => true,
            'message' => $added > 0
                ? strtr((string) setting('meetings.screen.questions_ok', 'اتضافت :a1 سؤال للاجتماع ✓'), [':a1' => (string) $added])
                : (string) setting('meetings.screen.questions_ok_2', 'اتحفظ كود الحضور ✓'),
        ];
    }

    /**
     * تثبيت بوست أعلى النقاش (أو فكّه) — والمثبَّت يعلو دائمًا في تاب النقاش.
     *
     * @return array{ok:bool,message:string}
     */
    public function togglePin(MeetingPost $post): array
    {
        $post->forceFill(['is_pinned' => ! $post->is_pinned])->save();

        return [
            'ok' => true,
            'message' => $post->is_pinned
                ? (string) setting('meetings.screen.pin_ok', 'اتثبّت أعلى النقاش ✓')
                : (string) setting('meetings.screen.pin_ok_2', 'اتفكّ التثبيت ✓'),
        ];
    }

    /**
     * ⭐ **إلغاء بسبب** (24.2-أوّلًا) — والسبب **إلزاميّ**: إلغاءٌ بلا سببٍ
     * مكتوب يترك جمهور الاجتماع بلا إجابة، ويترك المراجِع بلا أثر.
     *
     * والملغى **لا تُفتَح له نافذة حضور** أبدًا: `AttendanceService` تشترط
     * `status === 'ended'` للتسجيل وللتسوية معًا، فلا خصمَ غيابٍ على اجتماعٍ
     * لم ينعقد — وهذا هو الفرق الجوهريّ بين «ألغِ» و«أنهِ».
     *
     * @return array{ok:bool,message:string}
     */
    public function cancel(Meeting $meeting, User $actor, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            return ['ok' => false, 'message' => (string) setting('meetings.manager.cancel_reason_required', 'السبب مطلوب — إلغاءٌ بلا سبب يسيب الجمهور بلا إجابة.')];
        }

        if ($meeting->status === 'cancelled') {
            return ['ok' => false, 'message' => (string) setting('meetings.manager.cancel_already', 'الاجتماع ملغيّ بالفعل.')];
        }

        if ($meeting->status === 'ended') {
            return ['ok' => false, 'message' => (string) setting('meetings.manager.cancel_ended', 'الاجتماع انعقد وانتهى — اللي انعقد ما بيتلغيش.')];
        }

        DB::transaction(function () use ($meeting, $actor, $reason) {
            $meeting->forceFill([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'attendance_closes_at' => null,
            ])->save();

            // ومَن ألغى يُذكَر باسمه: إشعارٌ بلا فاعلٍ يترك الجمهور يسأل مَن يراجع
            foreach ($this->scope->audienceUserIds($meeting) as $id) {
                $this->ledger->notify(
                    User::find($id),
                    'meeting',
                    strtr((string) setting('meetings.manager.cancel_notice_title', 'اتلغى اجتماع: :a1'), [':a1' => (string) $meeting->title]),
                    strtr((string) setting('meetings.manager.cancel_notice_body', 'ألغاه :a2 — السبب: :a1'), [':a1' => $reason, ':a2' => (string) $actor->name]),
                    route('volunteer.meetings.show', $meeting),
                );
            }
        });

        return ['ok' => true, 'message' => (string) setting('meetings.manager.cancel_ok', 'اتلغى الاجتماع ✓ وجمهوره اتبلّغ بالسبب.')];
    }

    // ------------------------------------------------------------------ المرفقات

    /** مرفقات الاجتماع من مكتبة الوسائط المركزيّة — بلا جدول جديد */
    public function attachments(Meeting $meeting): Collection
    {
        return MediaItem::query()
            ->whereJsonContains('tags->meeting_id', $meeting->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * مرفقات مجموعة اجتماعات مفهرسة بمعرّف الاجتماع — استعلامٌ واحد لا
     * استعلامٌ لكلّ صفّ (2.7).
     *
     * @param  iterable<int,Meeting>  $meetings
     * @return Collection<int,Collection>
     */
    public function attachmentsFor(iterable $meetings): Collection
    {
        $ids = collect($meetings)->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return MediaItem::query()
            ->where(function ($q) use ($ids) {
                foreach ($ids as $id) {
                    $q->orWhereJsonContains('tags->meeting_id', (int) $id);
                }
            })
            ->get()
            ->groupBy(fn (MediaItem $m) => (int) ($m->tags['meeting_id'] ?? 0));
    }

    /**
     * @param  array<int,UploadedFile|null>  $files
     * @return int عدد ما رُفِع فعلًا
     */
    public function saveAttachments(Meeting $meeting, int $uploadedBy, array $files, bool $restricted = false): int
    {
        $saved = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            MediaItem::create([
                'disk' => 'public',
                'path' => $file->store('meetings/'.$meeting->id, 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'tags' => ['meeting_id' => $meeting->id, 'restricted' => $restricted],
                'uploaded_by' => $uploadedBy,
            ]);

            $saved++;
        }

        return $saved;
    }

    /**
     * أسئلة الاختيارات: الخيارات مفصولة بفاصلة، والإجابة الصحيحة لا تغادر الخادم.
     *
     * @param  array<int,array<string,mixed>>  $questions
     */
    public function saveQuestions(Meeting $meeting, int $createdBy, array $questions): int
    {
        $added = 0;

        foreach ($questions as $row) {
            $prompt = trim((string) ($row['prompt'] ?? ''));

            if ($prompt === '') {
                continue;
            }

            MeetingQuestion::create([
                'meeting_id' => $meeting->id,
                'created_by' => $createdBy,
                'type' => 'choice',
                'prompt' => $prompt,
                'options' => collect(explode(',', (string) ($row['options'] ?? '')))
                    ->map(fn ($o) => trim($o))->filter()->values()->all(),
                'correct_answer' => trim((string) ($row['correct_answer'] ?? '')) ?: null,
            ]);

            $added++;
        }

        return $added;
    }

    // ------------------------------------------------------------------ النطاق

    /**
     * هل يملك هذا المستخدم نطاقًا يتجاوز كياناته في إنشاء الاجتماعات؟
     *
     * `ALL`/`TRACK` نطاقان يعلوان الكيان أصلًا في محرّك الصلاحيّات — فمن
     * مُنِح أحدهما على `meetings.create` يُنشئ لأيّ كيان. وهذا **ليس اصطلاحًا
     * جديدًا**: الشرط نفسه كان يحكم فتح جمهور «الكلّ» منذ البداية، وكلّ ما
     * هنا أنّه صار يحكم **اختيار الكيان** أيضًا — وإلّا صار مسؤول اللوحة
     * (بلا عضويّة تطوّعيّة) عاجزًا عن إنشاء اجتماعٍ لأيّ قسم.
     */
    public function hasUnrestrictedScope(User $actor): bool
    {
        return in_array($actor->widestScope('meetings.create'), ['ALL', 'TRACK'], true);
    }

    /** «الكلّ» لا يفتحه إلّا مَن يملك نطاقًا واسعًا — وإلّا فكيان عضويّته */
    public function resolveAudience(User $actor, string $audience): string
    {
        $audience = in_array($audience, self::AUDIENCES, true) ? $audience : 'entity';

        return $audience === 'all' && ! $this->hasUnrestrictedScope($actor) ? 'entity' : $audience;
    }

    public function resolveEntityId(User $actor, string $audience, mixed $entityId): ?int
    {
        if ($audience === 'all') {
            return null;
        }

        $entityId = (int) ($entityId ?: $actor->activeMembership()?->entity_id);

        if ($this->hasUnrestrictedScope($actor)) {
            return $entityId ?: null;
        }

        return in_array($entityId, $this->scope->entityIdsWithAncestors($actor), true)
            ? $entityId
            : ($actor->activeMembership()?->entity_id ?: null);
    }
}
