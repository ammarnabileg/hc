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
            'dashboard' => ['الرئيسيّة', null],
            'announcements.index' => ['التعليمات', null],
            'learning.courses' => ['تدريباتي', null],
            'learning.paths' => ['المسارات', null],
            'learning.certificates' => ['شهاداتي', null],
            'library.index' => ['مكتبتي', null],
            'store.index' => ['المتجر', null],
            'wallet.index' => ['المحفظة', null],
            'wallet.tickets' => ['التذاكر', null],
            'challenges.index' => ['التحديات', null],
            'achievements.leaderboard' => ['الليدر بورد', null],
            'achievements.badges' => ['الشارات', null],
            'achievements.streak' => ['الستريك ونادي الخامسة', null],
            'events.index' => ['الفعاليّات', null],
            'cv.index' => ['السيرة الذاتيّة', 'user_cv.view'],
            'attestations.index' => ['الإفادة', 'user_attestation.view'],
            'referral.index' => ['ادعُ أصدقاءك', null],
            'complaints.index' => ['الشكاوى والمقترحات', 'complaints.view'],
            'help.index' => ['دليل المستخدم', null],
            'profile.me' => ['بروفايلي', 'user_profile.view'],
            'settings.index' => ['الإعدادات', 'user_profile.edit'],
            'settings.privacy' => ['الخصوصيّة والأمان', 'privacy_settings.view'],

            // التطوّع (24.4)
            'volunteer.overview' => ['لوحة التطوّع', null],

            // الإدارة (12.0)
            // باب اللوحة قدرةٌ محسوبة لا صلاحيّة باسم شاشة (12.2.1-أ)
            'admin.dashboard' => ['لوحة القيادة', 'admin-panel'],
            'admin.users.index' => ['قائمة المستخدمين', 'users.list'],
            'admin.roles.index' => ['الأدوار والصلاحيّات', 'roles.list'],
            'admin.paths.index' => ['المسارات (إدارة)', 'paths.list'],
            'admin.courses.index' => ['التدريبات (إدارة)', 'courses.list'],
            'admin.media.index' => ['مكتبة الوسائط', 'media_library.list'],
            'admin.certificates.index' => ['الشهادات (إدارة)', 'certificate_ledger.list'],
            'admin.volunteer.index' => ['إدارة التطوّع', 'memberships.list'],
            'admin.gamification.index' => ['التلعيب والتحديات', 'badges.list'],
            'admin.store.index' => ['المتجر (إدارة)', 'store_products.list'],
            'admin.topups.index' => ['طلبات الشحن', 'topup_requests.list'],
            'admin.articles.index' => ['المقالات', 'articles.list'],
            'admin.ads.index' => ['الإعلان المدفوع', 'ad_audiences.view'],
            'admin.studio.index' => ['استوديو الصور', 'image_templates.list'],
            'admin.rewards.index' => ['إدارة المكافآت', 'manual_rewards.list'],
            'admin.events.index' => ['الفعاليّات (إدارة)', 'events.list'],
            'admin.guidance.index' => ['التوجيه والدعم', 'announcements.list'],
            'admin.stats.index' => ['الإحصائيّات', 'reports_users.list'],
            'admin.finance.index' => ['🔒 الماليّات', 'finance.view'],
            'admin.settings.index' => ['الإعدادات والنظام', 'settings_general.view'],
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

            $results[] = ['label' => $label, 'url' => route($route), 'hint' => 'صفحة'];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function matchPeople(User $viewer, string $term, int $limit): array
    {
        if (! Gate::forUser($viewer)->allows('user_search.view') && ! $viewer->isActive()) {
            return [];
        }

        // نستعمل محرّك البحث القائم (13.1) — بلا بيانات حسّاسة إطلاقًا
        return app(UserSearch::class)
            ->results($term, ['name', 'code'], 0, $limit)
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
                'hint' => 'مهمّة',
            ])
            ->values()
            ->all();
    }
}
