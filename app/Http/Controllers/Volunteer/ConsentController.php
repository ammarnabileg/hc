<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\ConsentRequest;
use App\Models\User;
use App\Services\Volunteer\Profile\ConsentFlow;
use App\Services\Volunteer\Profile\VolunteerProfileTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * موافقة إظهار التواصل (13.4-م-2) — المسار كاملًا: طلب · موافقة · رفض · سجلّ.
 *
 * ثلاث قواعد تحكم كلّ ردّ هنا:
 *  1) **الردّ للطالب محايد دائمًا** — «وصل طلبك» ثمّ «غير متاح»، بلا كلمة رفض أبدًا.
 *  2) **الرفض والانتهاء صامتان** — لا إشعار للطالب في الحالتين.
 *  3) **التبريد يبدأ بعد الانتهاء أو الرفض**، لا لحظة إنشاء الطلب.
 */
class ConsentController extends Controller
{
    public function __construct(private readonly ConsentFlow $flow) {}

    /** «اطلب إظهار الرقم/الإيميل» — الطلب على **البيانات** لا على الشخص */
    public function request(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'in:'.implode(',', ConsentFlow::FIELDS)],
            // سبب الطلب **اختياريّ** — يرفع نسبة القبول ويقلّل الرفض العشوائيّ
            'reason' => ['nullable', 'string', 'max:'.(int) setting('volunteer.profile.consent.reason_max', 300)],
        ]);

        $owner = User::where('code', $code)->firstOrFail();
        $viewer = $request->user();

        abort_if($viewer->id === $owner->id, 403);

        try {
            $this->flow->request($viewer, $owner, $data['field'], $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        // نفس الرسالة سواء اتسجّل الطلب أو منعه التبريد — فلا يُستدَلّ على شيء
        return back()->with('status', (string) setting(
            'volunteer.consent.request_sent',
            'وصل طلبك — هيوصلك الردّ لمّا يتاح.',
        ));
    }

    /** موافقة صاحب البيانات — «سهّلت التعاون 🤝» (إجراء إداريّ صغير لا إنجاز) */
    public function approve(Request $request, ConsentRequest $consent): RedirectResponse
    {
        try {
            $this->flow->approve($consent, $request->user());
        } catch (RuntimeException $e) {
            return $this->backToContact($e->getMessage());
        }

        return $this->backToContact((string) setting(
            'volunteer.profile.consent.approved_message',
            'سهّلت التعاون 🤝 — بياناتك هتبان له للمدّة المحدّدة بس.',
        ), celebrate: true);
    }

    /** رفض **صامت**: بلا سبب إلزاميّ وبلا إشعار للطرف الآخر */
    public function deny(Request $request, ConsentRequest $consent): RedirectResponse
    {
        try {
            $this->flow->deny($consent, $request->user());
        } catch (RuntimeException $e) {
            return $this->backToContact($e->getMessage());
        }

        return $this->backToContact((string) setting(
            'volunteer.profile.consent.denied_message',
            'تمام — الطلب اتقفل، وبياناتك زيّ ما هي.',
        ));
    }

    /**
     * سجلّ الطلبات ومؤشّرات الثقة (13.4-م-2 · 13.4-ك):
     * عدد المرفوضة · معدّل القبول · متوسّط زمن الردّ — مؤشّر على الثقافة المؤسّسيّة.
     */
    public function insights(Request $request): View
    {
        $days = (int) ($request->integer('days') ?: setting('ux.lists.default_range_days', 30));

        // الانتهاء الصامت يُطبَّق قبل القراءة فلا تُحسَب طلبات ميّتة كمعلّقة
        $this->flow->sweepExpired();

        return view('volunteer.profile.consent-insights', [
            'metrics' => $this->flow->trustMetrics($days),
            'rows' => $this->flow->ledger($days),
            'days' => $days,
        ]);
    }

    private function backToContact(string $status, bool $celebrate = false): RedirectResponse
    {
        return redirect()
            ->route('profile.me', ['tab' => VolunteerProfileTabs::CONTACT])
            ->with('status', $status)
            ->with('consent_celebrate', $celebrate);
    }
}
