<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * «شهاداتي» (24.5): رفّ أوسمة من **المصدر الواحد** — نفس مصدر المكتبة والبروفايل.
 * والحالات الثلاث تُعرَض كما هي: **سارية · منتهية · ملغاة** — والمنتهية لا تُخفى (13.4-ق).
 */
class CertificateController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $certificates = Certificate::query()
            ->with(['certificate_type.accreditation'])
            ->where('user_id', $user->id)
            ->when($request->filled('type'), fn ($q) => $q->whereHas(
                'certificate_type',
                fn ($t) => $t->where('key', $request->string('type')),
            ))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('issued_at', (int) $request->integer('year')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('data_snapshot', 'like', $term));
            })
            ->orderByDesc('issued_at')
            ->get();

        $years = Certificate::query()
            ->where('user_id', $user->id)
            ->pluck('issued_at')
            ->map(fn ($date) => $date?->year)
            ->filter()->unique()->sortDesc()->values();

        return view('certificates.index', [
            'certificates' => $certificates,
            'types' => CertificateType::query()->where('is_active', true)->orderBy('id')->get(),
            'years' => $years,
            'filters' => [
                'type' => $request->string('type')->toString(),
                'year' => $request->string('year')->toString(),
                'q' => $request->string('q')->toString(),
            ],
        ]);
    }

    /** صورة الشهادة المولَّدة على الخادم — عامّة لأنّ صفحة التحقّق تنزّلها (8.1) */
    public function image(string $code): Response
    {
        $certificate = $this->find($code);

        return response($this->renderer->png($certificate), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$certificate->code.'.png"',
            'Cache-Control' => 'public, max-age='.(int) setting('certificates.render.http_cache_seconds', 3600),
        ]);
    }

    public function download(string $code): Response
    {
        $certificate = $this->find($code);

        return response($this->renderer->png($certificate), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$certificate->code.'.png"',
        ]);
    }

    /** البديل الطباعيّ (HTML قابل للطباعة كـPDF من المتصفّح) — بلا مكتبة خارجيّة */
    public function pdf(string $code): View
    {
        $certificate = $this->find($code);

        return view('certificates.pdf', [
            'certificate' => $certificate,
            'data' => (array) $certificate->data_snapshot,
            'verifyUrl' => route('verify.certificate', ['code' => $certificate->code]),
        ]);
    }

    private function find(string $code): Certificate
    {
        return Certificate::query()
            ->with('certificate_type.accreditation')
            ->where('code', $code)
            ->firstOrFail();
    }
}
