<?php

namespace App\Services\Volunteer\Org;

use App\Models\Membership;
use App\Models\User;

/**
 * العنصر الشرفيّ «أخوكم» (13.4-ص) — **شرفيّ بحت بلا أثر على أيّ شيء**.
 *
 * كانت إعداداته اثنين فقط (تفعيل + الوصف العربيّ)، والدستور (13.4-ص-د) ينصّ
 * على خمسة: **تفعيل · اختيار الحساب · الوصف بنسختيه (ع/إ) · أماكن الظهور ·
 * شكل الإطار** — و**التعديل لمالك المنصّة وحده 🔒 مع Audit**.
 *
 * ولماذا خدمةٌ مستقلّة؟ لأنّ العنصر يظهر في ثلاثة أماكن (الكانفاس · الأعضاء ·
 * الصفحة التعريفيّة) ويجب أن يقرأ ثلاثتها **مصدرًا واحدًا**، وإلّا اختلف
 * ظهوره من شاشة لأخرى وصار الإعداد بلا أثر (2.13).
 */
final class HonoraryElement
{
    /** أماكن الظهور الثلاثة لا رابع لها (13.4-ص-ب) */
    public const PLACES = [
        'canvas' => 'الهيكل التنظيميّ (الكانفاس)',
        'members' => 'صفحة الأعضاء والبوزشنز',
        'landing' => 'صفحة التطوّع التعريفيّة',
    ];

    /** أشكال الإطار المتاحة — قائمة مقفولة فلا يُحقن CSS من إعداد */
    public const FRAMES = [
        'soft' => 'إطار هادئ',
        'gold' => 'إطار ذهبيّ',
        'dashed' => 'إطار متقطّع',
        'none' => 'بلا إطار',
    ];

    public function enabled(): bool
    {
        return (bool) setting('volunteer.honorary.enabled', false);
    }

    /** الوصف بنسختيه — والافتراضيّ «أخوكم» (13.4-ص-د) */
    public function label(string $lang = 'ar'): string
    {
        return $lang === 'en'
            ? (string) setting('volunteer.honorary.label_en', 'Your brother')
            : (string) setting('volunteer.honorary.label_ar', 'أخوكم');
    }

    /** @return array<string, bool> */
    public function places(): array
    {
        $raw = setting('volunteer.honorary.places', []);
        $raw = is_array($raw) ? $raw : [];

        $out = [];

        foreach (array_keys(self::PLACES) as $place) {
            // الافتراضيّ: الكانفاس والأعضاء نعم، والصفحة التعريفيّة اختياريّة (13.4-ص-ب)
            $out[$place] = array_key_exists($place, $raw)
                ? (bool) $raw[$place]
                : $place !== 'landing';
        }

        return $out;
    }

    public function showsOn(string $place): bool
    {
        return $this->enabled() && ($this->places()[$place] ?? false);
    }

    public function frame(): string
    {
        $frame = (string) setting('volunteer.honorary.frame_style', 'soft');

        return array_key_exists($frame, self::FRAMES) ? $frame : 'soft';
    }

    /**
     * الحساب المرتبط: **اختيارٌ صريح** في الإعدادات، وافتراضيًّا **حساب مالك
     * المنصّة** (13.4-ص-د) — ثمّ عضويّة البوزشن الشرفيّ إن وُجدت.
     *
     * @return array{user:User,label:string,label_en:string,frame:string}|null
     */
    public function resolve(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $user = $this->account();

        if (! $user) {
            return null;
        }

        return [
            'user' => $user,
            'label' => $this->label(),
            'label_en' => $this->label('en'),
            'frame' => $this->frame(),
        ];
    }

    public function account(): ?User
    {
        $chosen = (int) setting('volunteer.honorary.user_id', 0);

        if ($chosen > 0 && $user = User::query()->find($chosen)) {
            return $user;
        }

        $membership = Membership::query()
            ->whereHas('position', fn ($q) => $q->where('is_honorary', true))
            ->whereIn('status', ['active', 'suspended'])
            ->with('user')
            ->first();

        return $membership?->user ?? $this->platformOwner();
    }

    private function platformOwner(): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('key', (string) config('access.owner_role')))
            ->first();
    }
}
