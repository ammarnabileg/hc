<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\ProfileCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ⭐ بار «أكمل ملفك» ومكافأته (21.1-ب) — والمستخدم أقرّ «مكافأه 3 تذاكر» صراحةً.
 *
 * الشاشة سؤالٌ واحد (2.15): «إيه الناقص في ملفّك؟» وفعلٌ رئيسيّ واحد: [كمّل ملفّك].
 * والمكافأة تُمنَح **مرّة واحدة** مهما تكرّر الفتح — الحارس في الخدمة لا في الزرّ.
 */
class ProfileCompletionController extends Controller
{
    public function __construct(private readonly ProfileCompletion $completion) {}

    public function show(Request $request): View
    {
        $state = $this->completion->sync($request->user());

        return view('growth.profile-completion', [
            'state' => $state,
            'fields' => $this->completion->fields(),
        ]);
    }

    /** إخفاء البار لهذه الجلسة — زرّ إيقافٍ صريح لكلّ تذكير (21.1-د) */
    public function dismiss(Request $request): RedirectResponse
    {
        $request->session()->put('growth.completion.dismissed', true);

        return back();
    }
}
