<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PermissionGroup extends Model
{
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PermissionGroup $group) {
            $group->uuid ??= (string) Str::uuid();
            $group->is_active ??= true;
            $group->sort_order ??= 0;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'permission_group_id')
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(PermissionResource::class, 'permission_group_id')
            ->orderBy('sort_order');
    }
}

