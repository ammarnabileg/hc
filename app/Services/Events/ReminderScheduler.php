<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventNotice;
use App\Models\EventReminder;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * **تذكيرات الفعاليّات المجدولة** (13.3 · 12.11 · 24.3).
 *
 * النصّ الحاكم حرفيًّا:
 *  • 13.3 (التسجيل والتذكرة) — «**تذكيرات مجدولة** (قبل يوم/ساعة) عبر
 *    الإشعارات/Toast/بريد (2.8)».
 *  • 12.11 (فورم الفعاليّة) — «**تذكيرات مجدولة** (قبل يوم/ساعة)».
 *  • 24.3 (بلوك إعدادات الفعاليّات) — «**مواعيد التذكير** (قائمة قابلة للإضافة
 *    — افتراضيّ: قبل يوم · قبل ساعة) + قنواتها (جرس/Toast/بريد)».
 *
 * ولذلك: المواعيد **قائمةٌ في الإعدادات** لا رقمان محروقان، والقرار **داخل هذه
 * الخدمة وحدها** لا في تعبير كرون يتجمّد على القيمة القديمة بعد أن يغيّرها
 * الأدمن. والمُستقبِل: **المسجّلون في الفعاليّة** — لأنّ النصّ يضع التذكير في
 * بند «التسجيل والتذكرة»، أمّا الدعوة بشريحةٍ فبندٌ آخر مستقلّ في 12.11.
 *
 * ⚠️ **وحارس عدم التكرار في القاعدة لا في الذاكرة:** صفٌّ فريد لكلّ
 * (فعاليّة · مستخدم · موعد · قناة)، فإعادة التشغيل — أو مسحتان متداخلتان —
 * لا توصِّل التذكير نفسه مرّتين لنفس المستلِم.
 */
class ReminderScheduler
{
    public const CHANNEL_BELL = 'bell';

    public const CHANNEL_EMAIL = 'email';

    public function __construct(private readonly EventPresenter $presenter) {}

