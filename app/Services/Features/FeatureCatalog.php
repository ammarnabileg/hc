<?php

namespace App\Services\Features;

/**
 * كتالوج المزايا: **المفتاح ⟵ مجموعته ⟵ مسارات المنصّة التي يحكمها** (24.3).
 *
 * لماذا هنا لا في القاعدة؟ لأنّ **ربط الميزة بمسارها قرارُ كودٍ لا قرارُ مالك**:
 * المالك يقرّر «هل تشتغل؟» و«مَن يراها؟» و«بأيّ نصّ؟» — أمّا «أيّ مسارٍ يخصّها»
 * فحقيقةٌ عن الكود نفسه، ولو صارت حقلًا في شاشة لأمكن فكُّ الربط من الشاشة
 * فيبقى المفتاح يلمع والمسار مفتوح — وهو عين العطب: «إعدادٌ بلا أثر أسوأ من
 * غيابه». والصفّ في `feature_flags` يحمل الحالة، وهذا يحمل الأثر.
 *
 * ⛔ ولا نصّ عربيّ في هذا الصنف: اللافتات في `feature_flags.label_ar`
 *    وأسماء المجموعات في `setting('features.groups')` — 2.13.
 */
class FeatureCatalog
{
    /** مجموعات 24.3 التسع — مفاتيحها إنجليزيّة ولافتاتها من الإعدادات */
    public const GROUPS = [
        'training',
        'gamification',
        'wars',
        'store',
        'library',
        'volunteer',
        'events',
        'guidance',
        'profile',
    ];

    /** «مَن يراها أثناء الإيقاف» — القيم الثلاث المنصوصة */
    public const VISIBILITY = ['none', 'admins', 'roles'];

    /** سلوك الميزة الموقوفة — و`''` يعني «يرث الإعداد العامّ» */
    public const BEHAVIORS = ['', 'hide', 'message'];

    /** أنواع الـOverride: دور أو شريحة جمهور */
    public const SCOPE_TYPES = ['role', 'segment'];

