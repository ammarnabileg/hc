<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\FileInviteLink;
use App\Services\Volunteer\Goals\FileDrafts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * قبول رابط دعوة ملفٍّ مبنيّ على البوزشن (23-0.2 · 8.1) — البديل عن
 * الإضافة المباشرة: أيّ عضوٍ يحمل الرابط ينضمّ بنفسه بلا اختيار اسمه سلفًا.
 */
class FileInviteController extends Controller
{
    public function __construct(private readonly FileDrafts $fileDrafts) {}

    public function show(Request $request, string $token): View
    {
        $link = FileInviteLink::query()->where('token', $token)->with(['entity', 'position'])->firstOrFail();

        return view('volunteer.file-invites.show', [
            'link' => $link,
            'expired' => $link->isExpired(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $link = FileInviteLink::query()->where('token', $token)->firstOrFail();

        $this->fileDrafts->acceptInviteLink($link, $request->user());

        return redirect()->route('volunteer.department')
            ->with('status', strtr((string) setting('volunteer.file_invites.accept_ok', 'انضممت لملفّ «:a1» ✓'), [':a1' => (string) ($link->entity?->name_ar)]));
    }
}
