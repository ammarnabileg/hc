<?php

namespace App\Services\Volunteer\Profile;

use App\Models\AppNotification;
use App\Models\ConsentRequest;
use App\Models\User;
use App\Services\Account\ConsentDirectory;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * مسار موافقة إظهار التواصل كاملًا (13.4-م-2): طلب ⇒ إشعار ⇒ **موافقة أو رفض**.
 *
 * القواعد المحفوظة بالحرف:
 *  · **الطلب على الرقم أو الإيميل** — لا على الرقم وحده.
 *  · **سبب الطلب اختياريّ** ويظهر في الإشعار (يرفع القبول ويقلّل الرفض العشوائيّ).
 *  · **الانتهاء في صمت**: الطلب صالح 72 ساعة، وبعدها **منتهٍ لا مرفوض**،
 *    و**يُشال من إشعارات صاحب البروفايل بلا أثر**.
 *  · **الرفض صامت تمامًا**: بلا إشعار للطالب وبلا كلمة «رفض» — «غير متاح» دائمًا.
 *  · **التبريد يبدأ بعد الانتهاء أو الرفض** لا لحظة الإنشاء — وهذا فرق جوهريّ:
 *    ضبطه وقت الإنشاء كان يحرق مدّة التبريد بالتوازي مع مهلة الطلب فيصير بلا أثر.
 *  · **السحب بلا إشعار للطرف الآخر** (في `ConsentDirectory` بمجال الحساب).
 *  · **الأبلاين مستثنًى** ولا يظهر في «مَن يرى بياناتي» — حقّه نظاميّ لا موافقة.
 */
final class ConsentFlow
{
    /** الحقول القابلة للطلب — الرقم والإيميل معًا (13.4-م-2) */
    public const FIELDS = ['phone', 'email'];

    public static function fieldLabel(string $field): string
    {
        return ConsentDirectory::fieldLabel($field);
    }

    public function validityHours(): int
    {
        return max(1, (int) setting('volunteer.consent.request_hours', 72));
    }

    public function cooldownHours(): int
    {
        return max(0, (int) setting('volunteer.consent.cooldown_hours', 72));
    }

    // ------------------------------------------------------------------ الطلب

    /**
     * تسجيل طلب إظهار. الردّ للطالب **محايد دائمًا** فلا يكشف قبولًا ولا رفضًا،
     * ولذلك تُرجِع الدالّة `null` بصمت عند التبريد أو وجود طلب قائم.
     */
    public function request(User $requester, User $owner, string $field, ?string $reason = null): ?ConsentRequest
    {
        if (! in_array($field, self::FIELDS, true)) {
            throw new RuntimeException('حقل غير مدعوم لطلب الإظهار.');
        }

        if ($requester->id === $owner->id) {
            throw new RuntimeException('دي بياناتك أصلًا.');
        }

        $this->sweepExpired($owner);

        if ($this->isBlocked($requester, $owner, $field)) {
            return null;
        }

        $consent = ConsentRequest::create([
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'field' => $field,
            'reason' => $this->trimReason($reason),
            'status' => 'pending',
            'request_expires_at' => now()->addHours($this->validityHours()),
            // ⛔ التبريد لا يُضبَط هنا — يبدأ **بعد** الانتهاء أو الرفض (13.4-م-2)
            'cooldown_until' => null,
        ]);

        $this->announce($consent);

        return $consent;
    }

    /**
     * إشعار صاحب البروفايل بوصول الطلب (2.8) — بمهلة ظاهرة وزرّ إجراء مباشر.
     * وهو ما كان ناقصًا فيبقى الطلب `pending` أبدًا بلا أن يعلم به صاحبه.
     */
    public function announce(ConsentRequest $consent): void
    {
        $requester = $consent->requester;
        $owner = $consent->owner;

        if (! $requester || ! $owner) {
            return;
        }

        $this->notifyOwner($consent, $requester, $owner);
    }

    /** تبريدٌ سارٍ أو طلبٌ معلّق لم تنتهِ مهلته ⇒ لا طلب جديد (منعًا للإزعاج المتكرّر) */
    public function isBlocked(User $requester, User $owner, string $field): bool
    {
        $latest = ConsentRequest::query()
            ->where('requester_id', $requester->id)
            ->where('owner_id', $owner->id)
            ->where('field', $field)
            ->latest('id')
            ->first();

        if (! $latest) {
            return false;
        }

        if ($latest->status === 'pending' && $latest->request_expires_at > now()) {
            return true;
        }

        if ($latest->status === 'granted' && $this->isLive($latest)) {
            return true;
        }

        return $latest->cooldown_until !== null && $latest->cooldown_until > now();
    }

