<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\ImageTemplate;
use App\Services\Images\BoardImageRenderer;
use App\Services\Images\BoardSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * زرّ [استخراج كصورة] — مسار الخادم الواحد لكلّ لوحات المنصّة (12.14-هـ).
 *
 * ⭐ «في أيّ ليدربورد يكون فيه زرّ استخراج كصورة» و«أيّ لوحات عمومًا قابلة
 *   للاستخراج كصورة» — فمكوّن واحد `<x-export-image>` ومسار واحد هنا،
 *   ولا نسخة ثانية من أيّ حساب (صفر ازدواج).
 *
 * **الأمان:** الحمولة في الرابط **موقَّعة** بمفتاح التطبيق، فلا يستطيع أحد
 * تلفيق لوحة باسم المنصّة. وخيارات المستخدم (المقاس · القالب · عدد الصفوف ·
 * الأفاتارات) تُستثنى من التوقيع لأنّها لا تُغيّر البيانات بل شكلها.
 *
 * **الصلاحيّة:** `image_export.use` على المسار، والزرّ نفسه **يُخفى** لمن لا
 * يملكها — لا يُعطَّل (2.15-أ-7).
 */
class ExportImageController extends Controller
{
    /** خيارات لا تُحتسَب في التوقيع لأنّها شكليّة بحتة */
    private const UNSIGNED = ['size', 'top', 'template', 'avatars', 'frame', 'download'];

    public function __construct(private readonly BoardImageRenderer $renderer) {}

    public function __invoke(Request $request): Response
    {
        if (! URL::hasValidSignature($request, true, self::UNSIGNED)) {
            abort(403, (string) setting('images.export.invoke_denied', 'الرابط ده مش صالح — ارجع للوحة واضغط [استخراج كصورة] من جديد.'));
        }

        $snapshot = BoardSnapshot::decode((string) $request->query('d', ''));
        $actor = $request->user();

        // عدد الصفوف: أفضل 10 · أفضل 3 · صفّي أنا (12.14-هـ)
        $top = (string) $request->query('top', 'top10');
        $rows = match ($top) {
            'me' => $snapshot->myRows($actor?->id),
            'top3' => array_slice($snapshot->rows, 0, 3),
            default => array_slice($snapshot->rows, 0, (int) setting('images.export.default_rows', 10)),
        };

        $normalized = (new BoardSnapshot($snapshot->kind, $snapshot->title, $snapshot->subtitle, $rows))
            ->normalizedRows(count($rows));

        $options = $this->options($request);
        $template = $this->template($request, $actor);

        try {
            $path = $this->renderer->cached($snapshot, $normalized, $options, $template, $actor);
        } catch (RuntimeException $e) {
            // رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب)
            return response($e->getMessage(), 429);
        }

        $name = trim($snapshot->title) !== '' ? $snapshot->title : (string) setting('images.export.invoke_msg', 'لوحة');
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => $disposition.'; filename="'.$this->asciiName($name).'.png"; filename*=UTF-8\'\''.rawurlencode($name.'.png'),
            'Cache-Control' => 'private, max-age='.(int) setting('images.preview.cache_seconds', 60),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array{width:int,height:int,avatars:bool,frame:string} */
    private function options(Request $request): array
    {
        $presets = $this->renderer->presets();
        $key = (string) $request->query('size', 'square');
        $preset = $presets[$key] ?? reset($presets);

        return [
            'width' => (int) ($preset['width'] ?? 1080),
            'height' => (int) ($preset['height'] ?? 1080),
            'avatars' => $request->query('avatars', '1') !== '0',
            // ⭐ رفع الطبقة لأعلى أو إنزالها لأسفل — الفريم فوق اللوحة أو تحتها
            'frame' => $request->query('frame') === 'below' ? 'below' : 'above',
        ];
    }

    /** القالب المتاح للمستخدم فقط (12.14-ز): الجمهور يحكم ما يظهر له */
    private function template(Request $request, $actor): ?ImageTemplate
    {
        $id = (int) $request->query('template', 0);

        if ($id <= 0) {
            return null;
        }

        $template = ImageTemplate::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->find($id);

        if (! $template) {
            return null;
        }

        return self::visibleTo($template, $actor) ? $template : null;
    }

    /** جمهور القالب: خاصّ بالإدارة · للمتطوّعين · للجميع (12.14-ز) */
    public static function visibleTo(ImageTemplate $template, $actor): bool
    {
        return match ($template->audience) {
            'everyone' => true,
            'volunteers' => (bool) $actor?->isVolunteer(),
            default => (bool) $actor?->can('image_templates.list'),
        };
    }

    private function asciiName(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: '';

        return trim($ascii, '-') !== '' ? trim($ascii, '-') : 'board';
    }
}
