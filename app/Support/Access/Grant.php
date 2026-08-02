<?php

namespace App\Support\Access;

/**
 * سطر إسناد واحد: صلاحيّة × نطاق × أثر × شروط.
 * يأتي من دورٍ أو من استثناء فرديّ — والمعالجة واحدة في الحالتين.
 */
final class Grant
{
    public function __construct(
        public readonly string $permissionKey,
        public readonly string $scope,
        public readonly string $effect,        // allow | deny
        public readonly array $conditions = [],
        public readonly ?int $membershipId = null,
        public readonly ?string $origin = null, // role:key | user
    ) {}

    public function isDeny(): bool
    {
        return $this->effect === 'deny';
    }

    public function isAllow(): bool
    {
        return $this->effect === 'allow';
    }

    /**
     * ترتيب النطاق: الأضيق = 0، و**النطاق التالف = -1**.
     *
     * كان `?: 0` يبتلع حالتين مختلفتين تمامًا: `SELF` (رتبتها 0) والنطاق المجهول
     * (`array_search` تعيد `false`). فكان صفٌّ نطاقُه `"ALLL"` أو `""` يُصنَّف
     * أضيقَ نطاق ويُعامَل معاملة `SELF` — أي **يُقرَأ إذنًا** بدل أن يُرفَض.
     * الآن يُرفَض: المحرّك لا يمنح على صفٍّ لا يفهم نطاقه.
     */
    public function scopeRank(): int
    {
        $index = array_search($this->scope, config('access.scopes'), true);

        return $index === false ? -1 : $index;
    }

    /** هل نطاق هذا الصفّ من القائمة المعتمَدة أصلًا؟ */
    public function hasValidScope(): bool
    {
        return $this->scopeRank() >= 0;
    }
}
