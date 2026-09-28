<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PermissionResource extends Model
{
    protected $fillable = [
        'uuid',
        'permission_group_id',
        'parent_id',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'permission_group_id' => 'integer',
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PermissionResource $resource) {
            $resource->uuid ??= (string) Str::uuid();
            $resource->is_active ??= true;
            $resource->sort_order ??= 0;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function permissionGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PermissionResource::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PermissionResource::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'permission_resource_id')
            ->orderBy('sort_order')
            ->orderBy('code');
    }
}
