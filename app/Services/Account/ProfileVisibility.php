<?php

namespace App\Services\Account;

use App\Models\Membership;
use App\Models\User;
use App\Models\UserPrivacySetting;

/**
 * مستويات المشاهدة الأربعة (الدستور 10.0-ج · 13.4-م):
 * صاحب البروفايل · زميل · أبلاين مخوَّل · أدمن.
 *
 * القاعدة العليا: أيّ حقل حسّاس **مخفيّ افتراضيًّا**، ولا يظهر إلّا بمستوى
 * مشاهدة يسمح به **مع** إعداد خصوصيّة صاحبه.
 */
class ProfileVisibility
{
    public const OWNER = 'owner';

    public const PEER = 'peer';

    public const UPLINE = 'upline';

    public const ADMIN = 'admin';

    /**
     * كاش **لكلّ طلب** لإعدادات خصوصيّة صاحب البروفايل.
     *
     * ⚠️ كان مفهرَسًا بلا أيّ إبطال، فلو عاش الكيان أطول من الطلب (Octane أو
     * عامل طوابير طويل العمر) بقيت خصوصيّةٌ بائتة تُظهِر حقلًا أقفله صاحبه
     * لحظتها. فنربطه ببصمة الطلب الجاري: طلبٌ جديد ⟵ كاشٌ جديد.
     */
    private array $settingsCache = [];

    private ?string $cacheFingerprint = null;

    /**
     * مستوى مشاهدة هذا الزائر لهذا البروفايل — **أو `null`** لمن هو خارج
     * المستويات الأربعة.
     *
     * ⚠️ كان الزائر **بلا حساب** يُصنَّف «زميلًا»، فصارت مستويات 10.0-ج الأربعة
     * خمسةً بابُها مفتوحٌ للإنترنت. والنصّ يعدّ أربعةً لا غير (صاحبه · زميل ·
     * أبلاين مخوَّل · أدمن) — وكلّها **مستويات لمن له حساب**؛ ومَن ليس منها لا
     * يرى إلّا ما هو **عامّ دائمًا** (المحافظة — 12.14-د).
     */
    public function levelFor(?User $viewer, User $owner): ?string
    {
        if (! $viewer) {
            return null;
        }

        if ($viewer->id === $owner->id) {
            return self::OWNER;
        }

        if ($viewer->isPlatformOwner() || $viewer->allows('users.view', $owner)) {
            return self::ADMIN;
        }

        // الأبلاين **المخوَّل** فقط: العلاقة التنظيميّة وحدها لا تكفي (13.4-م)
        if ($this->isUplineOf($viewer, $owner) && $viewer->allows('volunteers.view', $owner)) {
            return self::UPLINE;
        }

        return self::PEER;
    }

    /** هل يرى هذا الزائر هذا الحقل؟ */
    public function canSee(string $field, ?User $viewer, User $owner, ?string $level = null): bool
    {
        // ⭐ المحافظة عامّة دائمًا ولا يجوز إخفاؤها (12.14-د)
        if (in_array($field, PrivacyFields::ALWAYS_PUBLIC, true)) {
            return true;
        }

        $level ??= $this->levelFor($viewer, $owner);

        /*
         | خارج المستويات الأربعة (زائرٌ بلا حساب): لا حقل إلّا العامّ دائمًا.
         | وخيارات الإظهار الثلاثة نفسها لا تعرفه — «كلّ **المستخدمين**» و«كلّ
         | **المتطوّعين**» و«مشرفيني» كلّها أصحاب حسابات (13.4-م-2).
         */
        if ($level === null) {
            return false;
        }

        // صاحبه والأدمن والأبلاين المخوَّل: يرون بيانات التواصل بلا موافقة (13.4-م)
        if (in_array($level, [self::OWNER, self::ADMIN, self::UPLINE], true)) {
            return true;
        }

        return match ($this->visibilityOf($owner, $field)) {
            'all_users' => true,
            'all_volunteers' => (bool) $viewer?->isVolunteer(),
            default => false, // مشرفيني فقط — والزميل ليس مشرفًا
        };
    }

    /** إسقاط الكاش — يُستدعى فور تعديل صاحبه لإعدادات خصوصيّته */
    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->settingsCache = [];

            return;
        }

        unset($this->settingsCache[$userId]);
    }

    /** إعداد الإظهار المحفوظ لهذا الحقل، وإلّا فالافتراضيّ (الحسّاس مقفول) */
    public function visibilityOf(User $owner, string $field): string
    {
        $fingerprint = spl_object_hash(request());

        // الكاش صالحٌ داخل الطلب الواحد فقط — وأيّ طلبٍ جديد يبدأ من الصفر
        if ($this->cacheFingerprint !== $fingerprint) {
            $this->settingsCache = [];
            $this->cacheFingerprint = $fingerprint;
        }

        if (! isset($this->settingsCache[$owner->id])) {
            $this->settingsCache[$owner->id] = UserPrivacySetting::query()
                ->where('user_id', $owner->id)
                ->pluck('visibility', 'field')
                ->all();
        }

        $stored = $this->settingsCache[$owner->id][$field] ?? null;

        if ($stored === null || ! in_array($stored, PrivacyFields::VISIBILITIES, true)) {
            return PrivacyFields::defaultVisibility($field);
        }

        return $stored;
    }

    /** هل الزائر في سلسلة الأبلاين فوق صاحب البروفايل (لأيّ مستوى)؟ */
    public function isUplineOf(User $viewer, User $owner): bool
    {
        $viewerMembershipIds = Membership::query()
            ->where('user_id', $viewer->id)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        if ($viewerMembershipIds === []) {
            return false;
        }

        $current = Membership::query()
            ->where('user_id', $owner->id)
            ->where('status', 'active')
            ->get(['id', 'upline_id']);

        $guard = 0;

        while ($current->isNotEmpty() && $guard++ < 50) {
            $uplineIds = $current->pluck('upline_id')->filter()->unique()->values()->all();

            if ($uplineIds === []) {
                return false;
            }

            if (array_intersect($uplineIds, $viewerMembershipIds) !== []) {
                return true;
            }

            $current = Membership::query()->whereIn('id', $uplineIds)->get(['id', 'upline_id']);
        }

        return false;
    }

    /**
     * نصّ عربيّ يشرح مستوى المشاهدة — يظهر لصاحب البروفايل ليطمئنّ (13.4-م).
     *
     * ⚠️ كان يقول لصاحبه «بتشوف كلّ حاجة» بينما **الملاحظات الإداريّة محجوبة
     * عنه خادميًّا** — نصٌّ يكذب على المستخدم. والصيغة الصحيحة هي عينها التي
     * في `ViewerLevel::label()`، فنقرأ **نفس مفاتيحها** ولا نكتب صيغةً ثالثة.
     */
    public function levelLabel(?string $level): string
    {
        return match ($level) {
            self::OWNER => (string) setting('volunteer.profile.level.owner', 'دي صفحتك — بتشوف كلّ حاجة عدا الملاحظات الإداريّة'),
            self::ADMIN => (string) setting('volunteer.profile.level.admin', 'مشاهدة إداريّة'),
            self::UPLINE => (string) setting('volunteer.profile.level.upline', 'مشاهدة مشرف'),
            default => (string) setting('volunteer.profile.level.peer', 'المشاهدة العامّة'),
        };
    }
}
