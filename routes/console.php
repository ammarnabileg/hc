<?php

use App\Services\Admin\Ops\BackupManager;
use App\Services\Admin\Ops\OpsSettings;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| جدولة المهامّ الدوريّة
|--------------------------------------------------------------------------
| ملفّ مشترك — الأوامر نفسها يملكها أصحاب المجالات، والجدولة هنا.
*/

// محرّك التصعيد (23-8): يعالج النوافذ الفائتة ويطبّق التسويات الآليّة التسع،
// والاعتماد التلقائيّ للمساهم، ونقاط التفتيش والديدلاينات الداخليّة الفائتة.
Schedule::command('escalations:run')->everyFiveMinutes()->withoutOverlapping();

// تصفير درجة الالتزام شهريًّا (13.4-ن-ز): **مسحة كلّ ساعة والقرار داخل
// `RepService::isResetMoment()` وحده** — على غرار جدولة النسخ الاحتياطيّ تحت.
// لماذا لا `monthlyOn(1, '05:00')`؟ لأنّ تعبير الكرون يُقرأ مرّةً عند تحميل هذا
// الملفّ، فيبقى `0 5 1 * *` بعد أن يغيّر الأدمن اليوم والساعة من الإعدادات؛
// والأمر بلا `--force` يرفض خارج الموعد المضبوط — فلا يلتقيان أبدًا ولا يحدث
// التصفير في أيّ لحظة، بلا خطأ ولا سجلّ. والفشل الصامت هنا يهدم قاعدة «فرصة
// جديدة كلّ شهر» ومعها نادي +9.5 والسباق الشهريّ.
// والرقم الظاهر وحده يتصفّر، أمّا سجلّ المعاملات والمكتسَب التراكميّ فيبقيان.
Schedule::command('rep:reset-monthly')->hourly()->withoutOverlapping();

// التعليق المؤقّت ينتهي وحده (12.1-متقدّم-3): مسحة كلّ ساعة تصحّح السجلّ حتى لو
// ماكانش صاحب الحساب فتح المنصّة — والجدار نفسه يرفعه عند أوّل طلبٍ منه على أيّ حال.
Schedule::command('moderation:release-expired')->hourly()->withoutOverlapping();

// توليد المهامّ من البنود المتكرّرة في المشروع التشغيليّ (23)
Schedule::command('recurring:generate')->hourly()->withoutOverlapping();

// سلّم الخمول وعتبات لجنة التحقيق — مسحة يوميّة (13.4-س-ب · 13.4-س-ج)
Schedule::command('volunteers:inactivity')->dailyAt('05:30')->timezone('Africa/Cairo')->withoutOverlapping();

// النسخة الاحتياطيّة المجدولة (2.11 · 12.7): المسحة كلّ ساعة، والقرار داخل
// `isScheduleDue()` وحده — فالدوريّة والساعة إعدادان يحرّرهما الأدمن لا رقمٌ هنا.
// والجدولة تسجّل آخر تشغيل لتقرأه لوحة صحّة النظام.
Schedule::call(function () {
    $backups = app(BackupManager::class);

    if ($backups->isScheduleDue()) {
        $backups->create((string) setting('backups.schedule.kind', 'full'), null, scheduled: true);
    }

    app(OpsSettings::class)
        ->put('system.schedule.last_run_at', now()->toDateTimeString());
})->hourly()->name('backups:scheduled')->withoutOverlapping();

// التقارير المجدولة (24.3-خامسًا): مسحة كلّ ساعة تلتقط المستحقّ بساعته
// ومنطقته الزمنيّة — والساعة أصغر وحدة تسمح بها شاشة الجدولة، فلا حاجة لأدقّ.
Schedule::command('reports:dispatch')->hourly()->withoutOverlapping();
