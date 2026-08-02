<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

        return AppNotification::create([
            'user_id' => $user->id,
            'layer' => $layer,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'deadline_at' => $deadlineAt,
            'requires_action' => $requiresAction,
        ]);
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

    private static function normalizeLayer(string $layer): string
    {
        if (! in_array($layer, self::layers(), true)) {
            throw new InvalidArgumentException("طبقة إشعار غير معروفة: {$layer}");
        }

        return $layer;
    }
}
