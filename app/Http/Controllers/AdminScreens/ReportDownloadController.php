<?php

namespace App\Http\Controllers\AdminScreens;

use App\Http\Controllers\Controller;
use App\Models\ReportScheduleRun;
use App\Services\AdminScreens\ReportScheduler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * تنزيل التقرير الكبير برابط موقَّع محدود المدّة (24.3-خامسًا).
 *
 * **لماذا لا يمرّ من `permission` كبقيّة مسارات الإدارة؟** لأنّ مستقبِل التقرير
 * قد يكون بريدًا صريحًا لا حسابًا على المنصّة — والتقرير أُرسِل له عن قصد.
 * فالصلاحيّة هنا **هي التوقيع نفسه**: رابط لا يُخمَّن (رمز UUID) + توقيع Laravel
 * الذي يمنع تعديل الرمز أو تمديد المدّة + مدّة صلاحيّة من الإعدادات + سطر في
 * قاعدة البيانات يمكن إبطاله بحذفه. أربع طبقات لا واحدة.
 *
 * 🔒 **وعزل الماليّات فوق ذلك كلّه:** التقرير الموسوم `is_financial` لا يُنزَّل
 *    إلّا من **مالك المنصّة وهو داخلٌ بحسابه** (12.7) — التوقيع وحده لا يكفي،
 *    لأنّ رابطًا يُعاد توجيهه بالخطأ لا يجوز أن يفتح دفاتر المنصّة لأحد.
 */
class ReportDownloadController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $run = ReportScheduleRun::query()
            ->with('schedule')
            ->where('download_token', $token)
            ->first();

        // الرابط الملغى أو المنظَّف يُعامَل كغير موجود — لا نلمّح لما كان هنا
        abort_if($run === null || $run->download_path === null, 404, 'الرابط ده مابقاش شغّالًا.');

        abort_if(
            $run->download_expires_at === null || $run->download_expires_at->isPast(),
            410,
            'مدّة الرابط خلصت. افتح شاشة التقارير المجدولة واضغط «شغّل الآن» عشان يوصلك من جديد.',
        );

        // 🔒 الماليّ لمالك المنصّة وحده — وبحسابه لا بالرابط
        if ($run->schedule?->is_financial) {
            abort_unless(
                (bool) $request->user()?->isPlatformOwner(),
                403,
                'التقرير ده ماليّ — لمالك المنصّة وحده.',
            );
        }

        $disk = Storage::disk(ReportScheduler::DISK);

        abort_unless($disk->exists($run->download_path), 404, 'الملفّ اتشال من الخادم. شغّل التقرير من جديد.');

        return response((string) $disk->get($run->download_path), 200, [
            'Content-Type' => $run->downloadMime(),
            'Content-Disposition' => 'attachment; filename="'.($run->download_name ?: 'report').'"',
            // ملفّ مؤقّت لا يُخزَّن في كاش وسيط — خصوصًا لو حمل أرقامًا ماليّة
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
