<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlacementTestQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ⭐ **بناء الاختبار التمهيديّ** (2.5-د-2 · 12 · 24 — محتوى الـOnboarding).
 *
 * الدستور 2.5-د-2 حرفيًّا: «**اختبار تمهيدي (Placement):** يُدار من الأدمن —
 * الأسئلة ممكن تكون (**فيديو و/أو كود Embedded HTML من أي مكان و/أو نص و/أو
 * صورة**)، والإجابات مثل إجابات الاختبارات العادية. **مكافأة لكل سؤال** بجانبه:
 * **XP فقط أو تذاكر فقط أو الاثنين**».
 *
 * والقسم 12 يعدّ من صلاحيّات الأدمن: «**بناء الاختبار التمهيدي** (أسئلة
 * فيديو/HTML/نص/صورة + مكافأة XP/تذاكر لكل سؤال)».
 *
 * وشاشتها في 24 (محتوى الـOnboarding): «**[الاختبار التمهيديّ]** جدول أسئلة:
 * السؤال · النوع (فيديو/HTML/نصّ/صورة) · **مكافأة XP** · **مكافأة تذاكر** ·
 * الترتيب (سحب) · الحالة · إجراءات» و«**[سؤال]** النصّ + النوع + الوسائط +
 * الخيارات + الإجابة الصحيحة + XP + تذاكر» و«**الحالات:** فارغة «لا أسئلة
 * تمهيديّة — أضِف أوّل سؤال» … خطأ HTML غير صالح ⇒ تحذير قبل الحفظ».
 *
 * ⚠️ **وكان جدولًا بلا شاشة:** `placement_test_questions` موجود منذ هجرته،
 * و`PlacementTest` تقرأ منه، وشاشة المتدرّب `onboarding.placement` مبنيّة —
 * ولا مسارَ ولا فيو ينشئ سؤالًا واحدًا. فالبنك يبقى فارغًا أبدًا، وخطوةُ
 * التصفية المنصوصة في 2.5-د تُتخطّى صامتةً لكلّ مُسجَّل.
 *
 * ⚠️ **ولا علاقة له بـ`placements.*`** (تسكين المتطوّعين — 13.4-هـ): موردان
 * مختلفان في مصفوفة 12.2.2، وصلاحيّات هذه الشاشة هي `placement_test.*` وحدها.
 */
class PlacementTestAdminController extends Controller
{
    /** الأنواع المنصوصة حرفيًّا في 2.5-د-2 — لا نوعَ من عندنا */
    private function mediaKinds(): array
    {
        return (array) setting('onboarding.placement.media_kinds', []);
    }

    private function answerTypes(): array
    {
        return (array) setting('onboarding.placement.answer_types', []);
    }

