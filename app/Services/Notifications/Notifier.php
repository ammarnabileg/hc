<?php

namespace App\Services\Notifications;

use App\Mail\AnnouncementMail;
use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Throwable;

/**
 * بوّابة الإشعارات الموحّدة (الدستور 2.8).
 *
 * لماذا خدمة واحدة؟ لأنّ كلّ المجالات تكتب في نفس السجلّ الدائم الذي يقرأه
 * الجرس ومركز الإشعارات، فلو كتب كلّ مجال بطريقته اختلفت التابات والعدّادات.
 * والاستعمال من أيّ مجال:
 *   Notifier::send($user, 'certificate', 'صدرت شهادتك', null, route('learning.certificates'));
 */
class Notifier
{
    /**
     * إرسال إشعار واحد لمستخدم واحد.
     *
     * @param  string  $category  فئة الإشعار — قابلة للتوسّع بلا تعديل كود (2.8)
     * @param  string  $layer  الطبقة: platform · volunteer — وتاب التطوّع للمتطوّعين وحدهم
     * @param  Carbon|null  $deadlineAt  مهلة تُعرَض كعدّاد ملوّن برمزه (2.16)
     * @param  bool  $requiresAction  صفّ «يحتاج إجراء» بزرّ مباشر داخله
     */
    public static function send(
        User $user,
        string $category,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $layer = 'platform',
        ?Carbon $deadlineAt = null,
        bool $requiresAction = false,
    ): AppNotification {
        $layer = self::normalizeLayer($layer);

        /*
         | ⭐ مصفوفة النوع × القناة (24.3): نوعٌ محكومٌ بالمصفوفة وعمود «جرس»
         | موقوفٌ له يعني **لا سجلّ إطلاقًا** — الجرس هو السجلّ الدائم نفسه
         | (2.8)، فإيقافه إيقافٌ حقيقيّ للحدث كلّه لا لواجهة عرضه وحدها.
         | وترجع نسخةٌ غير محفوظة حفاظًا على عقد الإرجاع لمن يستعمل الناتج.
         */
        if (self::matrixGoverned($category) && ! self::matrixAllows($category, 'bell')) {
            return new AppNotification([
                'user_id' => $user->id, 'layer' => $layer, 'category' => $category,
                'title' => $title, 'body' => $body, 'url' => $url,
                'deadline_at' => $deadlineAt, 'requires_action' => $requiresAction,
            ]);
        }

        /*
         | ⭐ تجميع المتشابه في إشعارٍ واحد (12.6-ب): نفس العنوان ونفس الفئة
         | لنفس المستخدم داخل نافذةٍ قصيرة وهو **لم يقرأه بعد** ⟵ عدّادٌ يزيد
         | على الصفّ القائم بدل صفٍّ جديد. كان «التجميع» عدّاد عرضٍ في شاشة
         | الأدمن لا يدمج شيئًا.
         */
        if ($existing = self::mergeable($user, $layer, $category, $title)) {
            return self::merge($existing, $body, $url, $deadlineAt, $requiresAction);
        }

        /*
         | ⭐ حدّ الهدوء (12.6-ب): الأدمن يحدّد أقصى عدد إشعارات للمستخدم في اليوم،
         | و**الزيادة تتجمّع** في إشعارٍ واحد بدل أن تنهال عليه. وكانت الشاشة تَعِد
         | الأدمن بحمايةٍ لا وجود لها في أيّ مسارٍ تنفيذيّ، فيبثّ بثقة ويُغرِق الناس.
         */
        if (self::overQuietLimit($user, $category)) {
            return self::digest($user, $layer, $title);
        }

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'layer' => $layer,
            'category' => $category,
            'group_key' => self::groupKey($category, $title),
            'group_count' => 1,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'deadline_at' => $deadlineAt,
            'requires_action' => $requiresAction,
        ]);

        /*
         | ⭐ عمود «بريد» في نفس المصفوفة — يعمل فعليًّا لا يُعرَض وحده (24.3).
         | نفس محرّك بريد منشورات التعليمات (`AnnouncementMail`) لا Mailable
         | موازٍ لكلّ نوع (12.14) — وفشل بريد واحد لا يُسقِط الإشعار نفسه.
         */
        if (self::matrixGoverned($category) && self::matrixAllows($category, 'email') && $user->email) {
            try {
                Mail::to($user->email)->send(new AnnouncementMail($title, $title, (string) $body, null, $url));
            } catch (Throwable) {
                // بريدٌ فشل لا يُسقِط الإشعار نفسه — السجلّ الدائم أهمّ (2.8)
            }
        }

        return $notification;
    }

    /** هل هذا النوع من الأنواع الستّة المحكومة بمصفوفة 24.3؟ */
    private static function matrixGoverned(string $category): bool
    {
        return array_key_exists($category, setting('notifications.types', [
            'account' => '', 'certificate' => '', 'exam' => '',
            'announcement' => '', 'wallet' => '', 'order' => '',
        ]));
    }

    /** قراءة خليّة المصفوفة — الجرس مفتوحٌ افتراضيًّا وحده (24.3). */
    private static function matrixAllows(string $category, string $channel): bool
    {
        return (bool) setting('notifications.matrix.'.$category.'.'.$channel, $channel === 'bell');
    }

    /**
     * حدّ الهدوء اليوميّ: كم إشعارًا وصل هذا المستخدم اليوم؟ (12.6-ب)
     *
     * الفئات المستثناة تمرّ دائمًا — لأنّ تأجيل «حسابك اتقبل» أو «شهادتك صدرت»
     * ضررُه أكبر من نفعه، وحدّ الهدوء وُضِع ضدّ الإغراق لا ضدّ اللحظات الفارقة.
     */
    public static function overQuietLimit(User $user, string $category): bool
    {
        $limit = (int) setting('notifications.rate_limit.per_user_per_day', 3);

        if ($limit <= 0 || in_array($category, self::quietExemptCategories(), true)) {
            return false;
        }

        return $user->notificationsFeed()
            ->where('created_at', '>=', now()->startOfDay())
            ->where('category', '!=', self::digestCategory())
            ->count() >= $limit;
    }

    /** @return array<int, string> */
    public static function quietExemptCategories(): array
    {
        $exempt = setting('notifications.rate_limit.exempt_categories', ['account', 'security', 'certificate']);

        return is_array($exempt) ? array_values($exempt) : [];
    }

    /**
     * نفس الإشعار لمجموعة مستخدمين (بثّ) — يُستعمَل مع منشورات التعليمات
     * التي اختار الأدمن إظهارها في الإشعارات.
     *
     * @param  iterable<User>  $users
     * @return Collection<int, AppNotification>
     */
    public static function sendMany(
        iterable $users,
        string $category,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $layer = 'platform',
        ?Carbon $deadlineAt = null,
        bool $requiresAction = false,
    ): Collection {
        $sent = collect();

        foreach ($users as $user) {
            $sent->push(self::send($user, $category, $title, $body, $url, $layer, $deadlineAt, $requiresAction));
        }

        return $sent;
    }

    /** ربط الإشعار بسجلّه الأصليّ (مهمّة/طلب/شهادة) ليفتح المستخدم مصدره مباشرةً. */
    public static function about(AppNotification $notification, Model $reference, ?int $entityId = null): AppNotification
    {
        $notification->forceFill([
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'entity_id' => $entityId,
        ])->save();

        return $notification;
    }

    /** عدّاد غير المقروء — للجرس وللتابات (الكلّ · المنصّة · التطوّع). */
    public static function unreadCount(User $user, ?string $layer = null): int
    {
        return $user->notificationsFeed()
            ->whereNull('read_at')
            ->when($layer && $layer !== 'all', fn ($q) => $q->where('layer', $layer))
            ->count();
    }

    /** تعليم إشعار واحد كمقروء — والملكيّة تُتحقَّق في الكنترولر. */
    public static function markRead(AppNotification $notification): void
    {
        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    /** تعليم الكلّ كمقروء — داخل التاب المفتوح فقط حتى لا يُفاجَأ المستخدم. */
    public static function markAllRead(User $user, ?string $layer = null): int
    {
        return $user->notificationsFeed()
            ->whereNull('read_at')
            ->when($layer && $layer !== 'all', fn ($q) => $q->where('layer', $layer))
            ->update(['read_at' => now()]);
    }

    /**
     * حالة المهلة بلون ومعنًى واحد (2.16): فات = خطر · قرب = انتبه · متّسع = سليم.
     * وترجع null لو الإشعار بلا مهلة أصلًا.
     */
    public static function deadlineState(?Carbon $deadlineAt): ?string
    {
        if (! $deadlineAt) {
            return null;
        }

        if ($deadlineAt->isPast()) {
            return 'danger';
        }

        $soonHours = (float) setting('notifications.deadline.soon_hours', 24);

        return now()->diffInHours($deadlineAt, absolute: true) <= $soonHours ? 'warn' : 'ok';
    }

    /**
     * أيقونة الفئة من قاموس مقفول قابل للتحرير من لوحة الإدارة (2.16-ج · 2.13).
     * وأيّ فئة جديدة تعمل فورًا بأيقونة افتراضيّة بلا تعديل كود.
     */
    public static function categoryIcon(string $category): string
    {
        $icons = setting('notifications.category.icons', []);

        if (! is_array($icons)) {
            $icons = [];
        }

        return $icons[$category] ?? (string) setting('notifications.category.default_icon', '🔔');
    }

    /** الطبقات المسموحة — إعداد لا قائمة محروقة (2.13). */
    public static function layers(): array
    {
        $layers = setting('notifications.layers.allowed', ['platform', 'volunteer']);

        return is_array($layers) && $layers !== [] ? array_values($layers) : ['platform', 'volunteer'];
    }

    /**
     * فئات إشعارات التطوّع العشر (13 · قرار §25): مفتاح الفلتر ⟵ لافتته
     * العربيّة — إعداد قابل للتعديل (2.13) لا قائمة محروقة.
     *
     * @return array<string, string>
     */
    public static function categoryBuckets(): array
    {
        return [
            'tasks' => (string) setting('notifications.category.bucket_label_tasks', 'مهامّ'),
            'contributions' => (string) setting('notifications.category.bucket_label_contributions', 'مساهمات ونقاط تفتيش'),
            'decisions' => (string) setting('notifications.category.bucket_label_decisions', 'نوافذ قرار'),
            'meetings' => (string) setting('notifications.category.bucket_label_meetings', 'اجتماعات'),
            'transactions' => (string) setting('notifications.category.bucket_label_transactions', 'معاملات واعتراضات'),
            'escalations' => (string) setting('notifications.category.bucket_label_escalations', 'تصعيدات وتحكيمات'),
            'academy' => (string) setting('notifications.category.bucket_label_academy', 'أكاديمية وتسجيلات'),
            'recognition' => (string) setting('notifications.category.bucket_label_recognition', 'تقدير'),
            'structure' => (string) setting('notifications.category.bucket_label_structure', 'هيكل وترقيات'),
            'recruitment' => (string) setting('notifications.category.bucket_label_recruitment', 'توظيف'),
        ];
    }

    /**
     * قيمة `category` الخام في الصفّ ⟵ مفتاح إحدى الفئات العشر — تُبنى بادئةً
     * لا مطابقةً حرفيّة (`meeting.attendance_registered` تقع تحت `meetings`).
     * وترجع `null` لفئةٍ لا تخصّ طبقة التطوّع (شهادة/شحن/شكوى... إلخ) — فلا
     * تظهر أصلًا في فلتر شاشة إشعارات التطوّع.
     */
    public static function categoryBucket(string $category): ?string
    {
        $map = setting('notifications.category.bucket_map', []);
        $map = is_array($map) && $map !== [] ? $map : self::defaultCategoryMap();

        if (array_key_exists($category, $map)) {
            return $map[$category];
        }

        foreach ($map as $prefix => $bucket) {
            if (str_ends_with($prefix, '.') && str_starts_with($category, $prefix)) {
                return $bucket;
            }
        }

        return null;
    }

    /**
     * تضييق الاستعلام لفئةٍ من الفئات العشر — يطابق القيم الحرفيّة والبادئات
     * معًا (`meeting.` تطابق `meeting.attendance_registered`)، عكس
     * `categoryBucket()` تمامًا حتى لا يفترق الفلتر عن العرض.
     */
    public static function scopeToCategory(mixed $query, string $bucket): void
    {
        $map = setting('notifications.category.bucket_map', []);
        $map = is_array($map) && $map !== [] ? $map : self::defaultCategoryMap();

        $exact = [];
        $prefixes = [];

        foreach ($map as $key => $b) {
            if ($b !== $bucket) {
                continue;
            }

            str_ends_with($key, '.') ? $prefixes[] = $key : $exact[] = $key;
        }

        $query->where(function ($q) use ($exact, $prefixes) {
            if ($exact !== []) {
                $q->whereIn('category', $exact);
            }

            foreach ($prefixes as $prefix) {
                $q->orWhere('category', 'like', $prefix.'%');
            }
        });
    }

    /**
     * الخريطة الافتراضيّة — من الفئات الحقيقيّة التي يكتبها كود المجالات
     * فعليًّا (لا افتراضًا نظريًّا)؛ راجع `app/Services/Volunteer/**` لمصدر كلّ قيمة.
     *
     * @return array<string, string>
     */
    private static function defaultCategoryMap(): array
    {
        return [
            'task' => 'tasks',
            'task_delivered' => 'tasks',
            'task_extension' => 'tasks',
            'task_apology' => 'tasks',
            'task_flag' => 'tasks',
            'task_blocked' => 'tasks',
            'contribution' => 'contributions',
            'goal' => 'decisions',
            'meeting.' => 'meetings',
            'objection' => 'transactions',
            'consent' => 'transactions',
            'escalation' => 'escalations',
            'arbitration' => 'escalations',
            'academy' => 'academy',
            'recognition' => 'recognition',
            // ⚠️ حكم اجتهاديّ: تنبيهات الحساب (إنذار/تعليق/عودة) أقرب لموضوعها
            // إلى مسار العضو التنظيميّ منها إلى بقيّة الفئات — لا تصنيف أدقّ متاح اليوم
            'account' => 'structure',
            'volunteer' => 'structure',
            'recruitment' => 'recruitment',
        ];
    }

    // ---------------------------------------------- التجميع وحدّ الهدوء (12.6-ب)

    /** مفتاح التجميع: الفئة + العنوان — «المتشابه» بمعناه الحرفيّ لا التقريبيّ. */
    private static function groupKey(string $category, string $title): string
    {
        return mb_substr($category.'|'.$title, 0, 190);
    }

    private static function digestCategory(): string
    {
        return (string) setting('notifications.digest.category', 'digest');
    }

    /** صفٌّ قائم يصلح للدمج: نفس المفتاح · غير مقروء · داخل نافذة التجميع. */
    private static function mergeable(User $user, string $layer, string $category, string $title): ?AppNotification
    {
        if (! setting('notifications.grouping.enabled', true)) {
            return null;
        }

        $window = (int) setting('notifications.grouping.window_minutes', 15);

        if ($window <= 0) {
            return null;
        }

        return AppNotification::query()
            ->where('user_id', $user->id)
            ->where('layer', $layer)
            ->where('category', $category)
            ->where('group_key', self::groupKey($category, $title))
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subMinutes($window))
            ->latest('id')
            ->first();
    }

    private static function merge(
        AppNotification $existing,
        ?string $body,
        ?string $url,
        ?Carbon $deadlineAt,
        bool $requiresAction,
    ): AppNotification {
        $existing->forceFill([
            'group_count' => (int) $existing->group_count + 1,
            // الأحدث هو ما يهمّ المستخدم الآن، والعدّاد يحكي البقيّة
            'body' => $body ?? $existing->body,
            'url' => $url ?? $existing->url,
            'deadline_at' => $deadlineAt ?? $existing->deadline_at,
            'requires_action' => $requiresAction || (bool) $existing->requires_action,
        ])->save();

        return $existing;
    }

    /**
     * ما زاد عن حدّ الهدوء يتجمّع في إشعارٍ واحد لليوم بعدّادٍ يزيد —
     * فيعرف المستخدم أنّ عنده جديدًا بلا أن ينهال عليه (12.6-ب).
     */
    private static function digest(User $user, string $layer, string $title): AppNotification
    {
        $category = self::digestCategory();
        $key = $category.'|'.now()->toDateString();

        $existing = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->where('group_key', $key)
            ->whereNull('read_at')
            ->latest('id')
            ->first();

        $count = ((int) ($existing->group_count ?? 0)) + 1;

        $payload = [
            'title' => str_replace(
                ':count',
                (string) $count,
                (string) setting('notifications.digest.title', 'عندك :count تنبيهات جديدة'),
            ),
            'body' => $title,
            'group_count' => $count,
            'url' => Route::has('notifications.index') ? route('notifications.index') : null,
        ];

        if ($existing) {
            $existing->forceFill($payload)->save();

            return $existing;
        }

        return AppNotification::create($payload + [
            'user_id' => $user->id,
            'layer' => $layer,
            'category' => $category,
            'group_key' => $key,
        ]);
    }

    private static function normalizeLayer(string $layer): string
    {
        if (! in_array($layer, self::layers(), true)) {
            throw new InvalidArgumentException(strtr(setting('notifications.notifier.normalize_layer_1', 'طبقة إشعار غير معروفة: :p1'), [':p1' => (string) ($layer)]));
        }

        return $layer;
    }
}
