<?php

namespace App\Services\Notifications;

use App\Mail\AnnouncementMail;
use App\Models\AdAudience;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use App\Services\Admin\AudienceSegments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * ⭐ قناة البريد داخل «القنوات الموحّدة» (12.6-أ).
 *
 * المنشور الواحد يخرج من **مكان واحد في المحرّر** على ثلاث قنوات مستقلّة:
 * تاب التعليمات · Toast/إشعار · **بريد**. وهذا الصنف يملك القناة الثالثة وحدها،
 * وأربع قواعد تحكمه — كلّ واحدة منها مكتوبة لأنّ كسرها ضررٌ لا يُسترجَع:
 *
 * 1. **حدّ الهدوء (12.6-ب) يسري على البريد كما يسري على الجرس:** الزيادة
 *    **تتأجّل** إلى ما بعد نافذة اليوم بدل أن تنهال على المستخدم. وكان الحدّ
 *    مطبَّقًا في `Notifier` وحده، فلو خرج البريد بلا حدٍّ لصار الوعد نصفَ وعد.
 * 2. **تفضيل المستخدم وحالته يُفحَصان على الخادم لا في الواجهة:** مَن لا بريد
 *    له، أو بريده غير موثَّق، أو أوقف القناة — **لا يُرسَل له**، ولو مرّ الطلب
 *    من مسارٍ آخر.
 * 3. **الإرسال لا يُعيد نفسه:** صفٌّ في `announcement_deliveries` بفهرسٍ فريد
 *    يُثبت الوصول، فإعادة تشغيل الجدولة ألف مرّة لا تُخرِج رسالةً ثانية.
 * 4. **فشل بريد مستخدمٍ واحد لا يُسقِط الدفعة:** كلّ مرسَل إليه في `try` خاصّ
 *    به — سابقةُ هذا المشروع أنّ اعتراضًا عالقًا واحدًا أسقط محرّك التصعيد
 *    للمنصّة كلّها.
 */
class AnnouncementMailer
{
    public const CHANNEL = 'email';

    public function __construct(
        private readonly AnnouncementFeed $feed,
        private readonly AnnouncementPersonalizer $personalizer,
        private readonly AudienceSegments $segments,
    ) {}

    /**
     * القنوات الموحّدة كما يعرضها المحرّر — تسمياتها من الإعدادات (2.13).
     *
     * @return array<string, string>
     */
    public static function channels(): array
    {
        $channels = setting('announcements.channels', [
            'feed' => 'تاب التعليمات',
            'push' => 'إشعار / Toast',
            'email' => 'بريد',
        ]);

        return is_array($channels) && $channels !== [] ? $channels : ['feed' => 'تاب التعليمات'];
    }

    // ------------------------------------------------------------------- الإرسال

    /**
     * إرسال بريد منشورٍ واحد لجمهوره.
     *
     * @return array{sent: int, deferred: int, skipped: int, failed: int}
     */
    public function deliver(Announcement $announcement): array
    {
        $result = ['sent' => 0, 'deferred' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->channelIsOpen($announcement)) {
            return $result;
        }

        foreach ($this->recipients($announcement) as $user) {
            $outcome = $this->deliverOne($announcement, $user);
            $result[$outcome] = ($result[$outcome] ?? 0) + 1;
        }

        return $result;
    }

    /**
     * دفعة الجدولة: كلّ منشورٍ حيٍّ فُتِحت له قناة البريد.
     *
     * وهي **آمنة على التكرار** بحكم صفوف التسليم: تشغيلها كلّ ساعة لا يعني
     * رسالةً كلّ ساعة، بل يعني التقاط مَن حان وقته ومَن تأجّل ومَن تعثّر.
     *
     * @return array{announcements: int, sent: int, deferred: int, skipped: int, failed: int}
     */
    public function dispatchDue(): array
    {
        $totals = ['announcements' => 0, 'sent' => 0, 'deferred' => 0, 'skipped' => 0, 'failed' => 0];

        $due = Announcement::query()
            ->where('email_enabled', true)
            ->where('status', (string) setting('announcements.status.published', 'published'))
            ->whereNull('recurrence')
            ->limit((int) setting('announcements.email.max_announcements_per_run', 50))
            ->get()
            ->filter(fn (Announcement $a) => $this->feed->isLive($a));

        foreach ($due as $announcement) {
            $result = $this->deliver($announcement);
            $totals['announcements']++;

            foreach ($result as $key => $value) {
                $totals[$key] += $value;
            }
        }

        return $totals;
    }

    /**
     * جمهور المنشور كما يُحلّ **على الخادم لحظة الإرسال** — والشريحة المحفوظة
     * تُحلّ إلى أعضائها هنا لا في الواجهة (12.6-أ · 12.13).
     *
     * @return Collection<int, User>
     */
    public function recipients(Announcement $announcement): Collection
    {
        $max = (int) setting('announcements.email.max_recipients', 2000);
        $audience = (array) $announcement->audience;

        // استهدافٌ بشريحةٍ محفوظة وحدها: نحلّها بضربة واحدة بدل مرور كلّ المستخدمين
        if ((string) ($audience['type'] ?? '') === 'segment') {
            return $this->segmentMembers($audience, $max);
        }

        return User::query()
            ->where('status', (string) setting('announcements.email.recipient_status', 'active'))
            ->limit($max)
            ->get()
            ->filter(fn (User $user) => $this->feed->matches($announcement, $user))
            ->values();
    }

