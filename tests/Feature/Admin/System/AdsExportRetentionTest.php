<?php

namespace Tests\Feature\Admin\System;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * ⭐ فجوة: تصديرات شرائح الإعلان (CSV) في exports/audiences/ كانت تتراكم بلا
 * مدّة حفظٍ ولا أمر تنظيف — `AdsController::export()` يكتب الملفّ ولا شيء
 * يحذفه لاحقًا. الآن مسحة `ads:prune-exports` الساعيّة في routes/console.php
 * تحذف كلّ ملفٍّ أقدم من `ads.exports.retention_days` (افتراضيًّا 30 يومًا)
 * عبر طبقة التخزين نفسها المستعملة في الكتابة — على غرار `segments:refresh-dynamic`.
 */
class AdsExportRetentionTest extends SystemTestCase
{
    /** يجد المهمّة المسجَّلة فعلًا في routes/console.php لا وعدًا في التوثيق فقط */
    private function event(): Event
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => $e->description === 'ads:prune-exports');

        $this->assertNotNull($event, 'ads:prune-exports مجدولة في routes/console.php');

        return $event;
    }

    public function test_export_file_older_than_retention_is_deleted_and_recent_one_survives(): void
    {
        Storage::fake('local');

        // الإعداد نفسه يُستشار — لا رقمٌ محروق داخل المهمّة
        Setting::query()->where('key', 'ads.exports.retention_days')->update(['value' => '30']);
        Cache::forget('settings');

        $old = 'exports/audiences/1-'.now()->subDays(40)->format('Ymd-His').'.csv';
        $recent = 'exports/audiences/2-'.now()->subDays(5)->format('Ymd-His').'.csv';

        Storage::disk('local')->put($old, "email_sha256,phone_sha256\n");
        Storage::disk('local')->put($recent, "email_sha256,phone_sha256\n");

        // نُرجع mtime الملفّ القديم فعليًّا إلى الماضي — الحذف يقرأ Storage::lastModified()
        touch(Storage::disk('local')->path($old), now()->subDays(40)->timestamp);
        touch(Storage::disk('local')->path($recent), now()->subDays(5)->timestamp);

        $this->event()->run($this->app);

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($recent);
    }

    /** حدّ الحفظ إعدادٌ يحرّره الأدمن لا رقمٌ ثابت — تغييره يغيّر ما يُحذَف فعليًّا */
    public function test_retention_setting_controls_what_gets_pruned(): void
    {
        Storage::fake('local');

        Setting::query()->where('key', 'ads.exports.retention_days')->update(['value' => '90']);
        Cache::forget('settings');

        $file = 'exports/audiences/3-'.now()->subDays(40)->format('Ymd-His').'.csv';
        Storage::disk('local')->put($file, "email_sha256,phone_sha256\n");
        touch(Storage::disk('local')->path($file), now()->subDays(40)->timestamp);

        $this->event()->run($this->app);

        // 40 يومًا أقل من حدّ 90 يومًا المضبوط ⟵ الملفّ يبقى
        Storage::disk('local')->assertExists($file);
    }
}
