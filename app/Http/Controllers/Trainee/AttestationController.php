<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Attestation;
use App\Models\Cv;
use App\Models\User;
use App\Services\Library\AttestationBuilder;
use App\Services\Library\CvBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * خبراتي ← الإفادة (الدستور 9.1 · 24.5).
 *
 * تُولَّد تلقائيًّا من داتا المنصّة (تدريبات مكتملة + شهادات + شارات + XP
 * + روابط تحقّق)، ورابطها العامّ واستخراجها **مجّانًا بلا تذاكر**.
 */
class AttestationController extends Controller
{
    public function __construct(
        private readonly AttestationBuilder $builder,
        private readonly CvBuilder $cv,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $cv = $this->cv->forUser($user);

        return view('cv.attestations', [
            'requests' => $this->builder->requests($user),
            'record' => $this->builder->platformRecord($user),
            'placements' => $this->builder->placements(),
            'limitReached' => $this->builder->openRequestsExceeded($user),
            // ⭐ الرابط لا يوجد قبل الموافقة — والموافقة صريحة كالسيرة (9.1 · 10.0-ج)
            'isPublic' => (bool) $cv->attestation_is_public,
            'publicUrl' => $cv->attestation_slug ? route('attestations.public', $cv->attestation_slug) : null,
            'builder' => $this->builder,
        ]);
    }

    /**
     * فتح/غلق الرابط العامّ للإفادة — على غرار الـCV تمامًا (9.1).
     *
     * ولماذا Slug عشوائيّ لا كود المستخدم: الأكواد متسلسلة، فالرابط بالكود
     * قابلٌ للتعداد — يُفتَح واحدٌ فيُخمَّن ما بعده.
     */
    public function togglePublic(Request $request): JsonResponse
    {
        $cv = $this->cv->forUser($request->user());
        $enable = $request->boolean('enabled');

        if ($enable && ! $cv->attestation_slug) {
            $cv->attestation_slug = Str::lower(Str::random((int) setting('attestations.public.slug_length', 12)));
        }

        $cv->attestation_is_public = $enable;
        $cv->save();

        return response()->json([
            'ok' => true,
            'enabled' => (bool) $cv->attestation_is_public,
            'url' => $cv->attestation_slug ? route('attestations.public', $cv->attestation_slug) : null,
            'message' => $enable
                ? (string) setting('attestations.public.opened_message', 'الرابط شغّال ✓')
                : (string) setting('attestations.public.closed_message', 'الرابط اتقفل ✓'),
        ]);
    }

    /** [اطلب إفادة]: الجهة/الشخص · السبب · ملاحظة (24.5) */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->builder->openRequestsExceeded($user)) {
            // القيد يُشرَح لحظة كسره فقط (2.15-د)
            return back()->with('status', (string) setting(
                'attestations.request.limit_message',
                'عندك طلبات مفتوحة كتير — استنّى ردّها الأوّل.',
            ));
        }

        $validated = $request->validate([
            'from_name' => ['required', 'string', 'max:150'],
            'reason' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'from_name' => (string) setting('attestations.field.from_label', 'الجهة أو الشخص'),
            'reason' => (string) setting('attestations.field.reason_label', 'سبب الطلب'),
        ]);

        Attestation::create([
            'user_id' => $user->id,
            'from_name' => $validated['from_name'],
            'body' => trim($validated['reason'].($validated['note'] ? "\n".$validated['note'] : '')),
            'status' => 'requested',
            'is_public' => false,
        ]);

        return back()->with('status', (string) setting(
            'attestations.request.sent_message',
            'طلبك وصل — هنبلّغك أوّل ما يتردّ عليه.',
        ));
    }

    /** استخراج ملفّ الإفادة — مجّانًا (9.1) */
    public function export(Request $request): View
    {
        $user = $request->user();

        return view('cv.attestation-sheet', [
            'holder' => $user,
            'record' => $this->builder->platformRecord($user),
            'approved' => $this->approved($user),
            'print' => true,
        ]);
    }

    /**
     * الرابط العامّ القابل للمشاركة (9.1) — بيانات المنصّة الموثّقة فقط،
     * و**لمن وافق وحده**. وبلا موافقةٍ فـ404 نظيف كبقيّة الواجهات.
     */
    public function public(string $code): View
    {
        $cv = Cv::query()
            ->with('user')
            ->where('attestation_slug', $code)
            ->where('attestation_is_public', true)
            ->firstOrFail();

        $user = $cv->user;

        // صاحب الإفادة محذوفٌ Soft ⟵ 404 لا 500 (لا نُثبت وجود الرابط)
        abort_if($user === null, 404);

        $cv->increment('attestation_views');

        return view('cv.attestation-sheet', [
            'holder' => $user,
            'record' => $this->builder->platformRecord($user),
            'approved' => $this->approved($user),
            'print' => false,
        ]);
    }

    private function approved(User $user)
    {
        return Attestation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'published'])
            ->where('is_public', true)
            ->latest()
            ->get();
    }
}