    public function index(Request $request): View
    {
        $activeCount = PlacementTestQuestion::query()->where('is_active', true)->count();

        return view('placement.admin.index', [
            'questions' => PlacementTestQuestion::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'mediaKinds' => $this->mediaKinds(),
            'answerTypes' => $this->answerTypes(),
            'answerCounts' => DB::table('placement_test_answers')
                ->select('placement_test_question_id', DB::raw('count(*) as total'))
                ->groupBy('placement_test_question_id')
                ->pluck('total', 'placement_test_question_id'),
            // ⭐ الخطوة مفعَّلة إعداديًّا لكن بلا سؤال نشِط واحد — تُتخطّى صامتةً
            // لكلّ مُسجَّل جديد (`PlacementTest::isEnabled()`) ولا يرى أحد ذلك
            // إلّا هنا: التحذير الصريح بديل التخطّي الصامت الذي كان يخفي الفجوة.
            'silentlySkipped' => (bool) setting('onboarding.placement.enabled', true) && $activeCount === 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['sort_order'] = (int) PlacementTestQuestion::max('sort_order') + 1;
        $data['created_by'] = $request->user()->id;

        PlacementTestQuestion::create($data);

        return back()->with('status', setting('onboarding.placement.admin.created'));
    }

    public function update(Request $request, PlacementTestQuestion $question): RedirectResponse
    {
        $question->update($this->validated($request));

        return back()->with('status', setting('onboarding.placement.admin.updated'));
    }

    /** الحالة: منشور/موقوف — والموقوف لا يُعرَض على المسجّل الجديد ولا يُحذَف تاريخه */
    public function toggle(PlacementTestQuestion $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        return back()->with('status', setting('onboarding.placement.admin.toggled'));
    }

    public function destroy(PlacementTestQuestion $question): RedirectResponse
    {
        $question->delete();

        return back()->with('status', setting('onboarding.placement.admin.deleted'));
    }

    /** «الترتيب (سحب)» (24) — والقرار في الخادم لا في المتصفّح */
    public function reorder(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids'))));

        foreach ($ids as $index => $id) {
            PlacementTestQuestion::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * «تصدير إجابات المتقدّمين ونتائجهم» — نصّ `placement_test.export` في
     * مصفوفة 12.2.2 حرفيًّا. صفٌّ لكلّ إجابة، لا رقمٌ مجرَّد.
     */
    public function export(): StreamedResponse
    {
        $rows = DB::table('placement_test_answers as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('placement_test_questions as q', 'q.id', '=', 'a.placement_test_question_id')
            ->orderBy('a.id')
            ->get(['u.code', 'u.name', 'u.placement_score', 'q.prompt', 'a.answer', 'a.is_correct', 'a.xp_awarded', 'a.tickets_awarded', 'a.answered_at']);

        $headers = (array) setting('onboarding.placement.admin.export_headers', []);

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM — كي يفتح إكسل العربيّة سليمةً
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->code, $row->name, $row->placement_score, $row->prompt,
                    $row->answer, $row->is_correct, $row->xp_awarded, $row->tickets_awarded, $row->answered_at,
                ]);
            }

            fclose($out);
        }, (string) setting('onboarding.placement.admin.export_file', 'placement.csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ---------------------------------------------------------------- داخليّ

    /**
     * ⭐ «**خطأ HTML غير صالح ⇒ تحذير قبل الحفظ**» (24) — والفحص هنا لا في
     * المتصفّح: وسمٌ غير مغلق يكسر شاشة كلّ مُسجَّل جديد بعده.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'media_kind' => ['required', 'string', 'in:'.implode(',', array_keys($this->mediaKinds()))],
            'media_url' => ['nullable', 'string', 'max:1024'],
            'embed_html' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys($this->answerTypes()))],
            'options' => ['nullable', 'string'],
            'correct_answer' => ['nullable', 'string', 'max:500'],
            'reward_xp' => ['nullable', 'integer', 'min:0', 'max:'.(int) setting('onboarding.placement.admin.max_reward_xp', 1000)],
            'reward_tickets' => ['nullable', 'integer', 'min:0', 'max:'.(int) setting('onboarding.placement.admin.max_reward_tickets', 100)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'media_kind.in' => setting('onboarding.placement.admin.bad_media_kind'),
            'type.in' => setting('onboarding.placement.admin.bad_type'),
        ]);

        if (($data['media_kind'] ?? '') === 'embed' && ! $this->htmlIsBalanced((string) ($data['embed_html'] ?? ''))) {
            throw ValidationException::withMessages([
                'embed_html' => (string) setting('onboarding.placement.admin.bad_html'),
            ]);
        }

        return [
            'prompt' => $data['prompt'],
            'media_kind' => $data['media_kind'],
            'media_url' => $data['media_url'] ?? null,
            'embed_html' => $data['embed_html'] ?? null,
            'type' => $data['type'],
            'options' => $this->splitOptions($data['options'] ?? null),
            'correct_answer' => $data['correct_answer'] ?? null,
            'reward_xp' => (int) ($data['reward_xp'] ?? 0),
            'reward_tickets' => (int) ($data['reward_tickets'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** خياراتٌ سطرٌ لكلّ خيار — أبسط ما يفهمه الأدمن، والفارغ يسقط */
    private function splitOptions(?string $raw): ?array
    {
        $options = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw) ?: []),
            fn (string $line) => $line !== '',
        ));

        return $options === [] ? null : $options;
    }

    /**
     * فحصٌ بسيط للاتّزان: كلّ وسمٍ مفتوح له مغلق. لا نطلب مُحلِّل HTML كاملًا،
     * لكنّ الوسم غير المغلق هو الخطأ الذي يكسر الشاشة فعلًا.
     */
    private function htmlIsBalanced(string $html): bool
    {
        if (trim($html) === '') {
            return true;
        }

        $void = (array) setting('onboarding.placement.admin.void_tags', []);
        preg_match_all('/<\s*(\/?)\s*([a-zA-Z0-9]+)[^>]*?(\/?)\s*>/', $html, $matches, PREG_SET_ORDER);

        $stack = [];

        foreach ($matches as [$_, $closing, $tag, $selfClosing]) {
            $tag = strtolower($tag);

            if (in_array($tag, $void, true) || $selfClosing === '/') {
                continue;
            }

            if ($closing === '/') {
                if (array_pop($stack) !== $tag) {
                    return false;
                }

                continue;
            }

            $stack[] = $tag;
        }

        return $stack === [];
    }
}
