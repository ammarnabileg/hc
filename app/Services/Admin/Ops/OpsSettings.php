<?php

namespace App\Services\Admin\Ops;

use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * كتابة الإعدادات من شاشات النظام (2.13).
 *
 * لماذا مررنا على `SettingsRegistry` بدل الكتابة المباشرة؟ لأنّ قواعد الإعدادات
 * (النطاق الرقميّ · عزل الحسّاس · Audit · تفريغ الكاش) قاعدةٌ واحدة في المنصّة،
 * ولو كتبت كلّ شاشة بطريقتها اختلف السلوك من مكان لمكان — وهو أخطر ما يصيب لوحةَ إعدادات.
 */
class OpsSettings
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /** حفظ إعداد يملكه الأدمن — يرجع رسالة الحفظ أو سبب الرفض */
    public function save(string $key, mixed $value, User $actor): array
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return ['saved' => false, 'message' => setting('updates.ops_settings.save_1', 'الإعداد ده مش موجود.')];
        }

        return $this->registry->save($setting, $value, $actor);
    }

    /**
     * قيمة تشغيليّة يكتبها النظام نفسه لا الأدمن (آخر تشغيل الجدولة · تبريد التنبيهات)
     * — بلا Audit لأنّ سجلّ التدقيق لِما يفعله البشر، لا لضجيج المؤقّتات.
     */
    public function put(string $key, mixed $value): void
    {
        $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        $affected = DB::table('settings')->where('key', $key)->update([
            'value' => $value,
            'updated_at' => now(),
        ]);

        if ($affected === 0) {
            return;
        }

        Cache::forget('settings');
    }
}
