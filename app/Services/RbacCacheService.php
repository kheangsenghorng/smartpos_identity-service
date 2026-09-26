<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class RbacCacheService
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL = 3600;

    /**
     * Get user's permission codes using Redis cache.
     */
    public static function getUserPermissionCodes(User $user): array
    {
        $cacheKey = "user:{$user->uuid}:permission_codes";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $user->unsetRelation('roles');
            return $user->allPermissions()
                ->pluck('code')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Get user's role codes using Redis cache.
     */
    public static function getUserRoleCodes(User $user): array
    {
        $cacheKey = "user:{$user->uuid}:role_codes";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $user->unsetRelation('roles');
            $user->load('roles');

            return $user->roles
                ->pluck('code')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Set / update user's permission codes in Redis cache directly.
     */
    public static function setUserPermissionCodes(User $user, ?array $permissionCodes = null): array
    {
        $cacheKey = "user:{$user->uuid}:permission_codes";

        if ($permissionCodes === null) {
            $user->unsetRelation('roles');
            $permissionCodes = $user->allPermissions()
                ->pluck('code')
                ->unique()
                ->values()
                ->all();
        }

        Cache::put($cacheKey, $permissionCodes, self::CACHE_TTL);

        return $permissionCodes;
    }

    /**
     * Set / update user's role codes in Redis cache directly.
     */
    public static function setUserRoleCodes(User $user, ?array $roleCodes = null): array
    {
        $cacheKey = "user:{$user->uuid}:role_codes";

        if ($roleCodes === null) {
            $user->unsetRelation('roles');
            $user->load('roles');

            $roleCodes = $user->roles
                ->pluck('code')
                ->unique()
                ->values()
                ->all();
        }

        Cache::put($cacheKey, $roleCodes, self::CACHE_TTL);

        return $roleCodes;
    }

    /**
     * Refresh and update all cached RBAC permissions and roles for a user in Redis.
     */
    public static function refreshUserCache(User $user): array
    {
        $permissions = self::setUserPermissionCodes($user);
        $roles = self::setUserRoleCodes($user);

        return [
            'permissions' => $permissions,
            'roles' => $roles,
        ];
    }

    /**
     * Check if user has given permission(s) using cached permission list.
     */
    public static function hasPermission(User $user, string|array $permissions): bool
    {
        $requiredCodes = is_array($permissions)
            ? $permissions
            : explode(',', $permissions);

        $requiredCodes = array_map('trim', $requiredCodes);
        $userCodes = self::getUserPermissionCodes($user);

        foreach ($requiredCodes as $code) {
            if (in_array($code, $userCodes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has given role(s) using cached role list.
     */
    public static function hasRole(User $user, string|array $roles): bool
    {
        $requiredRoles = is_array($roles)
            ? $roles
            : explode(',', $roles);

        $requiredRoles = array_map('trim', $requiredRoles);
        $userRoles = self::getUserRoleCodes($user);

        foreach ($requiredRoles as $code) {
            if (in_array($code, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Invalidate cached RBAC permissions and roles for a specific user.
     */
    public static function forgetUserCache(User $user): void
    {
        Cache::forget("user:{$user->uuid}:permission_codes");
        Cache::forget("user:{$user->uuid}:role_codes");
    }

    /**
     * Invalidate cached RBAC permissions for all users assigned to a specific role.
     */
    public static function forgetRoleUsersCache(Role $role): void
    {
        $role->loadMissing('users');

        foreach ($role->users as $user) {
            self::forgetUserCache($user);
        }
    }

    /**
     * Get the cache version for the roles list.
     */
    public static function getRolesListVersion(?string $businessUuid = null): int
    {
        $versionKey = $businessUuid
            ? "roles:list:business:{$businessUuid}:version"
            : "roles:list:all:version";

        return (int) Cache::get($versionKey, 1);
    }

    /**
     * Get cached paginated roles with attached permissions, optionally filtered by business_uuid and is_system.
     */
    public static function getRolesWithPermissions(?string $businessUuid = null, int $perPage = 20, int $page = 1, ?bool $isSystem = null): LengthAwarePaginator
    {
        $version = self::getRolesListVersion($businessUuid);
        $scope = $businessUuid ? "business:{$businessUuid}" : "all";
        $sysSuffix = $isSystem !== null ? ($isSystem ? ":sys1" : ":sys0") : "";
        $cacheKey = "roles:list:{$scope}:v{$version}:p{$page}:l{$perPage}{$sysSuffix}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($businessUuid, $perPage, $page, $isSystem) {
            return Role::query()
                ->with('permissions')
                ->when(
                    $businessUuid,
                    fn ($q, $uuid) => $q->where('business_uuid', $uuid)
                )
                ->when(
                    $isSystem !== null,
                    fn ($q) => $q->where('is_system', $isSystem)
                )
                ->paginate(perPage: $perPage, page: $page);
        });
    }

    /**
     * Invalidate cached roles list for a business and globally.
     */
    public static function forgetRolesListCache(?string $businessUuid = null): void
    {
        if ($businessUuid) {
            $businessVersionKey = "roles:list:business:{$businessUuid}:version";
            if (! Cache::has($businessVersionKey)) {
                Cache::put($businessVersionKey, 1, self::CACHE_TTL * 24);
            }
            Cache::increment($businessVersionKey);
        }

        $globalVersionKey = "roles:list:all:version";
        if (! Cache::has($globalVersionKey)) {
            Cache::put($globalVersionKey, 1, self::CACHE_TTL * 24);
        }
        Cache::increment($globalVersionKey);
    }
}

