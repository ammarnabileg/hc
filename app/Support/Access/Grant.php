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

    /** ترتيب النطاق: الأضيق = 0 */
    public function scopeRank(): int
    {
        $order = config('access.scopes');

        return array_search($this->scope, $order, true) ?: 0;
    }
}
