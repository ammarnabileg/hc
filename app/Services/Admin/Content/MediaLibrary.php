<?php

namespace App\Services\Admin\Content;

use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * مكتبة الوسائط المركزيّة (12.4-د · 24.1): يُرفَع الملفّ **مرّة واحدة** ويُعاد استخدامه.
 *
 * ⭐ منع التكرار (Dedup) بمطابقة الهاش: نفس الملفّ بايتًا ببايت يعيد النسخة الموجودة
 * بدل تخزين ثانية — فلا تتضخّم المساحة ولا تتفرّق المراجع.
 * وتتبّع الاستخدام يمنع حذف ملفّ مستعمَل بلا تحذير.
 */
class MediaLibrary
{
    /** الأماكن التي قد تشير إلى ملفّ من المكتبة — عمود المسار أو رقم العنصر */
    private const USAGE_MAP = [
        ['table' => 'lesson_attachments', 'column' => 'media_item_id', 'by' => 'id', 'label' => 'مرفقات الدروس'],
        ['table' => 'courses', 'column' => 'cover_path', 'by' => 'path', 'label' => 'أغلفة التدريبات'],
        ['table' => 'learning_paths', 'column' => 'cover_path', 'by' => 'path', 'label' => 'أغلفة المسارات'],
        ['table' => 'certificate_templates', 'column' => 'background_path', 'by' => 'path', 'label' => 'خلفيّات قوالب الشهادات'],
        ['table' => 'certificate_accreditations', 'column' => 'logo_path', 'by' => 'path', 'label' => 'شعارات الاعتمادات'],
        ['table' => 'announcements', 'column' => 'media_path', 'by' => 'path', 'label' => 'وسائط التعليمات'],
    ];

    /**
     * رفع ملفّ إلى المكتبة.
     *
     * @return array{item: MediaItem, duplicated: bool} — و`duplicated` تُظهر رسالة
     *                                                  «هذا الملفّ موجود — استُخدمت النسخة الحاليّة» (24.1)
     */
    public function store(UploadedFile $file, ?User $uploader = null, ?string $folder = null, array $tags = []): array
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if (setting('media.dedup.enabled', true)) {
            $existing = MediaItem::query()->where('hash', $hash)->first();

            if ($existing) {
                return ['item' => $existing, 'duplicated' => true];
            }
        }

        $disk = (string) setting('media.storage.disk', 'public');
        $path = $file->store((string) setting('media.storage.directory', 'media'), $disk);

        $item = MediaItem::create([
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName() ?: basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'hash' => $hash,
            'tags' => $this->cleanTags($tags),
            'folder' => $folder ?: null,
            'uploaded_by' => $uploader?->id,
        ]);

        return ['item' => $item, 'duplicated' => false];
    }

    /** هل الملفّ مسموح نوعًا وحجمًا؟ القيم من الإعدادات لا من الكود (2.13). */
    public function rules(): array
    {
        $extensions = (array) setting('media.upload.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'mp3', 'wav', 'mp4']);
        $maxKb = (int) setting('media.upload.max_kb', 10240);

        return ['required', 'file', 'mimes:'.implode(',', $extensions), 'max:'.$maxKb];
    }

    /**
     * قائمة المكتبة: بحث بالاسم + فلاتر النوع/المجلّد/الوسم/«غير مستخدَم» (24.1).
     *
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters = []): LengthAwarePaginator
    {
        $query = MediaItem::query()->with('uploaded_by')->latest('id');

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }

        if (($kind = (string) ($filters['kind'] ?? '')) !== '') {
            $query->where(function ($inner) use ($kind) {
                foreach ($this->mimePrefixes($kind) as $prefix) {
                    $inner->orWhere('mime', 'like', $prefix.'%');
                }
            });
        }

        if (($folder = (string) ($filters['folder'] ?? '')) !== '') {
            $query->where('folder', $folder);
        }

        if (($tag = (string) ($filters['tag'] ?? '')) !== '') {
            $query->where('tags', 'like', '%"'.$tag.'"%');
        }

        if (! empty($filters['unused'])) {
            $used = $this->usedIds();
            $query->whereNotIn('id', $used->isEmpty() ? [0] : $used->all());
        }

        return $query->paginate((int) setting('media.grid.per_page', 24))->withQueryString();
    }

    /**
     * «مستخدَم في X مكان» بروابطه — ويُحذّر قبل الحذف (12.4-د).
     *
     * @return Collection<int, array{label: string, count: int}>
     */
    public function usage(MediaItem $item): Collection
    {
        $rows = collect();

        foreach (self::USAGE_MAP as $place) {
            $value = $place['by'] === 'id' ? $item->id : $item->path;
            $count = DB::table($place['table'])->where($place['column'], $value)->count();

            if ($count > 0) {
                $rows->push(['label' => $place['label'], 'count' => $count]);
            }
        }

        return $rows;
    }

    public function usageCount(MediaItem $item): int
    {
        return (int) $this->usage($item)->sum('count');
    }

    /** أرقام كلّ الملفّات المستعمَلة — لفلتر «غير مستخدَم». */
    public function usedIds(): Collection
    {
        $ids = collect();

        foreach (self::USAGE_MAP as $place) {
            $values = DB::table($place['table'])->whereNotNull($place['column'])->pluck($place['column']);

            $ids = $ids->merge($place['by'] === 'id'
                ? $values->map(fn ($v) => (int) $v)
                : MediaItem::query()->whereIn('path', $values)->pluck('id'));
        }

        return $ids->unique()->values();
    }

    /** المجلّدات المتاحة: الافتراضيّة من الإعدادات + ما أنشأه الأدمن فعلًا. */
    public function folders(): Collection
    {
        $defaults = collect((array) setting('media.folders.defaults', ['أغلفة', 'مرفقات', 'شهادات', 'شعارات']));

        return $defaults
            ->merge(MediaItem::query()->whereNotNull('folder')->distinct()->pluck('folder'))
            ->unique()
            ->values();
    }

    public function tags(): Collection
    {
        return MediaItem::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => is_array($tags) ? $tags : (json_decode((string) $tags, true) ?: []))
            ->unique()
            ->values();
    }

    public function url(MediaItem $item): string
    {
        return Storage::disk($item->disk ?: 'public')->url($item->path);
    }

    /** @return array<int, string> */
    public function cleanTags(array|string|null $tags): array
    {
        $list = is_string($tags) ? explode(',', $tags) : (array) $tags;

        return collect($list)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->take((int) setting('media.tags.max_per_item', 8))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function mimePrefixes(string $kind): array
    {
        return match ($kind) {
            'image' => ['image/'],
            'audio' => ['audio/'],
            'video' => ['video/'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/vnd.openxmlformats'],
            default => [$kind],
        };
    }
}
