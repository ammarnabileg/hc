<?php

namespace App\Services\Volunteer\Org;

use App\Models\Membership;
use App\Models\RepScore;
use App\Models\VolunteerCard;
use Illuminate\Support\Str;

/**
 * بطاقة المتطوّع الرقميّة (13.4-ر).
 *
 * المبدأ: البطاقة **لا تُنشئ بيانات جديدة** — تجميعٌ لمكوّنات قائمة في صفحةٍ قابلة للمشاركة.
 *  - المحتوى من **القائمة المقفولة حصرًا** (12.14-د)، و**⛔ لا بيانات تواصل إطلاقًا**.
 *  - **إظهار Rep إعدادٌ افتراضيّه مخفيّ** — رقمٌ سالب على بطاقة عامّة محرجٌ لصاحبه.
 *  - **تصير «منتهية» تلقائيًّا بانتهاء العضويّة ولا تُحذَف** (13.4-ق).
 *  - **⛔ لا بطاقة للعنصر الشرفيّ «أخوكم»** — ليس بوزشنًا ولا عضويّة (13.4-ص).
 */
final class CardIssuer
{
    /** الحقول المسموحة على البطاقة — قائمة مقفولة لا تُوسَّع من الواجهة (12.14-د) */
    public const LOCKED_FIELDS = [
        'avatar', 'name', 'code', 'position', 'department', 'track',
        'service_duration', 'country', 'governorate', 'joined_at',
    ];

    public function __construct(private readonly MemberDirectory $directory) {}

    /**
     * تُصدَر لحظة التسكين وتتحدّث عند الترقية أو النقل — تصميمٌ ثابت وبياناتٌ حيّة.
     * وترجع `null` للعنصر الشرفيّ.
     */
    public function issueFor(Membership $membership): ?VolunteerCard
    {
        if ($membership->position?->is_honorary) {
            return null;
        }

        if (! setting('volunteer_card.enabled', true)) {
            return null;
        }

        $card = VolunteerCard::query()->where('user_id', $membership->user_id)->first();
        $code = $card?->code ?? $this->generateCode($membership);

        $payload = [
            'code' => $code,
            'hash' => hash('sha256', $code.'|'.$membership->user_id.'|'.config('app.key')),
            'user_id' => $membership->user_id,
            'membership_id' => $membership->id,
            'language' => (string) setting('volunteer_card.default_language', 'ar'),
            'data_snapshot' => $this->snapshot($membership),
            'show_rep' => (bool) setting('volunteer_card.show_rep', false),
            'status' => 'valid',
            'issued_at' => $card?->issued_at ?? now(),
            'expired_at' => null,
        ];

        if ($card) {
            $card->update($payload);

            return $card->refresh();
        }

        return VolunteerCard::create($payload);
    }

    /**
     * ⭐ الحالة تُحسَب من العضويّة لا من عمود جامد: انتهت العضويّة ⟵ **منتهية**
     * — والبطاقة تبقى في سجلّه بتاريخيها ولا تُحذَف (13.4-ر-ج).
     */
    public function syncStatus(VolunteerCard $card): VolunteerCard
    {
        $membership = $card->membership;
        $ended = $membership === null
            || $membership->status === 'ended'
            || ($membership->ended_at !== null && $membership->ended_at <= now());

        if ($ended && $card->status !== 'expired') {
            $card->update([
                'status' => 'expired',
                'expired_at' => $membership?->ended_at ?? now(),
            ]);
        }

        if (! $ended && $card->status !== 'valid') {
            $card->update(['status' => 'valid', 'expired_at' => null]);
        }

        return $card->refresh();
    }

    public function isValid(VolunteerCard $card): bool
    {
        return $card->status === 'valid';
    }

    /**
     * محتوى الصفحة العامّة — **القائمة المقفولة حصرًا وبلا أيّ بيانات تواصل**.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(VolunteerCard $card): array
    {
        $user = $card->user;
        $membership = $card->membership;
        $score = $user ? RepScore::where('user_id', $user->id)->value('score') : null;
        $score = $score === null ? null : (float) $score;

        // إظهار Rep: إعداد المنصّة **و** إعداد البطاقة — والافتراضيّ مخفيّ
        $showRep = setting('volunteer_card.show_rep', false) && $card->show_rep;

        return [
            'avatar' => $user?->avatar_path,
            'user' => $user,
            'name' => $user?->name ?? '',
            'code' => $user?->code ?? '',
            'card_code' => $card->code,
            'position' => $membership?->position?->name_ar ?? ($card->data_snapshot['position'] ?? ''),
            'department' => $membership?->entity?->name_ar ?? ($card->data_snapshot['department'] ?? ''),
            'track' => $membership?->entity?->track?->name_ar ?? ($card->data_snapshot['track'] ?? ''),
            'service_duration' => $this->directory->serviceDuration($membership?->started_at),
            'country' => $user?->country?->name_ar ?? '',
            'governorate' => $user?->governorate?->name_ar ?? '',
            'joined_at' => $membership?->started_at,
            'rep_label' => $showRep ? RepBadge::label($score) : null,
            'rep_state' => $showRep ? RepBadge::state($score) : null,
            // إطار ذهبيّ لعضو نادي التميّز (13.4-ر-د)
            'is_club' => RepBadge::isClubMember($score),
            'status' => $card->status,
            'issued_at' => $card->issued_at,
            'expired_at' => $card->expired_at,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(Membership $membership): array
    {
        $user = $membership->user;

        return [
            'name' => $user?->name,
            'code' => $user?->code,
            'position' => $membership->position?->name_ar,
            'department' => $membership->entity?->name_ar,
            'track' => $membership->entity?->track?->name_ar,
            'joined_at' => $membership->started_at?->toDateString(),
            'country' => $user?->country?->name_ar,
            'governorate' => $user?->governorate?->name_ar,
        ];
    }

    private function generateCode(Membership $membership): string
    {
        $prefix = (string) setting('volunteer_card.code_prefix', 'VC');

        return $prefix.'-'.($membership->user?->code ?? Str::upper(Str::random(6)));
    }
}
