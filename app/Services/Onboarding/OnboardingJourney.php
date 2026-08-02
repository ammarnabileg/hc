<?php

namespace App\Services\Onboarding;

use App\Models\User;

/**
 * ترتيب رحلة ما بعد التسجيل (2.5-د) — **بالترتيب المنصوص لا بالصدفة**:
 *
 *   1. صفحة «تعليمات» ⟵ زرّ «موافقة».
 *   2. الاختبار التمهيديّ (Placement).
 *   3. صفحة «تحت المراجعة» — الحساب ينتظر اعتماد الأدمن.
 *   4. صفحة «تمّ قبول حسابك» بعد الاعتماد، ثمّ يدخل المنصّة.
 *
 * لماذا خدمة واحدة تقرّر الخطوة؟ لأنّ **كلّ شاشة من الأربع تحتاج نفس السؤال**
 * («هل هذا وقتها؟»)، ولو أجابت كلّ شاشة بنفسها اختلفت الإجابات وتسلّل المستخدم
 * لخطوةٍ لم يستحقّها — أو حُبِس في خطوةٍ أتمّها. المصدر واحد هنا، وكلّ شاشة تسأله.
 */
class OnboardingJourney
{
    public const STEP_INSTRUCTIONS = 'instructions';

    public const STEP_PLACEMENT = 'placement';

    public const STEP_REVIEW = 'review';

    public const STEP_ACCEPTED = 'accepted';

    public const STEP_DONE = 'done';

    public function __construct(private readonly PlacementTest $placement) {}

    /** الخطوة التي يقف عندها المستخدم الآن */
    public function currentStep(User $user): string
    {
        if (! $user->instructions_agreed_at && $this->instructionsEnabled()) {
            return self::STEP_INSTRUCTIONS;
        }

        if (! $user->placement_completed_at && $this->placement->isEnabled()) {
            return self::STEP_PLACEMENT;
        }

        if (! $user->isActive()) {
            return self::STEP_REVIEW;
        }

        // القبول لحظةُ ذروة تُرى مرّة واحدة (2.14-3) — وبعدها المنصّة
        return $user->acceptance_seen_at ? self::STEP_DONE : self::STEP_ACCEPTED;
    }

    /** اسم المسار الذي يجب أن يكون المستخدم فيه الآن */
    public function routeFor(User $user): string
    {
        return match ($this->currentStep($user)) {
            self::STEP_INSTRUCTIONS => 'onboarding.instructions',
            self::STEP_PLACEMENT => 'onboarding.placement',
            self::STEP_REVIEW => 'account.pending',
            self::STEP_ACCEPTED => 'onboarding.accepted',
            default => 'dashboard',
        };
    }

    /**
     * هل يقف المستخدم عند هذه الخطوة بالضبط؟
     * الشاشات تستعملها لتردّ مَن سبق خطوته أو تخلّف عنها للمكان الصحيح.
     */
    public function isAt(User $user, string $step): bool
    {
        return $this->currentStep($user) === $step;
    }

    /** صفحة التعليمات تُطفَأ من اللوحة إن أراد المالك (2.13) */
    public function instructionsEnabled(): bool
    {
        return trim((string) setting('onboarding.instructions.html', '')) !== '';
    }
}
