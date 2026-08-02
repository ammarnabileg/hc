<?php

use App\Models\RepRule;
use App\Models\Setting;
use App\Models\SettingOverride;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * قراءة إعداد من لوحة الإدارة (القاعدة الذهبيّة 2.13 — No Hard-coding).
     * ولا يُكتَب رقمٌ ولا نصٌّ ثابت في الكود بدل هذا.
     *
     * @param  int|null  $entityId  لقراءة Override الكيان (2.13-هـ)
     */
    function setting(string $key, mixed $default = null, ?int $entityId = null): mixed
    {
        $value = Cache::rememberForever('settings', function () {
            return Setting::query()->pluck('value', 'key')->all();
        })[$key] ?? null;

        if ($entityId) {
            $override = SettingOverride::query()
                ->whereHas('setting', fn ($q) => $q->where('key', $key))
                ->where('entity_id', $entityId)
                ->value('value');

            if ($override !== null) {
                $value = $override;
            }
        }

        if ($value === null) {
            return $default;
        }

        return match (true) {
            $value === '1' || $value === 'true' => true,
            $value === '0' || $value === 'false' => false,
            is_numeric($value) => $value + 0,
            str_starts_with((string) $value, '{') || str_starts_with((string) $value, '[') => json_decode($value, true) ?? $value,
            default => $value,
        };
    }
}

if (! function_exists('rep_rule')) {
    /** قيمة من جدول Rep الموحَّد (13.4-ن) — كلّها إعدادات لا أرقام محروقة */
    function rep_rule(string $key, float $default = 0): float
    {
        $values = Cache::rememberForever('rep_rules', function () {
            return RepRule::query()->pluck('value', 'key')->all();
        });

        return (float) ($values[$key] ?? $default);
    }
}

if (! function_exists('state_color')) {
    /**
     * قاموس الحالة (2.16): معنًى واحد لكلّ لون في المنصّة كلّها،
     * ومعه رمزٌ دائمًا لأنّ اللون وحده لا يحمل المعنى.
     *
     * @return array{color:string,icon:string,label:string}
     */
    function state_color(string $state): array
    {
        return match ($state) {
            'ok', 'green', 'valid', 'approved', 'completed', 'active' => [
                'color' => 'ok', 'icon' => '●', 'label' => 'سليم',
            ],
            'warn', 'yellow', 'pending', 'in_review', 'soon' => [
                'color' => 'warn', 'icon' => '▲', 'label' => 'انتبه',
            ],
            'danger', 'red', 'late', 'rejected', 'failed', 'overdue' => [
                'color' => 'danger', 'icon' => '◉', 'label' => 'خطر',
            ],
            'honor', 'gold' => [
                'color' => 'honor', 'icon' => '★', 'label' => 'تميّز',
            ],
            default => [
                'color' => 'idle', 'icon' => '○', 'label' => 'غير نشط',
            ],
        };
    }
}
