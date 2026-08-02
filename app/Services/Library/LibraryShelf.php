<?php

namespace App\Services\Library;

use App\Models\Bundle;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LibraryEntitlement;
use App\Models\Product;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * بناء «الرفّ» (20.1): كلّ ما يملكه المستخدم في مصدرٍ واحد.
 *
 * لماذا نجمع الشهادات هنا من جدولها لا من الإتاحات: لأنّ الدستور يفرض
 * «مصدرًا واحدًا» للشهادات بين مكتبتي والبروفايل وصفحة التحقّق — صفر ازدواج (20.1).
 */
class LibraryShelf
{
    /** التابات المعتمَدة بترتيبها (24.5) */
    public const TABS = ['all', 'courses', 'paths', 'bundles', 'products', 'certificates'];

    public function __construct(private readonly EntitlementGuard $guard) {}

    /** كلّ عناصر المكتبة كصفٍّ موحّد قابل للفلترة والفرز */
    public function items(User $user): Collection
    {
        return $this->fromEntitlements($user)
            ->concat($this->fromCertificates($user))
            ->values();
    }

    /** عدّاد كلّ تاب (20.1) */
    public function counts(Collection $items): array
    {
        $counts = ['all' => $items->count()];

        foreach (self::TABS as $tab) {
            if ($tab !== 'all') {
                $counts[$tab] = $items->where('tab', $tab)->count();
            }
        }

        return $counts;
    }

