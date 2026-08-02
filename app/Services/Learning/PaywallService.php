<?php

namespace App\Services\Learning;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\LibraryEntitlement;
use App\Models\User;
use App\Services\Store\StoreCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * «مجّاني أوّل مرّة» و**الـPaywall النفسيّ** (الدستور 16).
 *
 * النصّ حرفيًّا: مشاهدةٌ حرّةٌ متكرّرة؛ **بمجرّد (امتحان + شهادة) يُقفَل التدريب**
 * ويُطلَب الشراء بالسعر النهائيّ (سعر العرض إن كان ساريًا وإلّا الأساسيّ).
 *
 * ولماذا الإغلاق هنا لا في الواجهة؟ لأنّ إخفاء زرٍّ في الواجهة ليس إغلاقًا:
 * القرار يقع في `ProgressService::outline` فيسري على صفحة التدريب وصفحة الدرس
 * وتسجيل الإكمال وتصحيح الأسئلة معًا — مصدرٌ واحد لا أربعة.
 *
 * وليس فيه نفور مصطنع (2.9): الرسالة تذكّر بما أنجزه فعلًا، والسعر حقيقيّ معلن.
 */
class PaywallService
{
    public function __construct(private readonly StoreCatalog $catalog) {}

    /**
     * حالة القفل لهذا المستخدم في هذا التدريب.
     *
     * @return array{active:bool,locked:bool,reason:?string,headline:?string,price:float,buy_url:?string,percent:int}
     */
    public function state(?User $user, Course $course): array
    {
        $idle = [
            'active' => false,
            'locked' => false,
            'reason' => null,
            'headline' => null,
            'price' => 0.0,
            'buy_url' => null,
            'percent' => 0,
        ];

        if (! $user || ! (bool) ($course->free_first_time ?? false)) {
            return $idle;
        }

        // مَن دفع ثمنه لا يُقفَل عليه أبدًا — الملكيّة دائمة (20)
        if ($this->hasPaid($user, $course)) {
            return [...$idle, 'active' => true];
        }

        $earned = $this->earnedCredential($user, $course);

        if (! $earned) {
            // لسّه في المشاهدة الحرّة المتكرّرة
            return [...$idle, 'active' => true, 'price' => $this->price($course)];
        }

        $percent = (int) setting('learning.progress.complete_percent', 100);

        return [
            'active' => true,
            'locked' => true,
            'reason' => $this->text($course, 'learning.paywall.lock_reason'),
            'headline' => $this->text($course, 'learning.paywall.headline'),
            'price' => $this->price($course),
            'buy_url' => $this->buyUrl($course),
            'percent' => $percent,
        ];
    }

    public function isLocked(?User $user, Course $course): bool
    {
        return $this->state($user, $course)['locked'];
    }

    /**
     * (امتحان + شهادة) معًا — لا أحدهما.
     * الامتحان: محاولة ناجحة على امتحان هذا التدريب. والشهادة: شهادة سارية عليه.
     */
    public function earnedCredential(User $user, Course $course): bool
    {
        return $this->passedExam($user, $course) && $this->hasCertificate($user, $course);
    }

    public function passedExam(User $user, Course $course): bool
    {
        return DB::table('exam_attempts')
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exams.examable_type', Course::class)
            ->where('exams.examable_id', $course->id)
            ->where('exam_attempts.user_id', $user->id)
            ->where('exam_attempts.passed', true)
            ->exists();
    }

    public function hasCertificate(User $user, Course $course): bool
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('subject_type', Course::class)
            ->where('subject_id', $course->id)
            ->where('status', (string) setting('learning.certificate.valid_status', 'valid'))
            ->exists();
    }

    /** الشراء الحقيقيّ: سطر ملكيّة مصدره شراء أو باقة (18 — لا نوع «هديّة») */
    public function hasPaid(User $user, Course $course): bool
    {
        return LibraryEntitlement::query()
            ->where('user_id', $user->id)
            ->where('itemable_type', Course::class)
            ->where('itemable_id', $course->id)
            ->whereIn('source', (array) setting('learning.paywall.paid_sources', ['purchase', 'bundle']))
            ->exists();
    }

    /** السعر النهائيّ: سعر العرض إن كان ساريًا وإلّا الأساسيّ (16) — ولا يتأثّر بعلَم المجّانيّة */
    public function price(Course $course): float
    {
        return round($this->catalog->activeOffer($course) ?? (float) $course->price_coins, 2);
    }

    /**
     * نصّ الرسالة: **ما كتبه الأدمن للتدريب نفسه أوّلًا** (`paywall_text_ar/en`)
     * ثمّ الإعداد العامّ — ولا نصّ محروق في الكود (2.13).
     */
    private function text(Course $course, string $key): string
    {
        if ($key === 'learning.paywall.headline') {
            $own = app()->getLocale() === 'en'
                ? trim((string) ($course->paywall_text_en ?? '')) ?: trim((string) ($course->paywall_text_ar ?? ''))
                : trim((string) ($course->paywall_text_ar ?? ''));

            if ($own !== '') {
                return str_replace('{course}', (string) $course->name_ar, $own);
            }
        }

        return str_replace('{course}', (string) $course->name_ar, (string) setting($key));
    }

    private function buyUrl(Course $course): ?string
    {
        if (! Route::has('store.product')) {
            return null;
        }

        try {
            return route('store.product', ['type' => 'course', 'slug' => $course->slug]);
        } catch (\Throwable) {
            return null;
        }
    }
}
