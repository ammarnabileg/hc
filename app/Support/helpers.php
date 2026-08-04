<?php

use App\Models\RepRule;
use App\Models\Setting;
use App\Models\SettingOverride;
use App\Models\User;
use App\Services\Features\FeatureGate;
use App\Services\Ux\ViewMode;
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
        $rows = Cache::rememberForever('settings', function () {
            return Setting::query()
                ->get(['key', 'value', 'type'])
                ->mapWithKeys(fn ($row) => [$row->key => ['value' => $row->value, 'type' => $row->type]])
                ->all();
        });

        $value = $rows[$key]['value'] ?? null;
        $type = $rows[$key]['type'] ?? null;

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

        /*
         | النوع المعلَن في الجدول هو الحاكم — لا شكل النصّ المخزَّن.
         | قبل ذلك كان كلّ إعدادٍ قيمته «0» أو «1» يعود **بوليان**، فتذكرةٌ واحدة
         | تعود `true` وصفرُ تذاكر يعود `false`. والنظام كان يعمل بالمصادفة وحدها
         | لأنّ `(int) true === 1` — وأوّل شاشة تعرض القيمة أو تقارنها بـ`===`
         | تنكسر: ضبطُ «تذاكر ما بعد نصف المهلة = 0» كان يُفرِغ الرقم من الشاشة.
         | ومعظم أرقام الاقتصاد في الدستور تقع في 0 و1 و2.
         */
        return match ($type) {
            'number' => is_numeric($value) ? $value + 0 : $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $value, true) ?? $value,
            'string', 'text', 'color', 'media' => (string) $value,
            // بلا نوع معلَن (إعداد قديم أو مُنشَأ خارج الكتالوج) — استنتاجٌ محافظ
            default => match (true) {
                $value === 'true' => true,
                $value === 'false' => false,
                is_numeric($value) => $value + 0,
                str_starts_with((string) $value, '{') || str_starts_with((string) $value, '[') => json_decode($value, true) ?? $value,
                default => $value,
            },
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

if (! function_exists('advanced_mode')) {
    /**
     * هل الصفحة تُعرَض بالوضع المتقدّم للمستخدم الحاليّ؟ (2.15-أ-9)
     * قارئٌ واحد للعَلَم في الواجهة كلّها — فلا تفترق شاشةٌ عن أخرى.
     */
    function advanced_mode(?User $user = null): bool
    {
        return app(ViewMode::class)->isAdvanced($user ?? auth()->user());
    }
}

if (! function_exists('view_mode')) {
    /** خدمة وضعَي «مبسّط/متقدّم» وحدودهما من الإعدادات (2.15) */
    function view_mode(): ViewMode
    {
        return app(ViewMode::class);
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
        // ⭐ الرمز والتسمية إعدادان (`ux.state.*`) لا نصّان محروقان (2.13):
        // «انتبه» في شاشة و«تحت المراجعة» في أخرى تفقد اللونَ معناه الواحد،
        // فمن هنا يضبط المالك القاموسَ مرّةً واحدة للمنصّة كلّها.
        $color = match ($state) {
            'ok', 'green', 'valid', 'approved', 'completed', 'active' => 'ok',
            'warn', 'yellow', 'pending', 'in_review', 'soon' => 'warn',
            'danger', 'red', 'late', 'rejected', 'failed', 'overdue' => 'danger',
            'honor', 'gold' => 'honor',
            default => 'idle',
        };

        /*
         | المرساة: قيم الدستور نفسها، فلو غابت الإعدادات (تنصيب جديد) لا تنكسر
         | الشاشة. وهي **داخل `setting()`** لا في مصفوفةٍ فوقها: النصّ في مصفوفةٍ
         | منفصلة نصٌّ محروق ولو كان مآله وسيطًا افتراضيًّا (2.13-ب).
         */
        $icon = (string) match ($color) {
            'ok' => setting('ux.state.ok.icon', '●'),
            'warn' => setting('ux.state.warn.icon', '▲'),
            'danger' => setting('ux.state.danger.icon', '◉'),
            'honor' => setting('ux.state.honor.icon', '★'),
            default => setting('ux.state.idle.icon', '○'),
        };

        $label = (string) match ($color) {
            'ok' => setting('ux.state.ok.label', 'سليم'),
            'warn' => setting('ux.state.warn.label', 'انتبه'),
            'danger' => setting('ux.state.danger.label', 'خطر'),
            'honor' => setting('ux.state.honor.label', 'تميّز'),
            default => setting('ux.state.idle.label', 'غير نشط'),
        };

        return ['color' => $color, 'icon' => $icon, 'label' => $label];
    }
}

if (! function_exists('feature')) {
    /**
     * ⭐ **مفاتيح المزايا (24.3)** — القارئ الواحد الذي تستهلكه المنصّة كلّها.
     *
     * «إطفاء/تشغيل أيّ ميزة في المنصّة بلا نشر كود — **البديل الوحيد للصيانة
     * الجزئيّة الملغاة** (12.7-و)». فلا فحوصُ «مفتاحُ تفعيلٍ في الإعدادات» متناثرة في
     * القوالب: قارئٌ واحد يعرف الـOverride ويعرف «مَن يراها أثناء الإيقاف».
     */
    function feature(string $key, ?User $user = null): bool
    {
        return app(FeatureGate::class)->allows($key, $user ?? auth()->user());
    }
}

if (! function_exists('feature_state')) {
    /**
     * الحالة الكاملة للميزة (سلوكها ونصّها ونطاقها) — لمن يحتاج أكثر من نعم/لا.
     *
     * @return array{known:bool,allowed:bool,enabled:bool,scope:string,exempt:bool,behavior:string,message:string,flag:array<string,mixed>|null}
     */
    function feature_state(string $key, ?User $user = null): array
    {
        return app(FeatureGate::class)->state($key, $user ?? auth()->user());
    }
}