    /** بحث + فلتر النوع + فرز — ثلاثة ظاهرة والباقي مطويّ (2.15-أ-4) */
    public function filter(Collection $items, array $input): Collection
    {
        $tab = $input['tab'] ?? 'all';
        $search = trim((string) ($input['q'] ?? ''));
        $type = (string) ($input['type'] ?? '');
        $sort = (string) ($input['sort'] ?? setting('library.shelf.default_sort', 'recent'));
        $currency = (string) ($input['currency'] ?? '');

        $result = $items
            ->when($tab !== 'all', fn (Collection $c) => $c->where('tab', $tab))
            ->when($type !== '', fn (Collection $c) => $c->where('icon', $type))
            ->when($currency !== '', fn (Collection $c) => $c->where('currency', $currency))
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn (array $item) => mb_stripos($item['title'], $search) !== false,
            ));

        return match ($sort) {
            'name' => $result->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'used' => $result->sortByDesc('used_at')->values(),
            default => $result->sortByDesc('acquired_at')->values(),
        };
    }

    /** أنواع الفرز الثلاثة (20.1) */
    public function sortOptions(): array
    {
        return [
            'recent' => (string) setting('library.sort.recent_label', 'الأحدث'),
            'name' => (string) setting('library.sort.name_label', 'الاسم'),
            'used' => (string) setting('library.sort.used_label', 'الأكثر استخدامًا'),
        ];
    }

    /** قاموس أيقونات النوع — مقفول (2.16-ج) */
    public function typeOptions(): array
    {
        return [
            'course' => (string) setting('library.type.course_label', 'تدريب'),
            'path' => (string) setting('library.type.path_label', 'مسار'),
            'bundle' => (string) setting('library.type.bundle_label', 'بندل'),
            'pdf' => (string) setting('library.type.pdf_label', 'PDF'),
            'video' => (string) setting('library.type.video_label', 'فيديو'),
            'audio' => (string) setting('library.type.audio_label', 'صوت'),
            'html' => (string) setting('library.type.html_label', 'HTML'),
            'certificate' => (string) setting('library.type.certificate_label', 'شهادة'),
        ];
    }

    // ------------------------------------------------------------------ داخليّ

    private function fromEntitlements(User $user): Collection
    {
        $entitlements = LibraryEntitlement::query()
            ->where('user_id', $user->id)
            ->with(['itemable', 'order.currency'])
            ->get();

        $readAt = ReadingProgress::query()
            ->where('user_id', $user->id)
            ->pluck('updated_at', 'product_id');

        return $entitlements
            ->filter(fn (LibraryEntitlement $e) => $e->itemable !== null)
            ->map(function (LibraryEntitlement $e) use ($readAt) {
                $item = $e->itemable;
                $shape = $this->shapeOf($item);

                return [
                    'key' => 'entitlement:'.$e->id,
                    'entitlement_id' => $e->id,
                    'tab' => $shape['tab'],
                    'icon' => $shape['icon'],
                    'title' => $shape['title'],
                    'thumb' => $this->thumb($shape['cover']),
                    'action_label' => $shape['action_label'],
                    'action_url' => $shape['action_url'],
                    'is_protected' => $shape['is_protected'],
                    'availability' => $this->guard->availability($e),
                    'available' => $this->guard->isAvailableNow($e),
                    'acquired_at' => $e->created_at,
                    'used_at' => $item instanceof Product
                        ? ($readAt[$item->id] ?? $e->created_at)
                        : $e->created_at,
                    'currency' => $e->order?->currency?->code ?? '',
                    'order' => $e->order,
                ];
            })
            ->values();
    }

    private function fromCertificates(User $user): Collection
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->with('certificate_type')
            ->get()
            ->map(function (Certificate $certificate) {
                return [
                    'key' => 'certificate:'.$certificate->id,
                    'entitlement_id' => null,
                    'tab' => 'certificates',
                    'icon' => 'certificate',
                    'title' => $certificate->certificate_type?->name_ar
                        ?? (string) setting('library.certificate.fallback_title', 'شهادة'),
                    'thumb' => null,
                    'action_label' => (string) setting('library.action.download_label', 'تحميل'),
                    'action_url' => $this->routeOr('verify.certificate', ['code' => $certificate->code]),
                    'is_protected' => false,
                    // الشهادة وسامٌ على الرفّ (Peak-End) — وحالتها من حالتها هي
                    'availability' => [
                        'state' => $certificate->status === 'valid' ? 'honor' : 'idle',
                        'label' => $certificate->status === 'valid'
                            ? (string) setting('library.certificate.valid_label', 'سارية')
                            : (string) setting('library.certificate.invalid_label', 'غير سارية'),
                    ],
                    'available' => $certificate->status === 'valid',
                    'acquired_at' => $certificate->issued_at,
                    'used_at' => $certificate->issued_at,
                    'currency' => '',
                    'order' => null,
                ];
            })
            ->values();
    }

    /**
     * شكل العنصر حسب نوعه: الأيقونة والزرّ الرئيسيّ الواحد (20.1).
     *
     * @return array{tab:string,icon:string,title:string,cover:?string,action_label:string,action_url:string,is_protected:bool}
     */
    private function shapeOf(Model $item): array
    {
        if ($item instanceof Course) {
            return [
                'tab' => 'courses', 'icon' => 'course', 'title' => $item->name_ar,
                'cover' => $item->cover_path, 'is_protected' => false,
                'action_label' => (string) setting('library.action.open_label', 'فتح'),
                'action_url' => $this->routeOr('learning.course', ['course' => $item->slug], 'learning.courses'),
            ];
        }

        if ($item instanceof LearningPath) {
            return [
                'tab' => 'paths', 'icon' => 'path', 'title' => $item->name_ar,
                'cover' => $item->cover_path, 'is_protected' => false,
                'action_label' => (string) setting('library.action.open_label', 'فتح'),
                'action_url' => $this->routeOr('learning.path', ['path' => $item->slug], 'learning.paths'),
            ];
        }

        if ($item instanceof Bundle) {
            return [
                'tab' => 'bundles', 'icon' => 'bundle', 'title' => $item->name_ar,
                'cover' => $item->cover_path, 'is_protected' => false,
                'action_label' => (string) setting('library.action.open_label', 'فتح'),
                'action_url' => $this->routeOr('store.bundle', ['bundle' => $item->slug], 'store.bundles'),
            ];
        }

        if ($item instanceof Product) {
            return $this->shapeOfProduct($item);
        }

        return [
            'tab' => 'products', 'icon' => 'html', 'title' => (string) ($item->name_ar ?? $item->name ?? ''),
            'cover' => null, 'is_protected' => false,
            'action_label' => (string) setting('library.action.open_label', 'فتح'),
            'action_url' => '#',
        ];
    }

    private function shapeOfProduct(Product $product): array
    {
        // PDF محميّ (Flip-only): يُقرأ داخل الموقع فقط — بلا تحميل ولا رابط مباشر (20.2)
        if ($product->type === 'protected_pdf' || ! $product->is_downloadable) {
            return [
                'tab' => 'products', 'icon' => 'pdf', 'title' => $product->name_ar,
                'cover' => $product->cover_path, 'is_protected' => true,
                'action_label' => (string) setting('library.action.read_label', 'قراءة'),
                'action_url' => route('library.read', $product),
            ];
        }

        $extension = mb_strtolower(pathinfo((string) $product->file_path, PATHINFO_EXTENSION));

        [$icon, $label] = match (true) {
            in_array($extension, ['mp4', 'webm', 'mov'], true) => ['video', (string) setting('library.action.play_label', 'تشغيل')],
            in_array($extension, ['mp3', 'wav', 'ogg', 'm4a'], true) => ['audio', (string) setting('library.action.play_label', 'تشغيل')],
            in_array($extension, ['html', 'htm', 'zip'], true) => ['html', (string) setting('library.action.open_label', 'فتح')],
            default => ['pdf', (string) setting('library.action.download_label', 'تحميل')],
        };

        return [
            'tab' => 'products', 'icon' => $icon, 'title' => $product->name_ar,
            'cover' => $product->cover_path, 'is_protected' => false,
            'action_label' => $label,
            'action_url' => $product->file_path ? Storage::disk('public')->url($product->file_path) : '#',
        ];
    }

    private function thumb(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * أثناء البناء التدريجيّ: مسارُ مجالٍ آخر غيرُ موجودٍ — أو بتوقيعٍ مختلف —
     * لا يكسر الرفّ، بل نهبط للبديل الأوسع.
     */
    private function routeOr(string $name, array $params = [], ?string $fallback = null): string
    {
        if (Route::has($name)) {
            try {
                return route($name, $params);
            } catch (\Throwable) {
                // نكمل إلى البديل
            }
        }

        if ($fallback && Route::has($fallback)) {
            try {
                return route($fallback);
            } catch (\Throwable) {
                return '#';
            }
        }

        return '#';
    }
}
