<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = [
        'uuid',
        'business_uuid',
        'name',
        'code',
        'module',
        'level',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Role $role) {
            $role->uuid ??= (string) Str::uuid();
            $role->module ??= 'system';
            $role->level ??= 10;
            $role->is_active ??= true;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_roles',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    /**
     * Sub-roles that this role is authorized to assign / delegate.
     */
    public function assignableRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_assignable_roles',
            'grantor_role_id',
            'assignable_role_id'
        )->withPivot('created_at');
    }

    /**
     * Higher-level grantor roles that can assign this role.
     */
    public function grantorRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_assignable_roles',
            'assignable_role_id',
            'grantor_role_id'
        )->withPivot('created_at');
    }

    /**
     * Checks if this role can delegate the specified target role.
     */
    public function canAssign(Role|int|string $targetRole): bool
    {
        // Super Admin has global delegation authority
        if ($this->code === 'super_admin' || $this->level >= 100) {
            return true;
        }

        if ($targetRole instanceof Role) {
            $targetId = $targetRole->id;
        } elseif (is_numeric($targetRole)) {
            $targetId = (int) $targetRole;
        } else {
            $targetId = Role::where('code', $targetRole)->value('id');
        }

        if (! $targetId) {
            return false;
        }

        return $this->assignableRoles()->where('roles.id', $targetId)->exists();
    }
}