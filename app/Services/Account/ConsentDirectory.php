<?php

namespace App\Services\Account;

use App\Models\ConsentRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * قائمة «مَن يرى بياناتي» (الدستور 13.4-م · 24.5).
 *
 * قاعدتان حاكمتان:
 *  1) القائمة **للموافقات الاختياريّة فقط** — الأبلاين الذي يرى البيانات
 *     باستثناءٍ نظاميّ لا يظهر فيها (حقّه نظاميّ لا موافقة تُسحَب).
 *  2) **السحب بلا إشعار للطرف الآخر** — نفس فلسفة الرفض الصامت.
 */
class ConsentDirectory
{
    /** @return Collection<int, ConsentRequest> */
    public function activeFor(User $owner): Collection
    {
        return ConsentRequest::query()
            ->with('requester:id,name,code,avatar_path')
            ->where('owner_id', $owner->id)
            ->where('status', 'granted')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('consent_expires_at')->orWhere('consent_expires_at', '>', now()))
            ->orderBy('consent_expires_at')
            ->get();
    }

    /** السحب يقطع الرؤية فورًا — وبلا أيّ إشعار للطرف الآخر (13.4-م) */
    public function revoke(ConsentRequest $consent): void
    {
        $consent->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }

    /** نسبة ما تبقّى من مدّة الموافقة — للبار الزمنيّ الصغير (13.4-م) */
    public function remainingPercent(ConsentRequest $consent): int
    {
        $start = $consent->granted_at ?? $consent->created_at;
        $end = $consent->consent_expires_at;

        if (! $start || ! $end) {
            return 100;
        }

        $total = $start->diffInSeconds($end);
        $left = now()->diffInSeconds($end, false);

        if ($total <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round($left / $total * 100)));
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'phone' => 'رقم الموبايل',
            'email' => 'البريد الإلكترونيّ',
            default => $field,
        };
    }

    /** مدّة صلاحيّة الموافقة الافتراضيّة — إعداد لا رقم محروق (13.4-م) */
    public static function durationDays(): int
    {
        return max(1, (int) setting('account.consent.duration_days', 30));
    }
}
