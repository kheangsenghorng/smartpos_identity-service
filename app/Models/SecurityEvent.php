<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SecurityEvent extends Model
{
    public $timestamps = true;

    protected $table = 'security_events';

    protected $fillable = [
        'uuid',
        'user_uuid',
        'business_uuid',
        'session_uuid',
        'device_uuid',
        'event_type',
        'severity',
        'ip_address',
        'user_agent',
        'route',
        'http_method',
        'description',
        'metadata',
        'occurred_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SecurityEvent $event) {
            $event->uuid ??= (string) Str::uuid();
            $event->occurred_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Backward-compatible helper that delegates to SecurityEventService.
     */
    public static function log(
        string $eventType,
        ?string $userUuid = null,
        ?string $businessUuid = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $description = null
    ): self {
        return \App\Services\SecurityEventService::record(
            eventType: $eventType,
            severity: $severity,
            userUuid: $userUuid,
            businessUuid: $businessUuid,
            description: $description,
            metadata: $metadata,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }
}
