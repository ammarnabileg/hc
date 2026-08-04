<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\BadgeUser;
use App\Models\User;
use App\Services\Ui\UndoStack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * تعزيزات البروفايل الناقصة (الدستور 10):
 * **النبذة (Bio)** · **زرّ المشاركة** · **قسم الشارات**.
 */
class ProfileExtrasController extends Controller
{
    public function __construct(private readonly UndoStack $undo) {}

    /**
     * حفظ النبذة — تلقائيّ مع «اتحفظ ✓» بجوار الحقل (2.17-ب).
     *
     * ⭐ وهو **فعل قابل للتراجع**، فيُنفَّذ فورًا بلا تأكيد ومعه رمز تراجع
     *   يعمل خلال المهلة (2.15-د) — لا نافذة «هل أنت متأكّد؟».
     */
    public function updateBio(Request $request): JsonResponse
    {
        $max = max(20, (int) setting('profile.bio.max_chars', 280));

        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:'.$max],
        ], [
            'bio.max' => strtr((string) setting('profile.extras.update_bio_msg', 'النبذة أطول من :a1 حرف — اختصرها شويّة وجرّب تاني.'), [':a1' => (string) ($max)]),
        ], ['bio' => (string) setting('profile.extras.update_bio_msg_2', 'النبذة')]);

        $user = $request->user();
        $token = $this->undo->capture($user, $user, ['bio'], (string) setting('profile.extras.update_bio_msg_3', 'تعديل النبذة'));

        $user->forceFill(['bio' => trim((string) ($data['bio'] ?? '')) ?: null])->save();

        return response()->json([
            'saved' => true,
            'label' => (string) setting('profile.bio.saved_label', 'اتحفظ ✓'),
            'undo_token' => $token,
            'undo_seconds' => $this->undo->seconds(),
        ]);
    }

    /**
     * قسم الشارات (10): المفعَّلة بشكل مميّز وغير المفعَّلة **مقفولة** —
     * وشرط الفتح مكتوب صراحةً لا لغزًا.
     *
     * @return array{earned:Collection,locked:Collection}
     */
    public static function badgesFor(User $owner): array
    {
        $earned = BadgeUser::query()
            ->with('badge')
            ->where('user_id', $owner->id)
            ->get()
            ->filter(fn (BadgeUser $row) => (bool) $row->badge)
            ->keyBy(fn (BadgeUser $row) => (int) $row->badge_id);

        $all = Badge::query()->where('is_active', true)->orderBy('id')->get();

        return [
            'earned' => $all->filter(fn (Badge $b) => $earned->has((int) $b->id))->values(),
            'locked' => $all->reject(fn (Badge $b) => $earned->has((int) $b->id))->values(),
            'awarded_at' => $earned->map(fn (BadgeUser $row) => $row->awarded_at),
        ];
    }

    /**
     * روابط المشاركة (10) — تيليجرام · X · فيسبوك · واتساب + الرابط العامّ.
     * تُبنى على الخادم فلا تتسرّب صياغة خاطئة لرابط عامّ.
     *
     * @return array<int, array{key:string,label:string,url:string}>
     */
    public static function shareLinks(User $owner): array
    {
        $url = $owner->profileUrl();
        $text = trim((string) setting('profile.share.text', 'شوف بروفايلي على :platform'));
        $text = str_replace(':platform', (string) setting('platform.identity.name', config('app.name')), $text);

        $encodedUrl = rawurlencode($url);
        $encodedText = rawurlencode($text);

        return [
            ['key' => 'telegram', 'label' => (string) setting('profile.extras.share_links_msg', 'تيليجرام'), 'url' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedText}"],
            ['key' => 'x', 'label' => 'X', 'url' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedText}"],
            ['key' => 'facebook', 'label' => (string) setting('profile.extras.share_links_msg_2', 'فيسبوك'), 'url' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}"],
            ['key' => 'whatsapp', 'label' => (string) setting('profile.extras.share_links_msg_3', 'واتساب'), 'url' => "https://wa.me/?text={$encodedText}%20{$encodedUrl}"],
        ];
    }
}
