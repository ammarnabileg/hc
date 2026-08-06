<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateReport;
use App\Services\Certificates\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * صفحة التحقّق العامّة (8.1 · 12.5-هـ · 21.2-ز):
 * **عامّة تمامًا — بلا تسجيل دخول ولا حساب**، تُفتَح بالـQR أو ببحثٍ بالكود مباشرةً.
 * وهي **صفحة مفهرسة** تخدم حلقة النموّ (21.1) باحترام إعداد الفهرسة.
 */
class PublicVerificationController extends Controller
{
    /**
     * الاستعلام الوحيد لإيجاد شهادة بكودها — مصدر الحقيقة الذي تعتمد عليه
     * أيّ نقطة تحقّق أخرى (2.11)، ومنها `GET /api/v1/certificates/{code}/verify`
     * (12.15-أ) — فلا يُكتَب شرط «موجودة/صالحة» مرّتين قد يختلفان يومًا.
     */
    public static function lookup(string $code): ?Certificate
    {
        $code = ltrim(trim($code), '#');

        return $code === '' ? null : Certificate::query()->where('code', $code)->first();
    }

    public function show(Request $request, ?string $code = null): View
    {
        $code = trim((string) ($code ?? $request->query('code', '')));
        $code = ltrim($code, '#');

        $certificate = $code === '' ? null : Certificate::query()
            ->with(['certificate_type.accreditation', 'user'])
            ->where('code', $code)
            ->first();

        return view('certificates.verify', [
            'code' => $code,
            'certificate' => $certificate,
            'searched' => $code !== '',
            'indexable' => (bool) setting('growth.seo.index_certificates', true) && $certificate?->status !== null,
            'statusText' => $certificate ? $this->statusText($certificate) : null,
            'state' => $certificate ? $this->state($certificate->status) : null,
        ]);
    }

    /** QR الشهادة كصورة عامّة — تصويره يفتح هذه الصفحة والكود متعبّى تلقائيًّا */
    public function qr(string $code): Response
    {
        $certificate = Certificate::query()->where('code', $code)->firstOrFail();

        $png = QrCode::png(
            route('verify.certificate', ['code' => $certificate->code]),
            (int) setting('certificates.render.qr_size_px', 240),
            (int) setting('certificates.render.qr_margin_modules', 4),
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.(int) setting('certificates.render.http_cache_seconds', 3600),
        ]);
    }

    /**
     * ⭐ [أبلغ عن شهادة مشبوهة] (12.5-هـ · 24.1) — بلاغ **بلا حساب**.
     *
     * ⚠️ وكان الزرّ يكتب سطرًا في سجلّ التدقيق **بلا جدولٍ للبلاغات ولا شاشة
     * مراجعة**، فالبلاغ يذهب إلى حيث لا يقرؤه أحد. والنصّ يوجب الجدول صراحةً:
     * «وأسفلها **جدول البلاغات:** الكود · المبلِّغ · السبب · التاريخ · الحالة ·
     * [مراجعة]» (24.1). فيُحفَظ البلاغ صفًّا في `certificate_reports` ويبقى
     * سطرُ التدقيق إلى جانبه — الأوّل للعمل، والثاني للأثر الذي لا يُمحى.
     */
    public function report(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'note' => ['required', 'string', 'max:2000'],
            'contact' => ['nullable', 'string', 'max:190'],
        ]);

        $code = ltrim(trim($validated['code']), '#');

        /*
         | البلاغ عن **كودٍ لا صفّ له** بلاغٌ حقيقيّ لا خطأ إدخال: ورقةٌ بكودٍ
         | مخترَع هي عين ما تحرسه صفحة التحقّق. فلا يُردّ بـ404 ولا يُبتلَع —
         | يُحفَظ بكوده و`certificate_id` فارغ.
         */
        $certificate = Certificate::query()->where('code', $code)->first();

        CertificateReport::create([
            'certificate_id' => $certificate?->id,
            'code' => $code,
            'reporter_id' => $request->user()?->id,
            'reporter_contact' => $validated['contact'] ?? null,
            'reason' => $validated['note'],
            'status' => CertificateReport::NEW,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 190),
        ]);

        // سجلّ تدقيق لكلّ بلاغ (12.5-د): مَن ومتى ولماذا — ويعمل بلا حساب أصلًا
        if ($certificate) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => (string) setting('certificates.report.audit_action', 'certificate.reported'),
                'auditable_type' => $certificate->getMorphClass(),
                'auditable_id' => $certificate->id,
                'new_values' => [
                    'note' => $validated['note'],
                    'contact' => $validated['contact'] ?? null,
                ],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 190),
            ]);
        }

        return redirect()
            ->route('verify.certificate', ['code' => $code])
            ->with('status', (string) setting(
                'certificates.report.thanks',
                'وصلنا بلاغك وهنراجعه — شكرًا إنّك ساعدتنا نحمي قيمة الشهادة.',
            ));
    }

    /** نصّ الحالة — و«منتهية» لها نصّها المعتمَد بالحرف (13.4-ق-و) */
    private function statusText(Certificate $certificate): string
    {
        $format = (string) setting('certificates.render.date_format', 'Y/m/d');

        return match ($certificate->status) {
            'expired' => str_replace(
                [(string) setting('certificates.verify.status_text_msg', '[تاريخ الإصدار]'), (string) setting('certificates.verify.status_text_msg_2', '[تاريخ الانتهاء]')],
                [$certificate->issued_at?->format($format) ?? '—', $certificate->expired_at?->format($format) ?? '—'],
                (string) setting(
                    'certificates.verify.expired_text',
                    'هذه الشهادة منتهية: صدرت بتاريخ [تاريخ الإصدار] وانتهى العمل بها بتاريخ [تاريخ الانتهاء] بعد دخول صاحبها امتحانًا أحدث. وهي ليست ملغاة ولا مطعونًا في صحّتها.',
                ),
            ),
            'revoked' => (string) setting(
                'certificates.verify.revoked_text',
                'هذه الشهادة ملغاة. الإلغاء لا يقع إلّا على تزويرٍ مثبَت.',
            ),
            default => (string) setting(
                'certificates.verify.valid_text',
                'هذه الشهادة سارية وصادرة من المنصّة، وبياناتها مطابقة لسجلّنا.',
            ),
        };
    }

    /** قاموس الحالة (2.16): لون ومعه رمز دائمًا — والذهبيّ للشرف لا للحالة */
    private function state(string $status): string
    {
        return match ($status) {
            'valid' => 'ok',
            'expired' => 'idle',
            default => 'danger',
        };
    }
}
