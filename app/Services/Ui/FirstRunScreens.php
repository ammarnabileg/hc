<?php

namespace App\Services\Ui;

use App\Models\User;
use App\Models\UserFirstRun;
use App\Services\Admin\Ops\OnboardingContent;
use Illuminate\Support\Facades\Storage;

/**
 * «شاشة أوّل مرّة» — **على أهمّ الشاشات فقط** منعًا للزحام (2.15-د).
 *
 * ⭐ **مصدر حقيقة واحد**: جدول `onboarding_slides`.
 *
 * كان للشاشة مصدران: الأدمن يكتب شرائحه في الجدول من شاشة إدارة كاملة (CRUD
 * وترتيب وتفعيل وقوالب ومعاينة)، والمستخدم يقرأ من إعداد `ux.first_time.content`
 * الذي لا يكتب فيه أحد — فكانت النتيجة الحتميّة: **الأدمن يكتب والمستخدم لا يرى
 * شيئًا**، وثلاث شرائح مفعَّلة تُقابَل بقائمة فارغة. والمصدر الآن الجدول وحده،
 * لأنّه الذي يملك واجهة الإدارة، وما فيه هو **بعينه** ما يظهر في البوب-أب.
 */
class FirstRunScreens
{
    public function __construct(private readonly OnboardingContent $content) {}

    /** الشاشات المفعَّلة كما ضبطها الأدمن — نفس المفتاح الذي تحفظ فيه اللوحة */
    public function enabled(): array
    {
        $screens = setting('ux.first_time.enabled_screens', []);

        return is_array($screens) ? array_values(array_filter(array_map('strval', $screens))) : [];
    }

    public function isEnabled(string $screen): bool
    {
        // المفتاح العامّ يطفئ الميزة كلّها من اللوحة بضغطة (2.13)
        if (! setting('onboarding.first_time.enabled', true)) {
            return false;
        }

        return $screen !== '' && in_array($screen, $this->enabled(), true);
    }

    /**
     * مراحل الشاشة **كما كتبها الأدمن بالضبط** وبترتيبه، والمفعَّلة وحدها.
     *
     * @return array<int, array{title:string, body:string, image_url:?string, action_label:?string, action_url:?string}>
     */
    public function stepsFor(string $screen): array
    {
        if ($screen === '') {
            return [];
        }

        return $this->content->slides($screen, activeOnly: true)
            ->map(fn ($slide) => [
                'title' => (string) $slide->title_ar,
                'body' => (string) ($slide->body_ar ?? ''),
                'image_url' => $slide->image_path ? Storage::url($slide->image_path) : null,
                'action_label' => $slide->action_label ?: null,
                'action_url' => $slide->action_url ?: null,
            ])
            ->values()
            ->all();
    }

    /** نصوص أزرار البوب-أب — من نفس مجموعة إعدادات الـOnboarding (2.13) */
    public function labels(): array
    {
        return [
            'next' => (string) setting('onboarding.first_time.next_label', 'التالي'),
            'skip' => (string) setting('onboarding.first_time.skip_label', 'تخطّي'),
            'done' => (string) setting('onboarding.first_time.done_label', 'يلا نبدأ'),
            'replay' => (string) setting('onboarding.first_time.replay_hint', 'زرّ «؟» يعيد الشرح وقت ما تحبّ.'),
        ];
    }

    /** هل تُعرَض الآن لهذا المستخدم؟ — مرّة واحدة، إلّا أن يطلبها بزرّ «؟» */
    public function shouldShow(?User $user, string $screen): bool
    {
        if (! $user || ! $this->isEnabled($screen) || $this->stepsFor($screen) === []) {
            return false;
        }

        return ! UserFirstRun::query()
            ->where('user_id', $user->id)
            ->where('screen', $screen)
            ->whereNotNull('seen_at')
            ->exists();
    }
}