    /**
     * التعريفات: `key => [group, [route-name prefixes]]`.
     *
     * والبادئة تُطابَق **على حدود النقطة** لا كنصٍّ جزئيّ، فـ`library` لا تبتلع
     * `library_x`، و`learning.lesson.comments` تغلب `learning.lesson` لأنّها أطول.
     *
     * @return array<string, array{group:string, routes:list<string>}>
     */
    public static function definitions(): array
    {
        return [
            // ------------------------------------------------------ تدريب
            'training.courses' => ['group' => 'training', 'routes' => [
                'learning.courses', 'learning.course', 'learning.paths', 'learning.path',
            ]],
            'training.lessons' => ['group' => 'training', 'routes' => [
                'learning.lesson',
            ]],
            'training.lesson_comments' => ['group' => 'training', 'routes' => [
                'learning.lesson.comments',
            ]],
            'training.exams' => ['group' => 'training', 'routes' => [
                'exams',
            ]],
            'training.certificates' => ['group' => 'training', 'routes' => [
                'certificates', 'learning.certificates',
            ]],

            // ------------------------------------------------------ تلعيب
            'gamification.leaderboard' => ['group' => 'gamification', 'routes' => [
                'achievements.leaderboard',
            ]],
            'gamification.streak' => ['group' => 'gamification', 'routes' => [
                'achievements.streak',
            ]],
            'gamification.badges' => ['group' => 'gamification', 'routes' => [
                'achievements.badges',
            ]],
            'gamification.reward_questions' => ['group' => 'gamification', 'routes' => [
                'reward-questions',
            ]],
            'gamification.kudos' => ['group' => 'gamification', 'routes' => [
                'volunteer.kudos',
            ]],

            // ------------------------------------------------------ حروب
            'wars.board' => ['group' => 'wars', 'routes' => [
                'challenges.index', 'challenges.mine', 'challenges.leaderboard',
            ]],
            'wars.focus' => ['group' => 'wars', 'routes' => [
                'challenges.focus',
            ]],
            'wars.matches' => ['group' => 'wars', 'routes' => [
                'challenges.arena', 'challenges.ready', 'challenges.unready', 'challenges.fighters',
                'challenges.duel', 'challenges.play', 'challenges.answer', 'challenges.state',
                'challenges.submit', 'challenges.withdraw', 'challenges.result',
            ]],

            // ------------------------------------------- متجر وماليّات
            'store.storefront' => ['group' => 'store', 'routes' => [
                'store',
            ]],
            'store.wallet' => ['group' => 'store', 'routes' => [
                'wallet.index', 'wallet.tickets', 'wallet.transactions',
            ]],
            'store.topup' => ['group' => 'store', 'routes' => [
                'wallet.topup',
            ]],
            'store.transfer' => ['group' => 'store', 'routes' => [
                'wallet.transfer',
            ]],
            'store.withdraw' => ['group' => 'store', 'routes' => [
                'wallet.withdraw', 'wallet.withdrawals',
            ]],
            'store.exchange' => ['group' => 'store', 'routes' => [
                'wallet.exchange',
            ]],

            // ------------------------------------------------------ مكتبة
            'library.reader' => ['group' => 'library', 'routes' => [
                'library',
            ]],
            'library.cv' => ['group' => 'library', 'routes' => [
                'cv',
            ]],
            'library.attestations' => ['group' => 'library', 'routes' => [
                'attestations',
            ]],

            // ------------------------------------------------------ تطوّع
            'volunteer.panel' => ['group' => 'volunteer', 'routes' => [
                'volunteer', 'volunteering',
            ]],
            'volunteer.meetings' => ['group' => 'volunteer', 'routes' => [
                'volunteer.meetings',
            ]],
            'volunteer.tasks' => ['group' => 'volunteer', 'routes' => [
                'volunteer.tasks',
            ]],
            'volunteer.internal_library' => ['group' => 'volunteer', 'routes' => [
                'volunteer.library',
            ]],

            // ------------------------------------------------- فعاليّات
            'events.public' => ['group' => 'events', 'routes' => [
                'events',
            ]],

            // --------------------------------------------------- توجيه
            'guidance.announcements' => ['group' => 'guidance', 'routes' => [
                'announcements',
            ]],
            'guidance.help' => ['group' => 'guidance', 'routes' => [
                'help',
            ]],
            'guidance.complaints' => ['group' => 'guidance', 'routes' => [
                'complaints',
            ]],
            'guidance.notifications' => ['group' => 'guidance', 'routes' => [
                'notifications',
            ]],

            // ------------------------------------------------- بروفايل
            'profile.public' => ['group' => 'profile', 'routes' => [
                'u.profile', 'profile',
            ]],
            'profile.card' => ['group' => 'profile', 'routes' => [
                'card',
            ]],
            'profile.search' => ['group' => 'profile', 'routes' => [
                'search',
            ]],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function groupOf(string $key): ?string
    {
        return self::definitions()[$key]['group'] ?? null;
    }

    /**
     * ⭐ المسار ⟵ الميزة: **أطولُ بادئةٍ مطابقة** تفوز.
     *
     * ولماذا الأطول؟ لأنّ `learning.lesson.comments.store` يقع تحت بادئتين،
     * والأخصّ هو المقصود: إطفاء «تعليقات الدروس» لا إطفاء الدروس كلّها.
     * ولو أخذنا أوّلَ مطابقة لتغيّر الأثر بترتيب المصفوفة — وهو ما لا يُبنى
     * عليه حارس.
     */
    public static function featureForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        // ⛔ لوحة الإدارة خارج الحصر: لو أطفأ الأدمن ميزةً ثمّ أقفلت الشاشةُ
        // نفسَها لما أمكن إعادتها — بابٌ يُغلَق على مفتاحه.
        if (str_starts_with($routeName, 'admin.')) {
            return null;
        }

        $winner = null;
        $winnerLength = -1;

        foreach (self::definitions() as $key => $definition) {
            foreach ($definition['routes'] as $prefix) {
                if ($routeName !== $prefix && ! str_starts_with($routeName, $prefix.'.')) {
                    continue;
                }

                if (strlen($prefix) > $winnerLength) {
                    $winner = $key;
                    $winnerLength = strlen($prefix);
                }
            }
        }

        return $winner;
    }
}
