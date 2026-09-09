<?php

use App\Models\AdAudience;
use App\Services\Admin\AudienceSegments;
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

// دورات المنشورات المجدولة تكرارًا (12.6-أ): مسحة كلّ ساعة، والقرار داخل
// `AnnouncementRecurrence::due()` وحده — فالتردّد ومدّاه إعدادان يحرّرهما الأدمن
// لكلّ منشور، لا تعبير كرون ثابتٌ هنا. وبلا هذه الجدولة تبقى «الجدولة المتكرّرة»
// وعدًا في الشاشة بلا تنفيذ.
Schedule::command('announcements:recurring')->hourly()->withoutOverlapping();

// قناة البريد في «القنوات الموحّدة» (12.6-أ): تلتقط المستحقّ **والمؤجَّل بحدّ
// الهدوء** (12.6-ب) والمتعثّر. وبلا هذه الجدولة تبقى الرسالة المؤجَّلة مؤجَّلةً
// إلى الأبد: الحدّ يقول «تتأجّل» لا «تُلغى»، فبقاؤها بلا مُلتقِطٍ يجعل التأجيل
// إسقاطًا صامتًا. والأمر آمنٌ على التكرار بصفّ تسليمٍ لكلّ (منشور · مستخدم · قناة).
Schedule::command('announcements:deliver-emails')->hourly()->withoutOverlapping();

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

// تحديث عدّاد شرائح الجمهور الديناميكيّة (12.13): **مسحة كلّ ساعة والقرار داخل
// `AudienceSegments::refreshDue()` وحده** — على غرار `backups:scheduled` فوق،
// فالفترة إعدادٌ يحرّره الأدمن (`admin.segments.refresh_hours`) لا تعبير كرونٍ
// يتجمّد على القيمة القديمة. والعضويّة نفسها تبقى حيّة الحساب دومًا (12.13) —
// كلّ ما يحدّثه هذا هو عدّاد «عدد الأعضاء» و«آخر تحديث» الظاهرَين في القائمة،
// وبلا هذه الجدولة يبقيان محروقَين على لحظة آخر حفظٍ يدويّ فقط.
Schedule::call(function () {
    $segments = app(AudienceSegments::class);

    AdAudience::query()
        ->where('kind', AudienceSegments::KIND)
        ->where('segment_type', AudienceSegments::TYPE_DYNAMIC)
        ->whereNull('archived_at')
        ->each(function (AdAudience $segment) use ($segments) {
            if ($segments->refreshDue($segment)) {
                $segments->rebuild($segment);
            }
        });
})->hourly()->name('segments:refresh-dynamic')->withoutOverlapping();

// الفحص الدوريّ لمصدر الدول (12.7-د): **مسحة كلّ ساعة والقرار داخل
// `CountryDataSync::isCheckDue()` وحده** — على غرار `rep:reset-monthly` فوق.
// لماذا لا `monthlyOn(1, '04:00')`؟ لأنّ تعبير الكرون يُقرأ مرّةً عند تحميل هذا
// الملفّ، فيتجمّد على القيمة القديمة بعد أن يغيّر الأدمن الدوريّة واليوم من
// الإعدادات؛ والأمر بلا `--force` يرفض خارج الموعد المضبوط — فلا يلتقيان أبدًا
// ولا يقع فحصٌ في أيّ لحظة، بلا خطأ ولا سجلّ. والفشل الصامت هنا يعني مصدرَ دولٍ
// يتقادم بلا أن يدري أحد.
// ⛔ والفحص **يقف عند الفروق**: لا دمج آليّ — القرار للمالك من الشاشة.
Schedule::command('countries:check-source')->hourly()->withoutOverlapping();

// تذكيرات الفعاليّات (13.3 · 12.11 · 24.3): «تذكيرات مجدولة (قبل يوم/ساعة)».
// المسحة **كلّ خمس دقائق** لا كلّ ساعة: تذكير «قبل ساعة» بمسحةٍ ساعيّة قد يصل
// بعد أن تبدأ الفعاليّة فيفقد معناه كلّه. والقرار — أيّ موعدٍ حان ومَن يستقبل —
// داخل `ReminderScheduler` وحدها، فالمواعيد قائمةٌ يحرّرها الأدمن (2.13) لا
// تعبير كرون يُقرأ مرّةً عند تحميل هذا الملفّ فيتجمّد على القيمة القديمة.
// وبلا هذه الجدولة يبقى «التذكير المجدول» وعدًا في الشاشة بلا مُلتقِط — وهو
// إسقاطٌ صامت. والأمر آمنٌ على التكرار بصفٍّ فريد لكلّ (فعاليّة · مستخدم · موعد · قناة).
Schedule::command('events:remind')->everyFiveMinutes()->withoutOverlapping();

// التقارير المجدولة (24.3-خامسًا): مسحة كلّ ساعة تلتقط المستحقّ بساعته
// ومنطقته الزمنيّة — والساعة أصغر وحدة تسمح بها شاشة الجدولة، فلا حاجة لأدقّ.
Schedule::command('reports:dispatch')->hourly()->withoutOverlapping();

// 🧩 المطوّرين — API (12.15-أ ⭐): حدّ «آخر 100 سجلّ استخدامٍ لكلّ مفتاح»
// مفروضٌ فعليًّا لا وصفًا — مسحة كلّ ساعة تكفي؛ الجدول يكبر بمعدّل الطلبات
// الفعليّ لا بمعدّلٍ يستدعي أدقّ من ذلك.
Schedule::command('api:prune-request-logs')->hourly()->withoutOverlapping();
