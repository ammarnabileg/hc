<?php

namespace App\Services\Volunteer\People;

use App\Models\User;
use App\Models\VolunteerRecording;
use App\Models\VolunteerRecordingClaim;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * التسجيلات وكسبها (13.4-ل · 24.4-9).
 *
 * قاعدة الكسب: **التحقّق من الرمز Server-side**، و**مرّة واحدة لكلّ (تسجيل، متطوّع)**
 * — والقيد الفريد في قاعدة البيانات هو الحارس الأخير، لا شرط `if` في الكود.
 */
class RecordingRewards
{
    public const RESULT_OK = 'ok';

    public const RESULT_WRONG = 'wrong';

    public const RESULT_ALREADY = 'already';

    public const RESULT_NO_OTP = 'no_otp';

    public function __construct(private readonly PeopleBridge $bridge) {}

    /** تسجيلات قسمي — والمسودّة لا تظهر لغير مَن يديرها */
    public function listFor(User $user, array $entityIds, array $filters = []): Collection
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return VolunteerRecording::query()
            ->with(['entity', 'creator'])
            ->where('status', 'published')
            ->when($entityIds !== [], fn ($b) => $b->where(fn ($w) => $w->whereIn('entity_id', $entityIds)->orWhereNull('entity_id')))
            ->when(! empty($filters['entity']), fn ($b) => $b->where('entity_id', (int) $filters['entity']))
            ->when(! empty($filters['has_otp']), fn ($b) => $b->whereNotNull('otp'))
            ->when($q !== '', fn ($b) => $b->where('title', 'like', "%{$q}%"))
            ->when(($filters['sort'] ?? 'newest') === 'most_viewed',
                fn ($b) => $b->orderByDesc('views_count'),
                fn ($b) => $b->orderByDesc('created_at'))
            ->get();
    }

    /** التسجيلات التي كسبها المستخدم بالفعل — لشارة «تمّ» */
    public function claimedIds(User $user, Collection $recordings): array
    {
        if ($recordings->isEmpty()) {
            return [];
        }

        return VolunteerRecordingClaim::query()
            ->where('user_id', $user->id)
            ->whereIn('volunteer_recording_id', $recordings->pluck('id'))
            ->pluck('volunteer_recording_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** نصّ الشارة: «+10 VXP و+0.2 Rep — مرّة واحدة» — أرقامه من الإعدادات وجدول Rep */
    public function rewardBadge(): string
    {
        return str_replace(
            [':vxp', ':rep'],
            [(string) $this->vxpValue(), rtrim(rtrim(number_format($this->repValue(), 2), '0'), '.')],
            (string) setting('academy.recording.badge', '+:vxp VXP و+:rep Rep — مرّة واحدة'),
        );
    }

    public function vxpValue(): float
    {
        return (float) setting('academy.recording.vxp_value', 10);
    }

    public function repValue(): float
    {
        return rep_rule('academy.recording_otp', 0.2);
    }

    /**
     * التحقّق من الرمز ومنح النقاط — **Server-side** ومرّةً واحدة.
     *
     * @return array{result:string,message:string}
     */
    public function claim(VolunteerRecording $recording, User $user, string $otp): array
    {
        if (! $recording->grantsPoints()) {
            return [
                'result' => self::RESULT_NO_OTP,
                'message' => (string) setting('academy.recording.no_otp.message', 'التسجيل ده مالوش رمز — اتفرّج واستفيد وبس.'),
            ];
        }

        if (! hash_equals(trim((string) $recording->otp), trim($otp))) {
            return [
                'result' => self::RESULT_WRONG,
                'message' => (string) setting('academy.recording.wrong_otp.message', 'الرمز غير صحيح — راجعه في آخر التسجيل وجرّب تاني.'),
            ];
        }

        try {
            $claim = DB::transaction(fn () => VolunteerRecordingClaim::create([
                'volunteer_recording_id' => $recording->id,
                'user_id' => $user->id,
                'vxp_awarded' => $this->vxpValue(),
                'rep_awarded' => $this->repValue(),
                'claimed_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // القيد الفريد هو الحارس — فحتى الطلبان المتزامنان لا يمنحان مرّتين
            return [
                'result' => self::RESULT_ALREADY,
                'message' => (string) setting('academy.recording.already.message', 'حصلت على نقاط التسجيل ده بالفعل.'),
            ];
        }

        $this->bridge->credit($user, 'vxp', $this->vxpValue(), 'academy.recording_otp', $recording);
        $this->bridge->credit($user, 'rep', $this->repValue(), 'academy.recording_otp', $recording);

        $recording->increment('views_count');

        return [
            'result' => self::RESULT_OK,
            'message' => str_replace(
                [':vxp', ':rep'],
                [(string) $claim->vxp_awarded, (string) $claim->rep_awarded],
                (string) setting('academy.recording.granted.message', 'تمام ✓ اتضاف لك :vxp VXP و:rep Rep.'),
            ),
        ];
    }

    /** بلاغ رابط معطّل — يذهب لمن أضاف التسجيل */
    public function reportBroken(VolunteerRecording $recording, User $reporter, ?string $reason): void
    {
        $recording->forceFill(['broken_reported_at' => now()])->save();

        if ($recording->creator) {
            $this->bridge->notify(
                $recording->creator,
                'academy',
                'بلاغ رابط معطّل',
                $recording->title.($reason ? ' — '.$reason : ''),
                route('volunteer.academy.recordings'),
                now()->addHours((int) setting('academy.recording.report_sla_hours', 24)),
                true,
            );
        }
    }
}
