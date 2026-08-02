<?php

namespace App\Services\Library;

use App\Models\Attestation;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * الإفادة (9.1): تُولَّد **تلقائيًّا** من داتا المنصّة — تدريبات مكتملة + شهادات
 * + شارات + XP + روابط تحقّق — كإثبات موثّق، ومجّانًا بلا تذاكر.
 */
class AttestationBuilder
{
    /** حالات طلب الإفادة ولوحة ألوانها (2.16) */
    public function statusMeta(string $status): array
    {
        return match ($status) {
            'approved', 'published' => ['state' => 'ok', 'label' => (string) setting('attestations.status.approved_label', 'صدرت')],
            'rejected' => ['state' => 'danger', 'label' => (string) setting('attestations.status.rejected_label', 'مرفوضة')],
            'requested' => ['state' => 'warn', 'label' => (string) setting('attestations.status.requested_label', 'قيد الانتظار')],
            default => ['state' => 'idle', 'label' => (string) setting('attestations.status.other_label', 'مؤرشفة')],
        };
    }

    public function requests(User $user): Collection
    {
        return Attestation::query()
            ->where('user_id', $user->id)
            ->with('from_user')
            ->latest()
            ->get();
    }

    /** هل تجاوز سقف الطلبات المفتوحة؟ (القيد يُشرَح لحظة كسره فقط — 2.15-د) */
    public function openRequestsExceeded(User $user): bool
    {
        $max = (int) setting('attestations.request.max_open', 3);

        return Attestation::query()
            ->where('user_id', $user->id)
            ->where('status', 'requested')
            ->count() >= $max;
    }

    /**
     * الإفادة المولَّدة من المنصّة.
     *
     * @return array{courses:Collection,certificates:Collection,badges:Collection,xp:int,level:int}
     */
    public function platformRecord(User $user): array
    {
        $completions = CourseCompletion::query()
            ->where('user_id', $user->id)
            ->orderByDesc('completed_at')
            ->get();

        $courses = Course::query()
            ->whereIn('id', $completions->pluck('course_id'))
            ->get()
            ->keyBy('id');

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->where('status', 'valid')
            ->with('certificate_type')
            ->orderByDesc('issued_at')
            ->get()
            ->map(function (Certificate $certificate) {
                $certificate->verify_url = Route::has('verify.certificate')
                    ? route('verify.certificate', ['code' => $certificate->code])
                    : null;

                return $certificate;
            });

        $badges = Badge::query()
            ->whereIn('id', fn ($q) => $q->select('badge_id')->from('badge_user')->where('user_id', $user->id))
            ->get();

        return [
            'courses' => $completions->map(fn (CourseCompletion $c) => [
                'name' => $courses[$c->course_id]->name_ar ?? '',
                'completed_at' => $c->completed_at,
            ])->filter(fn (array $row) => $row['name'] !== '')->values(),
            'certificates' => $certificates,
            'badges' => $badges,
            'xp' => (int) $user->xp,
            'level' => (int) $user->level,
        ];
    }

    /** أين تظهر الإفادة (24.5) */
    public function placements(): array
    {
        return (array) setting('attestations.placements', [
            'في تاب «خبراتي» داخل بروفايلك العامّ',
            'في السيرة الذاتيّة تحت قسم الشهادات',
            'في الرابط العامّ الذي تشاركه مع جهة العمل',
        ]);
    }
}
