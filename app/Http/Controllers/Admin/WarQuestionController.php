<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarQuestion;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Gamification\Wars\WarBankService;
use App\Services\Gamification\Wars\WarRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بنك أسئلة الحروب (12.10-ب · شاشة 24.2).
 *
 * ⭐ قفلان معلَنان:
 *  - **الإجابة مخفيّة افتراضيًّا** ولا تظهر إلّا بكشفٍ مؤقّت **مسجَّل في Audit**.
 *  - **بلا صلاحيّة `wars_bank.view` لا إجابات ولا تصدير** — والعنصر يُخفى لا يُعطَّل.
 */
class WarQuestionController extends Controller
{
    public function __construct(
        private readonly WarBankService $bank,
        private readonly WarRules $rules,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $difficulty = (string) $request->query('difficulty', '');
        $status = (string) $request->query('status', '');
        $source = (string) $request->query('source', '');
        $missing = $request->boolean('missing');

        $questions = WarQuestion::query()
            ->when($search !== '', fn ($q) => $q->where('text', 'like', "%{$search}%"))
            ->when(in_array($difficulty, WarBankService::DIFFICULTY_KEYS, true), fn ($q) => $q->where('difficulty', $difficulty))
            ->when(in_array($status, WarBankService::STATUS_KEYS, true), fn ($q) => $q->where('status', $status))
            ->when(in_array($source, WarBankService::SOURCE_KEYS, true), fn ($q) => $q->where('source', $source))
            // فلتر «بلا إجابة» — أسئلة لا تصلح للحرب حتى تُستكمَل (24.2)
            ->when($missing, fn ($q) => $q->where(fn ($w) => $w->whereNull('answer')->orWhere('answer', '')))
            ->latest('id')
            ->paginate((int) setting('ux.lists.per_page', 25))
            ->withQueryString();

        return view('admin.wars.bank.index', [
            'questions' => $questions,
            'filters' => compact('search', 'difficulty', 'status', 'source', 'missing'),
            'difficulties' => WarBankService::difficulties(),
            'sources' => WarBankService::sources(),
            'statuses' => WarBankService::statuses(),
            'counts' => [
                'all' => WarQuestion::count(),
                'active' => WarQuestion::where('status', 'active')->count(),
                'numeric' => WarQuestion::where('status', 'active')->where('is_numeric', true)->count(),
                'missing' => WarQuestion::where(fn ($w) => $w->whereNull('answer')->orWhere('answer', ''))->count(),
            ],
            'minActive' => $this->rules->minActiveQuestions(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:war_questions,id'],
            'text' => ['required', 'string', 'max:500'],
            'answer' => ['nullable', 'string', 'max:160'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:160'],
            'tolerance' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'difficulty' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $question = isset($data['id']) ? WarQuestion::findOrFail($data['id']) : null;

        $this->bank->save($question, $data, $request->user());

        return back()->with('status', (string) setting('wars.questions_admin.save_ok', 'اتحفظ ✓'));
    }

    public function destroy(Request $request, WarQuestion $warQuestion): RedirectResponse
    {
        AuditTrail::log($request->user(), 'wars_bank.delete', $warQuestion, $warQuestion->only(['text']), []);
        $warQuestion->delete();

        return back()->with('status', (string) setting('wars.questions_admin.destroy_ok', 'اتحذف السؤال ✓'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'action' => ['required', 'string', 'in:activate,draft,archive,delete'],
        ]);

        $count = $this->bank->bulk($data['ids'], $data['action'], $request->user());

        return back()->with('status', strtr((string) setting('wars.questions_admin.bulk_ok', 'اتنفّذ الإجراء على :count سؤال ✓'), [':count' => (string) $count]));
    }

    /** كشف الإجابة مؤقّتًا — ويُسجَّل في Audit لأنّه استثناء على القفل (24.2) */
    public function reveal(Request $request, WarQuestion $warQuestion): RedirectResponse
    {
        AuditTrail::log($request->user(), 'wars_bank.reveal', $warQuestion, [], ['id' => $warQuestion->id]);

        return back()->with('reveal_answer', $warQuestion->id);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $contents = (string) file_get_contents($data['file']->getRealPath());

        if (! $request->boolean('confirm')) {
            $parsed = $this->bank->parseCsv($contents);

            return back()
                ->with('import_preview', $parsed['rows'])
                ->with('import_errors', $parsed['errors']);
        }

        $result = $this->bank->import($contents, $request->user());

        return back()->with('status', $result['errors']
            ? strtr((string) setting('wars.questions_admin.import_msg', ':a1 — لم يُضَف شيء.'), [':a1' => (string) ($result['errors'][0])])
            : strtr((string) setting('wars.questions_admin.import_ok', 'اتضاف :count سؤال ✓'), [':count' => (string) $result['imported']]));
    }

    public function export(Request $request): StreamedResponse
    {
        AuditTrail::log($request->user(), 'wars_bank.export', null, [], ['count' => WarQuestion::count()]);

        $csv = $this->bank->toCsv(WarQuestion::query()->orderBy('id')->cursor());

        return response()->streamDownload(
            function () use ($csv) {
                // BOM كي تفتح إكسل الملفّ بالعربيّة سليمةً
                echo "\xEF\xBB\xBF".$csv;
            },
            'wars-question-bank.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
