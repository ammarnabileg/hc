<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

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

        return AppNotification::create([
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
