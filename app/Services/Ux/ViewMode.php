<?php

namespace App\Services\Ux;

use App\Models\User;

/**
 * وضعا «مبسّط» و«متقدّم» (2.15) — **القارئ الحقيقيّ** للأعلام والإعدادات.
 *
 * لماذا خدمة واحدة؟ لأنّ 2.15 كلّها مبنيّة على أنّ «البساطة إخفاء وتدرّج لا
 * تقليل»: العمق **موجود دائمًا** خلف خطوة واحدة. فلو لم يقرأ أحدٌ العَلَم صار
 * الإخفاء **حذفًا** وانقلبت القاعدة على نفسها — لذلك يقرأ منها كلٌّ من:
 * سويتش الصفحة · حدّ الفلاتر الظاهرة · حدّ أعمدة الجدول · حدّ حقول الفورم.
 *
 * وكلّ الأرقام من `setting()` بلا رقم محروق (2.13).
 */
class ViewMode
{
    /** هل الوضع المتقدّم متاح أصلًا؟ (2.15-هـ: «تفعيل وضع متقدّم لكلّ دور») */
    public function available(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! (bool) setting('ux.advanced_mode.enabled', true)) {
            return false;
        }

        $roles = setting('ux.advanced_mode.roles', []);

        // قائمة فارغة = متاح للجميع؛ وإلّا لا بدّ أن يملك المستخدم أحد الأدوار
        if (is_array($roles) && $roles !== []) {
            return $user->roles()->whereIn('key', $roles)->exists();
        }

        return true;
    }

    /**
     * هل نعرض الصفحة بالوضع المتقدّم الآن؟
     *
     * «الوضع المبسّط العامّ» يغلب دائمًا لأنّه — نصًّا — «يُخفي وضع المتقدّم
     * من **كلّ** الصفحات دفعةً واحدة» (2.15-ب).
     */
    public function isAdvanced(?User $user): bool
    {
        if (! $this->available($user) || $user->simple_mode) {
            return false;
        }

        return (bool) $user->advanced_mode;
    }

    /**
     * تبديل السويتش من أيّ صفحة — ويُحفَظ لكلّ مستخدم (2.15-أ-9).
     * وتشغيل «المتقدّم» يُطفئ «المبسّط العامّ» تلقائيًّا وإلّا تناقض الإعدادان.
     */
    public function toggle(User $user): bool
    {
        $next = ! $this->isAdvanced($user);

        $user->forceFill([
            'advanced_mode' => $next,
            'simple_mode' => $next ? false : $user->simple_mode,
        ])->save();

        return $next;
    }

    /** 3 فلاتر ظاهرة والباقي مطويّ (2.15-أ-4) */
    public function maxVisibleFilters(): int
    {
        return max(1, (int) setting('ux.filters.max_visible', 3));
    }

    /** جدول بـ5–7 أعمدة والباقي خلف زرّ «أعمدة» (2.15-أ-5) */
    public function defaultColumns(): int
    {
        return max(1, (int) setting('ux.tables.default_columns', 6));
    }

    /** الفورم الأطول من الحدّ يتقسّم خطوات بحفظ تلقائيّ بينها (2.15-ب) */
    public function maxFieldsBeforeStepper(): int
    {
        return max(1, (int) setting('ux.forms.max_fields_before_stepper', 7));
    }

    /** 4 كروت KPI بحدّ أقصى (2.15-أ-3) */
    public function maxKpiCards(): int
    {
        return max(1, (int) setting('ux.kpi.max_cards', 4));
    }

    /** تسمية السويتش — نصّ من الإعدادات لا محروق (2.13) */
    public function label(): string
    {
        return (string) setting('ux.advanced_mode.label', 'وضع متقدّم');
    }
}
