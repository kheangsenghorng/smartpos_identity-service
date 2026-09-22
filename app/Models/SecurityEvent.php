<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SecurityEvent extends Model
{
    public $timestamps = false;

    protected $table = 'security_events';

    protected $fillable = [
        'uuid',
        'user_uuid',
        'business_uuid',
        'event_type',
        'severity',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SecurityEvent $event) {
            $event->uuid ??= (string) Str::uuid();
            $event->created_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Helper to log security audit event.
     */
    public static function log(
        string $eventType,
        ?string $userUuid = null,
        ?string $businessUuid = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'uuid' => (string) Str::uuid(),
            'user_uuid' => $userUuid,
            'business_uuid' => $businessUuid,
            'event_type' => $eventType,
            'severity' => $severity,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
