<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Attestation;
use App\Models\User;
use App\Services\Library\AttestationBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * خبراتي ← الإفادة (الدستور 9.1 · 24.5).
 *
 * تُولَّد تلقائيًّا من داتا المنصّة (تدريبات مكتملة + شهادات + شارات + XP
 * + روابط تحقّق)، ورابطها العامّ واستخراجها **مجّانًا بلا تذاكر**.
 */
class AttestationController extends Controller
{
    public function __construct(private readonly AttestationBuilder $builder) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('cv.attestations', [
            'requests' => $this->builder->requests($user),
            'record' => $this->builder->platformRecord($user),
            'placements' => $this->builder->placements(),
            'limitReached' => $this->builder->openRequestsExceeded($user),
            'publicUrl' => route('attestations.public', $user->code),
            'builder' => $this->builder,
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

    /** الرابط العامّ القابل للمشاركة (9.1) — بيانات المنصّة الموثّقة فقط */
    public function public(string $code): View
    {
        $user = User::where('code', $code)->firstOrFail();

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