    /**
     * مسحةٌ واحدة: كلّ فعاليّةٍ منشورةٍ لم تبدأ بعد × كلّ موعدٍ حان × كلّ مسجَّل.
     *
     * @return array{events:int, sent:int, skipped:int, failed:int}
     */
    public function dispatchDue(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $offsets = $this->offsets();
        $channels = $this->channels();
        $result = ['events' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        if ($offsets === [] || $channels === []) {
            return $result;
        }

        $widest = max($offsets);

        $events = Event::query()
            ->where('status', (string) setting('events.published_status', 'published'))
            ->where('reminders_enabled', true)
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->addMinutes($widest))
            ->orderBy('starts_at')
            ->limit((int) setting('events.reminder.events_per_run', 50))
            ->get();

        foreach ($events as $event) {
            $due = $this->dueOffsets($event, $now, $offsets);

            if ($due === []) {
                continue;
            }

            $result['events']++;

            foreach ($this->registrations($event) as $registration) {
                foreach ($due as $offset) {
                    foreach ($channels as $channel) {
                        $outcome = $this->deliverOne($event, $registration, $offset, $channel);
                        $result[$outcome]++;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * ⭐ «إشعار المسجّلين» المجدول (24.3: «إرسال الآن/مجدول»).
     *
     * المطالبة **ذرّيّة**: أوّل تحديثٍ من `pending` إلى `sending` وحده ينجح،
     * فلا تلتقط مسحتان الإشعارَ نفسَه فيصل مرّتين.
     *
     * @return array{notices:int, sent:int}
     */
    public function dispatchNotices(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $result = ['notices' => 0, 'sent' => 0];

        $pending = EventNotice::query()
            ->where('status', 'pending')
            ->where('send_at', '<=', $now)
            ->orderBy('send_at')
            ->limit((int) setting('events.notice.per_run', 20))
            ->get();

        foreach ($pending as $notice) {
            $claimed = EventNotice::query()
                ->whereKey($notice->id)
                ->where('status', 'pending')
                ->update(['status' => 'sending', 'updated_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            $result['notices']++;
            $result['sent'] += $this->deliverNotice($notice);
        }

        return $result;
    }

    /** بثّ إشعارٍ واحدٍ لكلّ مسجّلي الفعاليّة — ويُرجِع عدد مَن وصلهم */
    public function deliverNotice(EventNotice $notice): int
    {
        $event = $notice->event;

        if (! $event) {
            $notice->forceFill(['status' => 'sent', 'sent_at' => now(), 'recipients' => 0])->save();

            return 0;
        }

        $count = 0;

        foreach ($this->registrations($event) as $registration) {
            $user = $registration->user;

            if (! $user) {
                continue;
            }

            try {
                if ($notice->channel === self::CHANNEL_EMAIL && $user->email) {
                    Mail::raw(
                        $notice->body."\n\n".route('events.show', $event->slug),
                        fn ($message) => $message->to($user->email)->subject((string) $event->title_ar),
                    );
                } else {
                    Notifier::about(
                        Notifier::send(
                            user: $user,
                            category: (string) setting('events.reminder.category', 'event'),
                            title: (string) $event->title_ar,
                            body: $notice->body,
                            url: route('events.show', $event->slug),
                        ),
                        $event,
                    );
                }

                $count++;
            } catch (Throwable $e) {
                Log::warning('event notice delivery failed — batch continued', [
                    'notice_id' => $notice->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $notice->forceFill(['status' => 'sent', 'sent_at' => now(), 'recipients' => $count])->save();

        return $count;
    }

    /**
     * مواعيد التذكير بالدقائق — قائمةٌ يحرّرها الأدمن، والافتراضيّ **قبل يوم
     * وقبل ساعة** كما ينصّ 24.3 حرفيًّا.
     *
     * @return list<int>
     */
    public function offsets(): array
    {
        $raw = setting('events.reminder.offsets_minutes', [1440, 60]);

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $offsets = [];

        foreach ((array) $raw as $value) {
            $minutes = (int) $value;

            if ($minutes > 0) {
                $offsets[] = $minutes;
            }
        }

        $offsets = array_values(array_unique($offsets));
        rsort($offsets);

        return $offsets;
    }

    /**
     * القنوات المفعّلة (24.3: «جرس/Toast/بريد»).
     *
     * ⚠️ **والمبنيّ منها قناتان:** الجرس والبريد. أمّا **Toast** فليس في هذا
     * المشروع قناةَ تسليمٍ مستقلّة لها مستقرّ — إنّما هو عرضٌ لحظيّ لصفّ الجرس
     * عند أوّل طلبٍ للمستخدم، وطبقتُه مشتركة لا يملكها هذا المجال. فتركناها
     * **مسجَّلةً في التقرير** بدل أن نكتب قناةً تدّعي التسليم ولا تسلّم.
     *
     * @return list<string>
     */
    public function channels(): array
    {
        $raw = setting('events.reminder.channels', [self::CHANNEL_BELL, self::CHANNEL_EMAIL]);

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $allowed = [self::CHANNEL_BELL, self::CHANNEL_EMAIL];

        return array_values(array_intersect($allowed, array_map('strval', (array) $raw)));
    }

    /**
     * المواعيد التي حان وقتها لهذه الفعاليّة: بلغَ الوقتُ الموعدَ ولم تبدأ بعد.
     *
     * @param  list<int>  $offsets
     * @return list<int>
     */
    public function dueOffsets(Event $event, CarbonImmutable $now, array $offsets): array
    {
        $start = CarbonImmutable::parse($event->starts_at);
        $due = [];

        foreach ($offsets as $offset) {
            if ($now->greaterThanOrEqualTo($start->subMinutes($offset)) && $now->lessThan($start)) {
                $due[] = $offset;
            }
        }

        return $due;
    }

    /**
     * تسليمٌ واحد — و**الصفّ يُكتَب أوّلًا**: الفهرس الفريد هو الذي يحسم السباق،
     * فلو سبقتنا مسحةٌ أخرى ارتدّ الإدراج ولم يصل التذكير مرّتين.
     *
     * @return 'sent'|'skipped'|'failed'
     */
    private function deliverOne(Event $event, EventRegistration $registration, int $offset, string $channel): string
    {
        $user = $registration->user;

        if (! $user) {
            return 'skipped';
        }

        if ($channel === self::CHANNEL_EMAIL && ! $user->email) {
            return 'skipped';
        }

        try {
            $row = EventReminder::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'offset_minutes' => $offset,
                'channel' => $channel,
                'status' => 'sending',
            ]);
        } catch (QueryException) {
            // الفهرس الفريد ردّ الإدراج ⟵ التذكير وصل قبل كده، وده المطلوب
            return 'skipped';
        }

        try {
            $channel === self::CHANNEL_BELL
                ? $this->sendBell($event, $user, $offset)
                : $this->sendEmail($event, $user, $offset);
        } catch (Throwable $e) {
            /*
             | العزل: مستلِمٌ واحد يفشل ⟵ يُسجَّل ويُكمَل. ولو أطحنا بالدفعة كلّها
             | لصار عنوانٌ مكسور واحد كافيًا لكتم التذكير عن كلّ المسجّلين.
             */
            $row->forceFill(['status' => 'failed', 'reason' => Str::limit($e->getMessage(), 180)])->save();

            Log::warning('event reminder delivery failed — batch continued', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        $row->forceFill(['status' => 'sent', 'reason' => null, 'sent_at' => now()])->save();

        return 'sent';
    }

    private function sendBell(Event $event, User $user, int $offset): void
    {
        Notifier::about(
            Notifier::send(
                user: $user,
                category: (string) setting('events.reminder.category', 'event'),
                title: $this->title($event, $offset),
                body: $this->body($event, $user),
                url: route('events.show', $event->slug),
                deadlineAt: $event->starts_at,
            ),
            $event,
        );
    }

    private function sendEmail(Event $event, User $user, int $offset): void
    {
        $subject = $this->title($event, $offset);
        $body = $this->body($event, $user)."\n\n".route('events.show', $event->slug);

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }

    /** عنوان التذكير — بقالبٍ من الإعدادات لا بجملةٍ محروقة (2.13) */
    public function title(Event $event, int $offset): string
    {
        return str_replace(
            [':title', ':when'],
            [(string) $event->title_ar, $this->offsetLabel($offset)],
            (string) setting('events.reminder.title_template', 'فاكر «:title»؟ ابتدت بعد :when'),
        );
    }

    /** نصّ التذكير بالتوقيت المحلّيّ للمستخدم (13.3: «الوقت بتوقيت المستخدم») */
    public function body(Event $event, User $user): string
    {
        return str_replace(
            [':title', ':time', ':timezone'],
            [
                (string) $event->title_ar,
                $this->presenter->localStart($event, $user)->format((string) setting('events.reminder.time_format', 'Y-m-d · H:i')),
                $this->presenter->timezone($user),
            ],
            (string) setting('events.reminder.body_template', '«:title» يوم :time بتوقيت :timezone — جهّز نفسك ومكانك.'),
        );
    }

    /** «قبل يوم» و«قبل ساعة» بلفظهما، وما عداهما بالدقائق — والألفاظ إعدادات */
    public function offsetLabel(int $minutes): string
    {
        $labels = setting('events.reminder.offset_labels', ['1440' => 'يوم', '60' => 'ساعة']);

        if (is_string($labels)) {
            $labels = json_decode($labels, true);
        }

        return (string) (((array) $labels)[(string) $minutes]
            ?? $minutes.' '.setting('events.reminder.minutes_word', 'دقيقة'));
    }

    /** @return \Illuminate\Support\Collection<int, EventRegistration> */
    private function registrations(Event $event)
    {
        return EventRegistration::query()
            ->with('user:id,name,email,country_id')
            ->where('event_id', $event->id)
            ->limit((int) setting('events.reminder.recipients_per_event', 2000))
            ->get();
    }
}
