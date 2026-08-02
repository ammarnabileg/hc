<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\InternalLibraryItem;
use App\Models\Track;
use App\Services\Volunteer\People\LibrarySearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * المكتبة الداخليّة (23-3.3 · 24.4-10).
 *
 * **بلا زرّ رفع يدويّ** — الفهرسة آليّة لحظة اعتماد المهمّة، وهذه الشاشة **قراءةٌ وبحث**.
 * وما هو خارج نطاقي **يظهر بعنوانه وقفلٍ وزرّ [اطلب وصولًا]** — لا يُخفى.
 */
class LibraryController extends Controller
{
    public function __construct(private readonly LibrarySearch $library) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'q' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
            'entity' => $request->integer('entity') ?: null,
            'track' => $request->integer('track') ?: null,
            'access' => $request->string('access')->toString(),
            'owner' => $request->boolean('mine') ? $user->id : null,
            'tag' => $request->string('tag')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $items = $this->library->search($user, array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        return view('volunteer.library.index', [
            'items' => $items,
            'library' => $this->library,
            'types' => $this->library->types(),
            'accessLevels' => $this->library->accessLevels(),
            'tags' => $this->library->allTags(),
            'entities' => Entity::query()->where('status', 'active')->orderBy('name_ar')->get(),
            'tracks' => Track::query()->orderBy('id')->get(),
            'filters' => $filters,
            'pending' => $this->library->pendingRequestIds($user, $items),
            'view' => $request->string('view')->toString() ?: 'grid',
        ]);
    }

    /** بوب-أب المُدخَل — والمقيَّد لا يُفتَح لكنّه معروفٌ بعنوانه */
    public function show(Request $request, InternalLibraryItem $item): View
    {
        $user = $request->user();
        $item->load(['entity', 'owner', 'task']);

        return view('volunteer.library.item', [
            'item' => $item,
            'locked' => ! $this->library->canOpen($user, $item),
            'library' => $this->library,
            'types' => $this->library->types(),
        ]);
    }

    /** [اطلب وصولًا] بضغطة — **يقرّه دايركتور الكيان** (23-3.3) */
    public function requestAccess(Request $request, InternalLibraryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->library->requestAccess($item, $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'اتبعت ✓ دايركتور الكيان هيشوف الطلب.');
    }
}
