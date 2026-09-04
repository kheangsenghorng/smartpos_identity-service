<?php

namespace App\Models;

use App\Services\RbacCacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'status',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Get the full URL to the user's avatar image.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        $disk = config('filesystems.default', 'public');

        return Storage::disk($disk)->url($this->avatar);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | JWT
    |--------------------------------------------------------------------------
    */

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Check role
    |--------------------------------------------------------------------------
    */

    public function hasRole(string|array $roles): bool
    {
        return RbacCacheService::hasRole($this, $roles);
    }

    /*
    |--------------------------------------------------------------------------
    | All permissions
    |--------------------------------------------------------------------------
    */

    public function allPermissions(): Collection
    {
        $this->loadMissing(
            'roles.permissions'
        );

        return $this->roles
            ->flatMap(
                fn (Role $role) => $role->permissions
            )
            ->unique('id')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Permission codes
    |--------------------------------------------------------------------------
    */

    public function permissionCodes(): array
    {
        return RbacCacheService::getUserPermissionCodes($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Role codes
    |--------------------------------------------------------------------------
    */

    public function roleCodes(): array
    {
        return RbacCacheService::getUserRoleCodes($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Check permission
    |--------------------------------------------------------------------------
    */

    public function hasPermission(
        string|array $permissions
    ): bool {
        return RbacCacheService::hasPermission($this, $permissions);
    }

    /*
    |--------------------------------------------------------------------------
    | Clear RBAC Cache
    |--------------------------------------------------------------------------
    */

    public function clearRbacCache(): void
    {
        RbacCacheService::forgetUserCache($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Other relationships
    |--------------------------------------------------------------------------
    */

    public function devices(): HasMany
    {
        return $this->hasMany(
            UserDevice::class
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            UserSession::class
        );
    }

    public function posPins(): HasMany
    {
        return $this->hasMany(
            UserPosPin::class
        );
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(
            LoginAttempt::class
        );
    }
}