    // ------------------------------------------------------------------ الحسم

    /** موافقة صاحب البيانات: الحقل يُفتَح لمدّة يحدّدها الأدمن ثمّ يرجع مقفولًا — الأصل الخصوصيّة */
    public function approve(ConsentRequest $consent, User $owner): ConsentRequest
    {
        $this->guardOwner($consent, $owner);

        $consent->update([
            'status' => 'granted',
            'granted_at' => now(),
            'responded_at' => now(),
            'decided_by' => $owner->id,
            'consent_expires_at' => now()->addDays(ConsentDirectory::durationDays()),
            // التبريد لا معنى له بعد الموافقة — الحقل مفتوح فعلًا
            'cooldown_until' => null,
        ]);

        $this->clearOwnerNotification($consent);

        return $consent->refresh();
    }

    /**
     * رفض **صامت تمامًا**: بلا سبب إلزاميّ وبلا إشعار للطالب وبلا كلمة «رفض» —
     * والطالب يرى «غير متاح» فقط، حفظًا للعلاقة داخل الفريق.
     * ومن **هنا** يبدأ التبريد.
     */
    public function deny(ConsentRequest $consent, User $owner): ConsentRequest
    {
        $this->guardOwner($consent, $owner);

        $consent->update([
            'status' => 'denied',
            'responded_at' => now(),
            'decided_by' => $owner->id,
            'cooldown_until' => now()->addHours($this->cooldownHours()),
        ]);

        $this->clearOwnerNotification($consent);

        return $consent->refresh();
    }

    /**
     * انتهاء المهلة بلا ردّ: **منتهٍ لا مرفوض**، ويُشال من إشعارات صاحب البروفايل
     * تلقائيًّا **بلا أثر**، ومنه يبدأ التبريد كذلك.
     *
     * @return int عدد الطلبات التي انتهت في هذه الجولة
     */
    public function sweepExpired(?User $owner = null): int
    {
        $rows = ConsentRequest::query()
            ->where('status', 'pending')
            ->where('request_expires_at', '<=', now())
            ->when($owner, fn ($q) => $q->where('owner_id', $owner->id))
            ->get();

        foreach ($rows as $consent) {
            $consent->update([
                'status' => 'expired',
                'cooldown_until' => now()->addHours($this->cooldownHours()),
            ]);

            $this->clearOwnerNotification($consent);
        }

        return $rows->count();
    }

    // ------------------------------------------------------------------ قراءات

    /**
     * الطلبات المنتظِرة ردّ صاحب البروفايل — تظهر له في تاب التواصل.
     *
     * @return Collection<int, ConsentRequest>
     */
    public function pendingFor(User $owner): Collection
    {
        $this->sweepExpired($owner);

        return ConsentRequest::query()
            ->with('requester:id,name,code,avatar_path')
            ->where('owner_id', $owner->id)
            ->where('status', 'pending')
            ->orderBy('request_expires_at')
            ->get();
    }

