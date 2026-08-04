<?php

namespace App\Services\Images;

use App\Models\ImageTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * ⭐ التوليد الجماعيّ: قالب + شريحة ⟵ ملفّ ZIP بكلّ الصور (12.14-و).
 *
 * الشريحة تُبنى من بياناتنا (قسم · دور · أوائل الليدر بورد) لا من قائمة يدويّة،
 * وحجمها محكوم بإعداد حتى لا تُفجَّر الذاكرة بضغطة واحدة.
 */
class ImageBatchExporter
{
    public function __construct(private readonly ImageRenderer $renderer) {}

    /** @return array<string,string> */
    public function segments(): array
    {
        return [
            'top_xp' => setting('images.image_batch_exporter.segments_1', 'أوائل الليدر بورد (XP)'),
            'volunteers' => setting('images.image_batch_exporter.segments_2', 'المتطوّعون النشطون'),
            'active' => setting('images.image_batch_exporter.segments_3', 'الحسابات المفعَّلة'),
        ];
    }

    /** @return Collection<int, User> */
    public function resolveSegment(string $segment, int $limit): Collection
    {
        $limit = max(1, min($limit, (int) setting('images.batch.max_users', 200)));

        return match ($segment) {
            'volunteers' => User::query()
                ->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
                ->limit($limit)->get(),
            'active' => User::query()->where('status', 'active')->limit($limit)->get(),
            default => User::query()->orderByDesc('xp')->limit($limit)->get(),
        };
    }

    /** يبني الأرشيف ويرجع مساره على قرص `public` */
    public function build(ImageTemplate $template, Collection $users, ?User $actor = null): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(setting('images.image_batch_exporter.build_1', 'امتداد ZIP مش متاح على الخادم.'));
        }

        $relative = 'generated/batches/'.$template->id.'-'.now()->format('Ymd-His').'.zip';
        $full = Storage::disk('public')->path($relative);

        Storage::disk('public')->makeDirectory(dirname($relative));

        $zip = new ZipArchive;

        if ($zip->open($full, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(setting('images.image_batch_exporter.build_2', 'تعذّر إنشاء ملفّ ZIP.'));
        }

        foreach ($users as $user) {
            $path = $this->renderer->render($template, $user, $actor);
            $zip->addFile(Storage::disk('public')->path($path), $user->code.'.png');
        }

        $zip->close();

        return $relative;
    }
}
