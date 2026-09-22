<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAssignableRole extends Model
{
    public $timestamps = false;

    protected $table = 'role_assignable_roles';

    protected $fillable = [
        'grantor_role_id',
        'assignable_role_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RoleAssignableRole $model) {
            $model->created_at ??= now();
        });
    }

    public function grantorRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'grantor_role_id');
    }

    public function assignableRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assignable_role_id');
    }
}
