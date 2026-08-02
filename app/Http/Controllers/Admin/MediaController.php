<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\Admin\Content\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * مكتبة الوسائط المركزيّة (12.4-د · 24.1).
 *
 * ⭐ منع التكرار بالهاش: الملفّ نفسه لا يُخزَّن مرّتين، وتُعاد النسخة الموجودة
 * برسالة واضحة — و**تتبّع الاستخدام** يمنع حذف ملفٍّ مستعمَل بلا تحذير.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $items = $this->library->search($filters);

        return view('admin.courses.media', [
            'items' => $items,
            'usage' => $this->usageMap($items->getCollection()),
            'filters' => $filters,
            'folders' => $this->library->folders(),
            'tags' => $this->library->tags(),
            'view' => $request->string('view')->toString() ?: 'grid',
        ]);
    }

    /** بوب-أب «اختَر من المكتبة / ارفع جديد» المستدعى من أيّ حقل رفع (12.4-د). */
    public function picker(Request $request): View
    {
        return view('admin.courses.media-picker', [
            'items' => $this->library->search($this->filters($request)),
            'target' => $request->string('target')->toString(),
            'multiple' => $request->boolean('multiple'),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['file' => $this->library->rules()]);

        $result = $this->library->store(
            file: $request->file('file'),
            uploader: $request->user(),
            folder: $request->string('folder')->toString() ?: null,
            tags: $this->library->cleanTags($request->input('tags')),
        );

        $message = $result['duplicated']
            ? (string) setting('media.dedup.notice', 'الملفّ ده موجود عندنا — استخدمنا النسخة الحاليّة ✓')
            : 'اترفع الملفّ ✓';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'duplicated' => $result['duplicated'],
                'item' => [
                    'id' => $result['item']->id,
                    'name' => $result['item']->name,
                    'path' => $result['item']->path,
                    'url' => $this->library->url($result['item']),
                ],
            ]);
        }

        return back()->with('status', $message);
    }

    public function update(Request $request, MediaItem $media): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'folder' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable'],
        ]);

        $media->update([
            'name' => $data['name'],
            'folder' => $data['folder'] ?? null,
            'tags' => $this->library->cleanTags($data['tags'] ?? []),
        ]);

        return back()->with('status', 'اتحفظ ✓');
    }

    /** الحذف مع تحذير إن كان الملفّ مستعمَلًا (12.4-د). */
    public function destroy(Request $request, MediaItem $media): RedirectResponse
    {
        $used = $this->library->usageCount($media);

        if ($used > 0 && ! $request->boolean('force')) {
            return back()->with('status', (string) setting(
                'media.delete.in_use_warning',
                'الملفّ ده مستخدَم في أماكن تانية — أكّد الحذف لو متأكّد.',
            ));
        }

        Storage::disk($media->disk ?: 'public')->delete($media->path);
        $media->delete();

        return back()->with('status', 'اتشال الملفّ ✓');
    }

    /** «مستخدَم في X مكان» بروابطه. */
    public function usage(MediaItem $media): JsonResponse
    {
        return response()->json([
            'total' => $this->library->usageCount($media),
            'places' => $this->library->usage($media)->all(),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'q' => trim($request->string('q')->toString()),
            'kind' => $request->string('kind')->toString(),
            'folder' => $request->string('folder')->toString(),
            'tag' => $request->string('tag')->toString(),
            'unused' => $request->boolean('unused'),
        ];
    }

    /** @return array<int, int> */
    private function usageMap(Collection $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $map[$item->id] = $this->library->usageCount($item);
        }

        return $map;
    }
}
