<?php

namespace App\Services\Volunteer\Escalation;

use App\Models\User;
use App\Services\Notifications\Notifier;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * غلاف آمن حول بوّابة الإشعارات (نقطة تكامل).
 *
 * كلّ إشعارات دورة العمل تحمل **مهلةً** تُعرَض عدّادًا ملوّنًا (2.16)،
 * لأنّ كلّ ما في هذا المجال محكوم بنافذة زمنيّة.
 */
class FlowNotifier
{
    public static function available(): bool
    {
        return class_exists(Notifier::class);
    }

    public static function send(
        ?User $user,
        string $category,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?CarbonInterface $deadlineAt = null,
        bool $requiresAction = false,
        ?Model $about = null,
    ): void {
        if (! $user || ! self::available()) {
            return;
        }

        $notification = Notifier::send(
            user: $user,
            category: $category,
            title: $title,
            body: $body,
            url: $url,
            layer: 'volunteer',
            deadlineAt: $deadlineAt ? Carbon::parse($deadlineAt) : null,
            requiresAction: $requiresAction,
        );

        if ($about) {
            Notifier::about($notification, $about);
        }
    }
}
