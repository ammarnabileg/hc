<?php

namespace App\Services\Security;

use App\Models\AppNotification;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ صمّام أمان ضدّ «موقع مقفول ومنسيّ» (12.7-و-1).
 *
 * لمّا يوصل العدّاد صفرًا والصيانة ما زالت شغّالة، يصل **تنبيه فوريّ** لمن يديرون
 * الصيانة ليختاروا: **تمديد بضغطة (+1 / +3 / مخصّص)** أو **رفع الصيانة**.
 * والتنبيه **مرّة واحدة لكلّ فترة** حتى لا يتحوّل لضجيج يُتجاهَل.
 */
class MaintenanceAlert
{
    public const CATEGORY = 'maintenance.overrun';

    public function overrun(MaintenanceWindow $window): void
    {
        if ($this->alreadySent($window)) {
            return;
        }

        $title = (string) setting('system.maintenance.alert_title', 'الصيانة عدّت المدّة المعلَنة');
        $body = str_replace(
            '{hours}',
            (string) (int) $window->planned_hours,
            (string) setting(
                'system.maintenance.alert_body',
                'العدّاد وصل صفر والمنصّة لسّه مقفولة، والمستخدم بيشوف «قرّبنا ننتهي». مدّد المدّة أو ارفع الصيانة.',
            ),
        );

        foreach ($this->managers() as $manager) {
            $notification = Notifier::send(
                user: $manager,
                category: self::CATEGORY,
                title: $title,
                body: $body,
                url: route('admin.settings.index', ['tab' => 'maintenance']),
                requiresAction: true,
            );

            Notifier::about($notification, $window);
        }
    }

    /** مَن يملك رفع الصيانة أو تمديدها: مالك المنصّة + كلّ مَن له `maintenance.manage` */
    public function managers(): Collection
    {
        $viaRole = DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('permission_role.effect', 'allow')
            ->where(fn ($q) => $q
                ->where('permissions.key', 'maintenance.manage')
                ->orWhere('roles.key', config('access.owner_role')))
            ->pluck('role_user.user_id');

        $viaUser = DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permissions.key', 'maintenance.manage')
            ->where('permission_user.effect', 'allow')
            ->pluck('permission_user.user_id');

        return User::query()
            ->whereIn('id', $viaRole->merge($viaUser)->unique()->all())
            ->get();
    }

    private function alreadySent(MaintenanceWindow $window): bool
    {
        return AppNotification::query()
            ->where('category', self::CATEGORY)
            ->where('reference_type', $window->getMorphClass())
            ->where('reference_id', $window->getKey())
            ->exists();
    }
}
