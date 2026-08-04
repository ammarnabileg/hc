<?php

namespace App\Services\Ui;

use App\Models\Task;
use App\Models\User;
use App\Services\Account\UserSearch;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * فهرس البحث الموحّد (Ctrl+K) — 2.15-د:
 * «حقل واحد يصل إلى أيّ **صفحة** أو **شخص** أو **مهمّة** بالكتابة، بديلًا عن
 * التنقّل في السايد بار».
 *
 * ⭐ والصفحة التي لا يملك المستخدم صلاحيّتها **لا تظهر في النتائج أصلًا** —
 *   لا معطَّلة ولا رماديّة (2.15-أ-7)، فالبحث لا يكشف ما لا يُرى.
 */
class CommandIndex
{
    /**
     * خريطة الصفحات: [اسم المسار => [العنوان, الصلاحيّة|null]].
     * ولماذا في الكود لا في قاعدة البيانات؟ لأنّها **خريطة السايد بار نفسها**
     * (12.0 · 24.5)، وأيّ تفريع لها يخلق مصدرَي حقيقة.
     *
     * @return array<string, array{0:string,1:?string}>
     */
    public function pages(): array
    {
        return [
            // المتدرّب (24.5)
            'dashboard' => [setting('ux.command_index.pages_1', 'الرئيسيّة'), null],
            'announcements.index' => [setting('ux.command_index.pages_2', 'التعليمات'), null],
            'learning.courses' => [setting('ux.command_index.pages_3', 'تدريباتي'), null],
            'learning.paths' => [setting('ux.command_index.pages_4', 'المسارات'), null],
            'learning.certificates' => [setting('ux.command_index.pages_5', 'شهاداتي'), null],
            'library.index' => [setting('ux.command_index.pages_6', 'مكتبتي'), null],
            'store.index' => [setting('ux.command_index.pages_7', 'المتجر'), null],
            'wallet.index' => [setting('ux.command_index.pages_8', 'المحفظة'), null],
            'wallet.tickets' => [setting('ux.command_index.pages_9', 'التذاكر'), null],
            'challenges.index' => [setting('ux.command_index.pages_10', 'التحديات'), null],
            'achievements.leaderboard' => [setting('ux.command_index.pages_11', 'الليدر بورد'), null],
            'achievements.badges' => [setting('ux.command_index.pages_12', 'الشارات'), null],
            'achievements.streak' => [setting('ux.command_index.pages_13', 'الستريك ونادي الخامسة'), null],
            'events.index' => [setting('ux.command_index.pages_14', 'الفعاليّات'), null],
            'cv.index' => [setting('ux.command_index.pages_15', 'السيرة الذاتيّة'), 'user_cv.view'],
            'attestations.index' => [setting('ux.command_index.pages_16', 'الإفادة'), 'user_attestation.view'],
            'referral.index' => [setting('ux.command_index.pages_17', 'ادعُ أصدقاءك'), null],
            'complaints.index' => [setting('ux.command_index.pages_18', 'الشكاوى والمقترحات'), 'complaints.view'],
            'help.index' => [setting('ux.command_index.pages_19', 'دليل المستخدم'), null],
            'profile.me' => [setting('ux.command_index.pages_20', 'بروفايلي'), 'user_profile.view'],
            'settings.index' => [setting('ux.command_index.pages_21', 'الإعدادات'), 'user_profile.edit'],
            'settings.privacy' => [setting('ux.command_index.pages_22', 'الخصوصيّة والأمان'), 'privacy_settings.view'],

            // التطوّع (24.4)
            'volunteer.overview' => [setting('ux.command_index.pages_23', 'لوحة التطوّع'), null],

            // الإدارة (12.0)
            // باب اللوحة قدرةٌ محسوبة لا صلاحيّة باسم شاشة (12.2.1-أ)
            'admin.dashboard' => [setting('ux.command_index.pages_24', 'لوحة القيادة'), 'admin-panel'],
            'admin.users.index' => [setting('ux.command_index.pages_25', 'قائمة المستخدمين'), 'users.list'],
            'admin.roles.index' => [setting('ux.command_index.pages_26', 'الأدوار والصلاحيّات'), 'roles.list'],
            'admin.paths.index' => [setting('ux.command_index.pages_27', 'المسارات (إدارة)'), 'paths.list'],
            'admin.courses.index' => [setting('ux.command_index.pages_28', 'التدريبات (إدارة)'), 'courses.list'],
            'admin.media.index' => [setting('ux.command_index.pages_29', 'مكتبة الوسائط'), 'media_library.list'],
            'admin.certificates.index' => [setting('ux.command_index.pages_30', 'الشهادات (إدارة)'), 'certificate_ledger.list'],
            'admin.volunteer.index' => [setting('ux.command_index.pages_31', 'إدارة التطوّع'), 'memberships.list'],
            'admin.gamification.index' => [setting('ux.command_index.pages_32', 'التلعيب والتحديات'), 'badges.list'],
            'admin.store.index' => [setting('ux.command_index.pages_33', 'المتجر (إدارة)'), 'store_products.list'],
            'admin.topups.index' => [setting('ux.command_index.pages_34', 'طلبات الشحن'), 'topup_requests.list'],
            'admin.articles.index' => [setting('ux.command_index.pages_35', 'المقالات'), 'articles.list'],
            'admin.ads.index' => [setting('ux.command_index.pages_36', 'الإعلان المدفوع'), 'ad_audiences.view'],
            'admin.studio.index' => [setting('ux.command_index.pages_37', 'استوديو الصور'), 'image_templates.list'],
            'admin.rewards.index' => [setting('ux.command_index.pages_38', 'إدارة المكافآت'), 'manual_rewards.list'],
            'admin.events.index' => [setting('ux.command_index.pages_39', 'الفعاليّات (إدارة)'), 'events.list'],
            'admin.guidance.index' => [setting('ux.command_index.pages_40', 'التوجيه والدعم'), 'announcements.list'],
            'admin.stats.index' => [setting('ux.command_index.pages_41', 'الإحصائيّات'), 'reports_users.list'],
            'admin.finance.index' => [setting('ux.command_index.pages_42', '🔒 الماليّات'), 'finance.view'],
            'admin.settings.index' => [setting('ux.command_index.pages_43', 'الإعدادات والنظام'), 'settings_general.view'],
        ];
    }

