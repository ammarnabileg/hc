<?php

namespace App\Services\Account;

use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\ConsentRequest;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserPrivacySetting;

/**
 * [تحميل بياناتي] (الدستور 24.5 · 21.3): ملفّ JSON يوضّح ما تحتفظ به المنصّة
 * عن صاحب الحساب — حقٌّ يُمارَس بضغطة، لا طلبٌ يُنتظَر.
 */
class AccountDataExport
{
    public function for(User $user): array
    {
        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'platform' => config('app.name'),
                'note' => 'الملفّ ده بياناتك أنت وحدك، وتقدر تحمّله وقت ما تحبّ.',
            ],
            'account' => [
                'code' => $user->code,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country?->name_ar,
                'governorate' => $user->governorate?->name_ar,
                'birthdate' => $user->birthdate?->toDateString(),
                'gender' => $user->gender,
                'status' => $user->status,
                'level' => $user->level,
                'xp' => $user->xp,
                'joined_at' => $user->created_at?->toIso8601String(),
            ],
            'preferences' => [
                'locale' => $user->locale,
                'theme' => $user->theme,
                'sound_enabled' => (bool) $user->sound_enabled,
                'simple_mode' => (bool) $user->simple_mode,
                'advanced_mode' => (bool) $user->advanced_mode,
            ],
            'privacy_settings' => UserPrivacySetting::where('user_id', $user->id)
                ->get(['field', 'visibility'])
                ->map(fn ($row) => ['field' => $row->field, 'visibility' => $row->visibility])
                ->all(),
            // ⭐ المحافظة عامّة دائمًا ولا تخضع للخصوصيّة (12.14-د) — يُصرَّح بها هنا
            'always_public_fields' => PrivacyFields::ALWAYS_PUBLIC,
            'emergency_contacts' => EmergencyContact::where('user_id', $user->id)
                ->get(['name', 'phone', 'relation'])
                ->map(fn ($c) => ['name' => $c->name, 'phone' => $c->phone, 'relation' => $c->relation])
                ->all(),
            'who_sees_my_data' => ConsentRequest::with('requester:id,name,code')
                ->where('owner_id', $user->id)
                ->where('status', 'granted')
                ->whereNull('revoked_at')
                ->get()
                ->map(fn ($c) => [
                    'person' => $c->requester?->name,
                    'code' => $c->requester?->code,
                    'field' => $c->field,
                    'expires_at' => $c->consent_expires_at?->toIso8601String(),
                ])->all(),
            'devices' => UserDevice::where('user_id', $user->id)
                ->get(['device_label', 'ip', 'last_active_at'])
                ->map(fn ($d) => [
                    'device' => $d->device_label,
                    'ip' => $d->ip,
                    'last_active_at' => $d->last_active_at?->toIso8601String(),
                ])->all(),
            'certificates' => Certificate::where('user_id', $user->id)
                ->get(['code', 'status', 'issued_at'])
                ->map(fn ($c) => [
                    'code' => $c->code,
                    'status' => $c->status,
                    'issued_at' => $c->issued_at?->toIso8601String(),
                ])->all(),
            'complaints' => Complaint::where('user_id', $user->id)
                ->get(['number', 'type', 'title', 'status', 'created_at'])
                ->map(fn ($c) => [
                    'number' => $c->number,
                    'type' => $c->type,
                    'title' => $c->title,
                    'status' => $c->status,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    public function filename(User $user): string
    {
        $prefix = (string) setting('account.export.filename_prefix', 'my-data');

        return $prefix.'-'.$user->code.'-'.now()->format('Y-m-d').'.json';
    }
}
