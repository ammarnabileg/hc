<?php

namespace App\Models\Concerns;

use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\AccessEngine;
use App\Support\Access\MembershipContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ربط المستخدم بمعماريّة الصلاحيّات (12.2.1).
 * الدور يحدّد «ماذا» والعضويّة تحدّد «أين».
 */
trait HasAccessControl
{
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['membership_id', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot(['membership_id', 'scope', 'effect', 'conditions'])
            ->withTimestamps();
    }

    /** العضويّة النشطة التي يُقيَّم بداخلها كلّ شيء */
    public function activeMembership(): ?Membership
    {
        return app(MembershipContext::class)->for($this);
    }

    public function isVolunteer(): bool
    {
        return $this->memberships()->where('status', 'active')->exists();
    }

    /** هل يملك هذه الصلاحيّة على هذا الهدف؟ */
    public function allows(string $permissionKey, mixed $target = null, ?Membership $context = null): bool
    {
        return app(AccessEngine::class)->allows($this, $permissionKey, $target, $context);
    }

    public function isPlatformOwner(): bool
    {
        return app(AccessEngine::class)->isPlatformOwner($this);
    }

    /** أوسع نطاق يملكه في صلاحيّة (لبناء الاستعلامات) */
    public function widestScope(string $permissionKey): ?string
    {
        return app(AccessEngine::class)->widestScope($this, $permissionKey);
    }

    public function assignRole(Role|string $role, ?Membership $membership = null, ?int $assignedBy = null): void
    {
        $role = $role instanceof Role ? $role : Role::where('key', $role)->firstOrFail();

        $this->roles()->syncWithoutDetaching([
            $role->id => [
                'membership_id' => $membership?->id,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ],
        ]);

        app(AccessEngine::class)->forget($this);
    }

    public function hasRole(string $key): bool
    {
        return $this->roles()->where('key', $key)->exists();
    }
}
