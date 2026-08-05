<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\InviteLeaderboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ⭐ لوحة متصدّري الدعوات شهريًّا (21.1-ج) — ولم يكن لها مسارٌ ولا شاشة.
 *
 * و**اللوحة قابلة للاستخراج كصورة** (توسعة 12.14-هـ) عبر بطاقة الـOG الخاصّة بها،
 * فتُنشَر كما هي بلا لقطة شاشة.
 */
class InviteBoardController extends Controller
{
    public function __construct(private readonly InviteLeaderboard $board) {}

    public function index(Request $request): View
    {
        $month = $request->string('month')->toString() ?: null;
        $period = $this->board->period($month);

        return view('growth.invite-board', [
            'rows' => $this->board->rows($month),
            'months' => $this->board->months(),
            'month' => $period['key'],
            'periodLabel' => $period['from']->translatedFormat('F Y'),
            'myRank' => $this->board->positionOf($request->user(), $month),
            'cardUrl' => route('growth.og.leaderboard', ['month' => $period['key']]),
        ]);
    }
}