    /**
     * ⭐ الأهليّة تُفحَص **على الخادم**: بلا بريد · بريد غير موثَّق · أوقف القناة
     * ⟵ لا يُرسَل له. وترجع سببَ الاستبعاد أو `null` إن كان مؤهَّلًا.
     */
    public function skipReason(User $user): ?string
    {
        if (blank($user->email)) {
            return (string) setting('announcements.email.skip_no_address', 'بلا عنوان بريد');
        }

        if (setting('announcements.email.require_verified', true) && blank($user->email_verified_at)) {
            return (string) setting('announcements.email.skip_unverified', 'البريد غير موثَّق');
        }

        if (filled($user->email_optout_at)) {
            return (string) setting('announcements.email.skip_optout', 'أوقف قناة البريد');
        }

        return null;
    }

    /**
     * حدّ الهدوء على البريد (12.6-ب): كم رسالةً وصلت هذا المستخدم اليوم؟
     * والزيادة تتأجّل — لا تُلغى ولا تنهال.
     */
    public function overQuietLimit(User $user): bool
    {
        $limit = (int) setting('announcements.email.rate_limit.per_user_per_day', 2);

        if ($limit <= 0) {
            return false;
        }

        return AnnouncementDelivery::query()
            ->where('user_id', $user->id)
            ->where('channel', self::CHANNEL)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->startOfDay())
            ->count() >= $limit;
    }

    // ------------------------------------------------------------------ داخليّ

    /** قناة البريد مفتوحة لهذا المنشور: مفتاح المنصّة العامّ + اختيار المحرّر. */
    private function channelIsOpen(Announcement $announcement): bool
    {
        return (bool) setting('announcements.email.enabled', true)
            && (bool) $announcement->email_enabled
            && $this->feed->isLive($announcement);
    }

    /** @return Collection<int, User> */
    private function segmentMembers(array $audience, int $max): Collection
    {
        $ids = array_map('intval', (array) ($audience['ids'] ?? []));

        if ($ids === []) {
            return collect();
        }

        return AdAudience::query()
            ->whereIn('id', $ids)
            ->where('kind', AudienceSegments::KIND)
            ->get()
            ->flatMap(fn (AdAudience $segment) => $this->segments->members($segment, $max))
            ->unique('id')
            ->take($max)
            ->values();
    }

    /**
     * تسليمٌ لمستخدمٍ واحد — وكلّ فروع القرار تُخزَّن كصفٍّ يشرح ما جرى.
     *
     * @return 'sent'|'deferred'|'skipped'|'failed'
     */
    private function deliverOne(Announcement $announcement, User $user): string
    {
        $row = AnnouncementDelivery::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id, 'channel' => self::CHANNEL],
            ['status' => 'pending'],
        );

        // ⭐ حارس التكرار: ما أُرسل مرّةً لا يُرسَل ثانيةً مهما أُعيد التشغيل
        if ($row->status === 'sent') {
            return 'sent';
        }

        if ($row->status === 'deferred' && $row->deferred_until && $row->deferred_until->isFuture()) {
            return 'deferred';
        }

        if ($row->status === 'failed' && $row->attempts >= (int) setting('announcements.email.max_attempts', 3)) {
            return 'failed';
        }

        if (($reason = $this->skipReason($user)) !== null) {
            $row->forceFill(['status' => 'skipped', 'reason' => $reason])->save();

            return 'skipped';
        }

        if ($this->overQuietLimit($user)) {
            $row->forceFill([
                'status' => 'deferred',
                'reason' => (string) setting('announcements.email.defer_reason', 'اتأجّل احترامًا لحدّ الهدوء'),
                'deferred_until' => now()->addHours(max(1, (int) setting('announcements.email.defer_hours', 24))),
            ])->save();

            return 'deferred';
        }

        try {
            Mail::to($user->email)->send($this->message($announcement, $user));
        } catch (Throwable $e) {
            /*
             | ⭐ العزل: بريدٌ واحد يفشل ⟵ يُسجَّل ويُكمَل. ولو أطحنا بالدفعة كلّها
             | لصار عنوانٌ مكسور واحد كافيًا لكتم منشورٍ عن آلاف المستخدمين.
             */
            $row->forceFill([
                'status' => 'failed',
                'attempts' => (int) $row->attempts + 1,
                'reason' => Str::limit($e->getMessage(), 180),
            ])->save();

            Log::warning('فشل بريد منشور — أُكمِلت الدفعة', [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        $row->forceFill([
            'status' => 'sent',
            'attempts' => (int) $row->attempts + 1,
            'reason' => null,
            'deferred_until' => null,
            'sent_at' => now(),
        ])->save();

        return 'sent';
    }

    /** بناء الرسالة — العنوان والافتتاحيّة والتذييل من الإعدادات (2.13). */
    private function message(Announcement $announcement, User $user): AnnouncementMail
    {
        $title = $this->personalizer->render($announcement->title, $user);
        $body = $this->personalizer->render($announcement->body, $user);

        $subject = str_replace(
            ':title',
            $title,
            (string) setting('announcements.email.subject_template', ':title'),
        );

        return new AnnouncementMail(
            subjectLine: Str::limit($subject, 180, ''),
            heading: $title,
            bodyText: Str::limit((string) $body, (int) setting('announcements.email.body_limit', 2000)),
            ctaLabel: $announcement->cta_label ?: ((string) setting('announcements.email.cta_fallback_label', 'افتح التعليمات')),
            ctaUrl: $announcement->cta_url ?: (Route::has('announcements.index') ? route('announcements.index') : null),
            footer: (string) setting('announcements.email.footer', 'وصلتك الرسالة دي لأنّك مفعّل قناة البريد — تقدر توقّفها من إعدادات حسابك.'),
        );
    }
}
