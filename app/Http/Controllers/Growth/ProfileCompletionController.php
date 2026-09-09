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
        $state['granted'] = (bool) $request->session()->pull('growth.completion.granted', false);

        return view('growth.profile-completion', [
            'state' => $state,
            'fields' => $this->completion->fields(),
        ]);
    }

    /**
     * ⭐ المِنح الفعليّ — POST محميٌّ بـCSRF لا GET (12.9): الشاشة تُرسِله تلقائيًّا
     * (بلا ضغطة زائدة من المستخدم) لحظة ما تعرض ملفًّا مكتملًا لم يُصرَف بعد،
     * فتبقى التجربة «تلقائيّة» كما كانت — لكن الفعل المالي صار خلف فعلٍ لا
     * يمكن توليده بطلب قراءة مموَّه (`<img src>`/Prefetch).
     */
    public function claim(Request $request): RedirectResponse
    {
        $granted = $this->completion->grant($request->user());

        return redirect()->route('growth.profile.completion')->with('growth.completion.granted', $granted);
    }

    /** إخفاء البار لهذه الجلسة — زرّ إيقافٍ صريح لكلّ تذكير (21.1-د) */
    public function dismiss(Request $request): RedirectResponse
    {
        $request->session()->put('growth.completion.dismissed', true);

        return back();
    }
}
