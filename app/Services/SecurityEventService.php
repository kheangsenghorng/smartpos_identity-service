<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SecurityEventService
{
    // Severity levels
    public const SEVERITY_LOW      = 'low';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    // Authentication events
    public const LOGIN_SUCCESS     = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED      = 'LOGIN_FAILED';
    public const LOGOUT            = 'LOGOUT';
    public const ACCOUNT_LOCKED    = 'ACCOUNT_LOCKED';

    // Password events
    public const PASSWORD_CHANGED      = 'PASSWORD_CHANGED';
    public const PASSWORD_RESET        = 'PASSWORD_RESET';
    public const PASSWORD_RESET_FAILED = 'PASSWORD_RESET_FAILED';

    // Email events
    public const EMAIL_CHANGE_REQUESTED = 'EMAIL_CHANGE_REQUESTED';
    public const EMAIL_CHANGED          = 'EMAIL_CHANGED';
    public const EMAIL_VERIFIED         = 'EMAIL_VERIFIED';

    // OTP / 2FA events
    public const OTP_SENT              = 'OTP_SENT';
    public const OTP_FAILED            = 'OTP_FAILED';
    public const OTP_VERIFIED          = 'OTP_VERIFIED';
    public const TWO_FACTOR_FAILED     = 'TWO_FACTOR_FAILED';
    public const TWO_FACTOR_VERIFIED   = 'TWO_FACTOR_VERIFIED';

    // Device events
    public const NEW_DEVICE_LOGIN = 'NEW_DEVICE_LOGIN';
    public const DEVICE_TRUSTED   = 'DEVICE_TRUSTED';
    public const DEVICE_BLOCKED   = 'DEVICE_BLOCKED';

    // Session events
    public const SESSION_CREATED       = 'SESSION_CREATED';
    public const SESSION_REVOKED       = 'SESSION_REVOKED';
    public const ALL_SESSIONS_REVOKED  = 'ALL_SESSIONS_REVOKED';

    // Authorization & RBAC events
    public const ROLE_ASSIGNED        = 'ROLE_ASSIGNED';
    public const ROLE_REVOKED         = 'ROLE_REVOKED';
    public const ROLES_SYNCED         = 'ROLES_SYNCED';
    public const PERMISSION_DENIED    = 'PERMISSION_DENIED';
    public const UNAUTHORIZED_ACCESS  = 'UNAUTHORIZED_ACCESS';

    // Security anomaly events
    public const SUSPICIOUS_ACTIVITY    = 'SUSPICIOUS_ACTIVITY';
    public const RATE_LIMIT_TRIGGERED   = 'RATE_LIMIT_TRIGGERED';

    /**
     * Keys that must never be stored raw in metadata.
     */
    protected static array $redactedKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'pin',
        'pos_pin',
        'otp',
        'code',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'authorization',
        'bearer',
        'credit_card',
        'card_number',
        'cvv',
    ];

    /**
     * Standardized security event recorder with auto-sanitization and error resilience.
     */
    public static function record(
        string $eventType,
        string $severity = self::SEVERITY_LOW,
        ?string $userUuid = null,
        ?string $businessUuid = null,
        ?string $description = null,
        array $metadata = [],
        ?string $sessionUuid = null,
        ?string $deviceUuid = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $route = null,
        ?string $httpMethod = null,
        \DateTimeInterface|string|null $occurredAt = null
    ): ?SecurityEvent {
        try {
            $request = request();

            // Auto-detect user_uuid from auth if not explicitly provided
            if (! $userUuid && auth('api')->check()) {
                $userUuid = auth('api')->user()?->uuid;
            }

            return SecurityEvent::create([
                'uuid'          => (string) Str::uuid(),
                'user_uuid'     => $userUuid,
                'business_uuid' => $businessUuid,
                'session_uuid'  => $sessionUuid,
                'device_uuid'   => $deviceUuid,
                'event_type'    => $eventType,
                'severity'      => $severity,
                'ip_address'    => $ipAddress ?? $request?->ip(),
                'user_agent'    => $userAgent ?? $request?->userAgent(),
                'route'         => $route ?? $request?->path(),
                'http_method'   => $httpMethod ?? $request?->method(),
                'description'   => $description,
                'metadata'      => self::sanitizeMetadata($metadata),
                'occurred_at'   => $occurredAt ?? now(),
            ]);
        } catch (\Throwable $e) {
            // Fail-safe: security logging must never crash core business logic
            Log::error('Failed to record security event: ' . $e->getMessage(), [
                'event_type' => $eventType,
                'user_uuid' => $userUuid,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Recursively sanitize metadata array by redacting sensitive keys.
     */
    public static function sanitizeMetadata(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::$redactedKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeMetadata($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
