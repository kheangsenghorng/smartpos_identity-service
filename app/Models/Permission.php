<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Permission extends Model
{
    protected $fillable = [
        'uuid',
        'permission_group_id',
        'permission_resource_id',
        'code',
        'name',
        'module',
        'resource',
        'action',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'permission_group_id' => 'integer',
        'permission_resource_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Permission $permission) {
            $permission->uuid ??= (string) Str::uuid();
            $permission->is_active ??= true;

            // Auto-extract resource and action from code (e.g. module.resource.action) if not explicitly set
            if (! $permission->resource || ! $permission->action) {
                $parts = explode('.', $permission->code);
                if (count($parts) >= 3) {
                    $permission->module ??= $parts[0];
                    $permission->resource ??= $parts[1];
                    $permission->action ??= $parts[2];
                } elseif (count($parts) === 2) {
                    $permission->resource ??= $parts[0];
                    $permission->action ??= $parts[1];
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions'
        )->withTimestamps();
    }

    public function permissionGroup()
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

    public function permissionResource()
    {
        return $this->belongsTo(PermissionResource::class, 'permission_resource_id');
    }
}