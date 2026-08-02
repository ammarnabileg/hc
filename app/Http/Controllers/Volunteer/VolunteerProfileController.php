<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Volunteer\Profile\NotesPanel;
use App\Services\Volunteer\Profile\ProfileReport;
use App\Services\Volunteer\Profile\VolunteerProfileTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * طبقة التطوّع على البروفايل الواحد (13.4-م): ما لا يسعه الحقنُ في الصفحة نفسها —
 * **تقرير الترقية** و**الملاحظات الإداريّة السرّيّة**.
 */
class VolunteerProfileController extends Controller
{
    public function __construct(
        private readonly ProfileReport $report,
        private readonly NotesPanel $notes,
    ) {}

    /**
     * «تقرير PDF» — مادّة قرار الترقية (13.4-م-1): برقم مرجع وتاريخ إصدار،
     * **ويُسجَّل في الأوديت** لحظة الإصدار لا بعده.
     */
    public function report(Request $request, string $code): View
    {
        $owner = User::query()
            ->with(['country:id,name_ar', 'governorate:id,name_ar'])
            ->where('code', $code)
            ->firstOrFail();

        // الصلاحيّة على **هذا الشخص** لا على الشاشة وحدها (12.2.1)
        abort_unless($request->user()->allows('reports_volunteer.view', $owner), 403);

        return view('volunteer.profile.report', $this->report->build($owner, $request->user()));
    }

    /** ملاحظة إداريّة سرّيّة — للمخوَّل وحده، وبـAudit كامل (13.4-م-5) */
    public function storeNote(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) setting('volunteer.profile.notes.max_chars', 2000)],
        ]);

        $owner = User::where('code', $code)->firstOrFail();

        abort_unless($request->user()->allows('admin_notes.create', $owner), 403);
        // ⛔ ولا يكتب أحد ملاحظة إداريّة على نفسه
        abort_if($request->user()->id === $owner->id, 403);

        try {
            $this->notes->write($request->user(), $owner, $data['body']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('status', $e->getMessage());
        }

        return redirect()
            ->to('/u/'.$owner->code.'?tab='.VolunteerProfileTabs::NOTES)
            ->with('status', (string) setting('volunteer.profile.notes.saved', 'اتحفظت الملاحظة ✓ — سرّيّة ومسجّلة في التدقيق.'));
    }
}