    /** هل لهذا الطالب موافقة سارية على هذا الحقل؟ */
    public function hasLiveGrant(User $requester, User $owner, string $field): bool
    {
        return ConsentRequest::query()
            ->where('requester_id', $requester->id)
            ->where('owner_id', $owner->id)
            ->where('field', $field)
            ->where('status', 'granted')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('consent_expires_at')->orWhere('consent_expires_at', '>', now()))
            ->exists();
    }

    /** طلب الزائر القائم على هذا الحقل — ومنه يُعرَف شكل الزرّ (طلب / مستنّي الردّ) */
    public function latestFor(User $requester, User $owner, string $field): ?ConsentRequest
    {
        return ConsentRequest::query()
            ->where('requester_id', $requester->id)
            ->where('owner_id', $owner->id)
            ->where('field', $field)
            ->latest('id')
            ->first();
    }

    /**
     * مؤشّرات الثقة للإدارة المركزيّة (13.4-ك):
     * عدد المرفوضة · معدّل القبول · **متوسّط زمن الردّ** (البطء يعطّل التنسيق كالرفض).
     */
    public function trustMetrics(?int $days = null): array
    {
        $days = $days ?: (int) setting('ux.lists.default_range_days', 30);

        $rows = ConsentRequest::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->get(['status', 'created_at', 'responded_at']);

        $answered = $rows->whereIn('status', ['granted', 'denied']);
        $granted = $rows->where('status', 'granted')->count();
        $denied = $rows->where('status', 'denied')->count();

        $responseHours = $answered
            ->filter(fn (ConsentRequest $r) => $r->responded_at !== null)
            ->map(fn (ConsentRequest $r) => $r->created_at->diffInMinutes($r->responded_at) / 60);

        return [
            'days' => $days,
            'total' => $rows->count(),
            'granted' => $granted,
            'denied' => $denied,
            'expired' => $rows->where('status', 'expired')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
            // معدّل القبول يُحسَب على المحسوم فقط — فالمنتهي ليس رفضًا
            'acceptance_rate' => $answered->count() > 0 ? (int) round($granted / $answered->count() * 100) : null,
            'avg_response_hours' => $responseHours->isNotEmpty() ? round($responseHours->avg(), 1) : null,
        ];
    }

    /**
     * سجلّ الطلبات: مَن طلب / متى / السبب / النتيجة — يمنع الإزعاج ويحمي الطرفين.
     *
     * @return Collection<int, ConsentRequest>
     */
    public function ledger(?int $days = null, ?User $owner = null): Collection
    {
        $days = $days ?: (int) setting('ux.lists.default_range_days', 30);

        return ConsentRequest::query()
            ->with(['requester:id,name,code', 'owner:id,name,code'])
            ->where('created_at', '>=', now()->subDays($days))
            ->when($owner, fn ($q) => $q->where('owner_id', $owner->id))
            ->latest('id')
            ->limit((int) setting('volunteer.profile.consent.ledger_size', 50))
            ->get();
    }

    /** نتيجة الطلب كما تُعرَض للإدارة — وللطالب تبقى «غير متاح» دائمًا */
    public static function outcomeLabel(ConsentRequest $consent): string
    {
        return match ($consent->status) {
            'granted' => (string) setting('volunteer.profile.consent.outcome.granted', 'اتوافق'),
            'denied' => (string) setting('volunteer.profile.consent.outcome.denied', 'اترفض'),
            'expired' => (string) setting('volunteer.profile.consent.outcome.expired', 'انتهت المدّة'),
            'revoked' => (string) setting('volunteer.profile.consent.outcome.revoked', 'اتسحبت'),
            default => (string) setting('volunteer.profile.consent.outcome.pending', 'مستنّي ردّ'),
        };
    }

    /** نصّ محايد واحد للطالب في كلّ الحالات — لا يكشف رفضًا أبدًا (13.4-م-2) */
    public static function neutralLabel(): string
    {
        return (string) setting('volunteer.profile.consent.neutral', 'غير متاح / انتهت المدّة');
    }

    // ------------------------------------------------------------------ داخليّ

    private function isLive(ConsentRequest $consent): bool
    {
        return $consent->revoked_at === null
            && ($consent->consent_expires_at === null || $consent->consent_expires_at > now());
    }

    private function guardOwner(ConsentRequest $consent, User $owner): void
    {
        if ((int) $consent->owner_id !== $owner->id) {
            throw new RuntimeException('الطلب ده مش بتاعك.');
        }

        if ($consent->status !== 'pending') {
            throw new RuntimeException('الطلب ده اتحسم خلاص.');
        }
    }

    private function trimReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            return null;
        }

        return mb_substr($reason, 0, (int) setting('volunteer.profile.consent.reason_max', 300));
    }

    /** إشعار صاحب البروفايل — بمهلة ظاهرة وبزرّ إجراء مباشر (2.8) */
    private function notifyOwner(ConsentRequest $consent, User $requester, User $owner): void
    {
        $title = str_replace(
            [':name', ':field'],
            [$requester->shortName(), self::fieldLabel($consent->field)],
            (string) setting('volunteer.profile.consent.notify_title', ':name طالب إظهار :field'),
        );

        $body = $consent->reason
            ? str_replace(':reason', $consent->reason, (string) setting('volunteer.profile.consent.notify_body', 'السبب: :reason'))
            : (string) setting('volunteer.profile.consent.notify_no_reason', 'من غير سبب مكتوب — القرار ليك.');

        $notification = Notifier::send(
            user: $owner,
            category: 'consent',
            title: $title,
            body: $body,
            url: route('profile.me', ['tab' => VolunteerProfileTabs::CONTACT]),
            layer: 'volunteer',
            deadlineAt: $consent->request_expires_at,
            requiresAction: true,
        );

        Notifier::about($notification, $consent);
    }

    /**
     * إزالة إشعار الطلب من صندوق صاحب البروفايل — **بلا أثر**:
     * لا عند الانتهاء ولا بعد الحسم يبقى صفٌّ معلّق يذكّره بشيء انتهى.
     */
    private function clearOwnerNotification(ConsentRequest $consent): void
    {
        AppNotification::query()
            ->where('user_id', $consent->owner_id)
            ->where('reference_type', $consent->getMorphClass())
            ->where('reference_id', $consent->getKey())
            ->delete();
    }
}
