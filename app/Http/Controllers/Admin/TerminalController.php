<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Developers\TerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تنفيذ أمر Shell عبر تاب «الطرفيّة» (12.15-هـ) — مالك المنصّة حصرًا.
 *
 * ⛔ **لا صلاحيّة `permission:` لهذا المسار** — الدستور صريح: «مالك المنصّة
 * حصرًا — لا صلاحيّة تُمنَح لأيّ دورٍ آخر، ولا استثناء». الحارس `isPlatformOwner()`
 * مباشرةً، بنفس نمط `AdsController::export()` (owner-only بلا مفتاح صلاحيّة).
 * ولا ميدلوير owner-only جاهزًا في المشروع فـ`abort_unless` هنا طبقة الدفاع
 * الوحيدة — لا يُخترَع ميدلويرٌ جديد لغرضٍ واحد.
 */
class TerminalController extends Controller
{
    public function __construct(private readonly TerminalService $service) {}

    public function run(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);

        abort_unless(
            (bool) setting('developers.terminal.enabled', true),
            503,
            (string) setting('developers.admin.terminal_disabled_msg', 'تاب الطرفيّة معطَّل حاليًّا من الإعدادات.')
        );

        $data = $request->validate(['command' => ['required', 'string', 'max:4000']]);

        $result = $this->service->run($data['command'], $request->user(), $request->ip());

        return response()->json($result);
    }
}