    /**
     * نتائج البحث الموحّد.
     *
     * @return array{pages:array,people:array,tasks:array}
     */
    public function search(User $viewer, string $term): array
    {
        $term = trim($term);
        $limit = max(3, (int) setting('ux.palette.limit_per_group', 5));

        return [
            'pages' => $this->matchPages($viewer, $term, $limit),
            'people' => $term === '' ? [] : $this->matchPeople($viewer, $term, $limit),
            'tasks' => $term === '' ? [] : $this->matchTasks($viewer, $term, $limit),
        ];
    }

    // ------------------------------------------------------------------ داخليّ

    private function matchPages(User $viewer, string $term, int $limit): array
    {
        $results = [];

        foreach ($this->pages() as $route => [$label, $permission]) {
            if (! Route::has($route)) {
                continue;
            }

            if ($permission !== null && ! Gate::forUser($viewer)->allows($permission)) {
                continue; // ما لا يملكه لا يظهر له أصلًا
            }

            if ($term !== '' && ! Str::contains($label, $term) && ! Str::contains($route, Str::lower($term))) {
                continue;
            }

            $results[] = ['label' => $label, 'url' => route($route), 'hint' => setting('ux.command_index.match_pages_1', 'صفحة')];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function matchPeople(User $viewer, string $term, int $limit): array
    {
        /*
         | ⚠️ كان الشرط `و` لا `أو`: فلا يخرج فارغًا إلّا مَن جمع فقدانَ المفتاح
         | **وعدمَ التفعيل معًا** — أي أنّ غير المفعَّل كان يبحث في الناس من لوحة
         | الأوامر. والشرطان مستقلّان: مفعَّلٌ **و**يملك مفتاح البحث (13.1 · 24.5).
         */
        if (! $viewer->isActive() || ! Gate::forUser($viewer)->allows(UserSearch::PAGE_PERMISSION)) {
            return [];
        }

        // نستعمل محرّك البحث القائم (13.1) — بنطاق الباحث نفسه وبلا بيانات حسّاسة
        return app(UserSearch::class)
            ->results($viewer, $term, ['name', 'code'], 0, $limit)
            ->map(fn (User $user) => [
                'label' => $user->shortName(),
                'url' => route('u.profile', ['code' => $user->code]),
                'hint' => '#'.$user->code,
            ])
            ->values()
            ->all();
    }

    private function matchTasks(User $viewer, string $term, int $limit): array
    {
        if (! Gate::forUser($viewer)->allows('tasks.view') || ! Route::has('volunteer.tasks.show')) {
            return [];
        }

        // مهامّ المستخدم وحده — البحث الموحّد اختصار تنقّل لا نافذة على مهامّ غيره
        return Task::query()
            ->where('title', 'like', '%'.$term.'%')
            ->where(fn ($q) => $q->where('owner_id', $viewer->id)->orWhere('created_by', $viewer->id))
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'title'])
            ->map(fn (Task $task) => [
                'label' => $task->title,
                'url' => route('volunteer.tasks.show', ['task' => $task->id]),
                'hint' => setting('ux.command_index.match_tasks_1', 'مهمّة'),
            ])
            ->values()
            ->all();
    }
}
