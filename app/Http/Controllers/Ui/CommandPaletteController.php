<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Services\Ui\CommandIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * البحث الموحّد (Ctrl+K) — 2.15-د.
 *
 * حقل واحد يصل إلى أيّ صفحة أو شخص أو مهمّة بالكتابة، بديلًا عن التنقّل في
 * السايد بار. **وعلى الموبايل** أيقونة بحث في الهيدر تفتح شاشة بحث كاملة.
 */
class CommandPaletteController extends Controller
{
    public function __invoke(Request $request, CommandIndex $index): JsonResponse
    {
        // الشرط نفسه المنصوص عليه للبحث الكبير: يراه كلّ مستخدم **مفعَّل** (13.1)
        abort_unless($request->user()->isActive(), 403, 'حسابك لسّه تحت المراجعة — هيوصلك إشعار أوّل ما يتفعّل.');

        return response()->json(
            $index->search($request->user(), (string) $request->query('q', ''))
        );
    }
}
