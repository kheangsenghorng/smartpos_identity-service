# SmartPOS Identity Service — Backend Implementation & Improvement Roadmap

This document outlines the exact architectural gaps, file additions, code changes, and step-by-step tasks required to implement **Multi-Role & Multi-Business Context Switching** in `smartpos-identity-service`.

---

## 🔍 1. Current State vs. Target State Audit

| Aspect | Current Identity Service State | Target Context-Aware State |
| :--- | :--- | :--- |
| **JWT Claims** | `'iss'`, `'aud'`, `'sid'`, `'user_uuid'`, `'device_uuid'`, `'roles'`, `'permissions'` | Adds `'business_uuid'`, `'active_role'`, and strictly scopes `'permissions'` to the `active_role`. |
| **Permission Scope** | Merges ALL permissions from ALL assigned roles into a single flat array. | Strictly limits permissions to the single currently active role (`cashier` mode cannot see `owner` perms). |
| **Context Switch API** | No endpoint (`404 Not Found`). User must log out and log in again. | `POST /api/v1/auth/switch-context` returns a new JWT with updated `active_role` without resetting the session. |
| **Role Guarding** | Custom checks in controllers; no active role middleware. | Dedicated `ActiveRoleMiddleware` (`'active.role:owner'`) and `BusinessContextMiddleware`. |
| **Token Expiry** | Default 24 hours (`86400`s). | Short-lived Access Token (15–30 min) + Long-lived Refresh Token (30 days). |
| **Audit Logging** | Generic `login_attempts` only. | Dedicated `security_events` logging for `ROLE_CONTEXT_SWITCHED`. |

---

## 📋 2. Step-by-Step Implementation Tasks

### 🗂️ Phase 1: Request Validation & DTOs

#### Task 1.1 — Create `SwitchContextRequest`
* **Target File**: `app/Http/Requests/Auth/SwitchContextRequest.php`
* **Command**: `php artisan make:request Auth/SwitchContextRequest`
* **Implementation Blueprint**:
```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchContextRequest extends FormRequest
{
    public const ALLOWED_ROLES = [
        'owner',
        'admin',
        'manager',
        'cashier',
        'staff',
    ];

    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'business_uuid' => ['required', 'uuid'],
            'role'          => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'The selected role is invalid. Allowed roles: ' . implode(', ', self::ALLOWED_ROLES),
        ];
    }
}
```

---

### ⚙️ Phase 2: Core Domain Service (`ContextSwitchService`)

#### Task 2.1 — Create `ContextSwitchService`
* **Target File**: `app/Services/Auth/ContextSwitchService.php`
* **Responsibilities**:
  1. Verify user account status (`active`).
  2. Verify that the requested role is attached to the user for the given `business_uuid` (or is a global system admin role).
  3. Retrieve permissions strictly assigned to the target `Role` model.
  4. Generate and sign a new JWT access token using `auth('api')->claims(...)`.
  5. Log the security audit event (`ROLE_CONTEXT_SWITCHED`).
  6. Return structured response payload.

* **Implementation Blueprint**:
```php
<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\UserSession;
use App\Services\RbacCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class ContextSwitchService
{
    /**
     * Switch user role context and issue a new JWT access token.
     *
     * @throws ValidationException
     */
    public function switchContext(User $user, string $businessUuid, string $targetRoleCode): array
    {
        // 1. Verify user status
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'user' => ['Your user account is not active.'],
            ]);
        }

        // 2. Resolve eligible roles for the user in this business
        $availableRoles = $user->roles()
            ->where(function ($q) use ($businessUuid) {
                $q->where('business_uuid', $businessUuid)
                  ->orWhereNull('business_uuid'); // Global roles (e.g. system admin)
            })
            ->get();

        $matchedRole = $availableRoles->first(function (Role $role) use ($targetRoleCode) {
            return strtolower(trim($role->code)) === strtolower(trim($targetRoleCode));
        });

        // If user is designated as owner in business or model
        if (!$matchedRole && $targetRoleCode === 'owner' && $user->is_owner) {
            $matchedRole = Role::where('code', 'owner')->first();
        }

        if (!$matchedRole) {
            Log::warning('[SECURITY_UNAUTHORIZED_ROLE_SWITCH]', [
                'user_uuid'     => $user->uuid,
                'business_uuid' => $businessUuid,
                'target_role'   => $targetRoleCode,
            ]);

            throw ValidationException::withMessages([
                'role' => ['You are not assigned the requested role in this business context.'],
            ]);
        }

        // 3. Load permissions strictly for the active role (cached in Redis)
        $permissions = RbacCacheService::getRolePermissions($matchedRole);

        // 4. Resolve current session & device from current token claims
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $currentPayload = $guard->payload();

        $sessionUuid = $currentPayload->get('sid');
        $deviceUuid  = $currentPayload->get('device_uuid');

        // Verify session is still valid in database
        $session = UserSession::where('uuid', $sessionUuid)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            throw ValidationException::withMessages([
                'session' => ['Active session expired or revoked. Please log in again.'],
            ]);
        }

        // 5. Generate NEW JWT token with active context
        $newAccessToken = $guard
            ->claims([
                'iss'           => 'smartpos-identity-service',
                'aud'           => 'smartpos-api',
                'sid'           => $session->uuid,
                'user_uuid'     => $user->uuid,
                'device_uuid'   => $deviceUuid,
                'business_uuid' => $businessUuid,
                'roles'         => $availableRoles->pluck('code')->unique()->values()->all(),
                'active_role'   => $matchedRole->code,
                'permissions'   => $permissions,
            ])
            ->login($user);

        // 6. Audit logging
        Log::info('[ROLE_CONTEXT_SWITCHED]', [
            'user_uuid'     => $user->uuid,
            'business_uuid' => $businessUuid,
            'old_role'      => $currentPayload->get('active_role', 'none'),
            'new_role'      => $matchedRole->code,
            'session_uuid'  => $session->uuid,
        ]);

        return [
            'token_type'    => 'Bearer',
            'access_token'  => $newAccessToken,
            'expires_in'    => config('jwt.ttl', 60) * 60,
            'active_context' => [
                'business_uuid' => $businessUuid,
                'role'          => $matchedRole->code,
            ],
            'roles'         => $availableRoles->pluck('code')->unique()->values()->all(),
            'permissions'   => $permissions,
        ];
    }
}
```

---

### 🌐 Phase 3: Controller & Route Registration

#### Task 3.1 — Add Controller Method in `AuthController`
* **Target File**: `app/Http/Controllers/Api/AuthController.php`
* **Code to Add**:
```php
    /**
     * Switch active role and business context.
     */
    public function switchContext(
        \App\Http\Requests\Auth\SwitchContextRequest $request,
        \App\Services\Auth\ContextSwitchService $service
    ) {
        $result = $service->switchContext(
            user: auth('api')->user(),
            businessUuid: $request->input('business_uuid'),
            targetRoleCode: $request->input('role')
        );

        return response()->json([
            'success' => true,
            'message' => 'Context switched successfully.',
            'data'    => $result,
        ]);
    }
```

#### Task 3.2 — Register Route in `routes/api/auth.php`
* **Target File**: `routes/api/auth.php`
* **Code to Add** (inside `Route::middleware(['auth:api', 'session.active'])` group):
```php
    Route::post(
        '/switch-context',
        [AuthController::class, 'switchContext']
    )->middleware('throttle:30,1');
```

---

### 🛡️ Phase 4: Middleware Enforcement

#### Task 4.1 — Create `ActiveRoleMiddleware`
* **Target File**: `app/Http/Middleware/ActiveRoleMiddleware.php`
* **Command**: `php artisan make:middleware ActiveRoleMiddleware`
* **Implementation Blueprint**:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveRoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $payload = auth('api')->payload();
        $activeRole = strtolower(trim($payload->get('active_role', '')));

        $allowedRoles = array_map('strtolower', $roles);

        if (!in_array($activeRole, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'error'   => 'ACTIVE_ROLE_UNAUTHORIZED',
                'message' => "This endpoint requires an active role of: " . implode(', ', $roles) . ". Please switch context.",
                'current_active_role' => $activeRole ?: null,
            ], 403);
        }

        return $next($request);
    }
}
```

#### Task 4.2 — Register Middleware Alias in `bootstrap/app.php`
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'active.role' => \App\Http\Middleware\ActiveRoleMiddleware::class,
    ]);
})
```

---

### ⚡ Phase 5: RBAC Cache Optimization (`RbacCacheService`)

#### Task 5.1 — Cache Role Permissions in Redis
* **Target File**: `app/Services/RbacCacheService.php`
* **Add Scoped Helper**:
```php
    /**
     * Get permission codes strictly for a single role (cached in Redis).
     */
    public static function getRolePermissions(Role $role): array
    {
        $cacheKey = "rbac:role:{$role->uuid}:permissions";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($role) {
            return $role->permissions()->pluck('code')->map('strtolower')->values()->all();
        });
    }

    /**
     * Invalidate role cache when permissions change.
     */
    public static function invalidateRole(Role $role): void
    {
        Cache::forget("rbac:role:{$role->uuid}:permissions");
    }
```

---

### 🧪 Phase 6: Automated Test Suite

#### Task 6.1 — Create `tests/Feature/Auth/SwitchContextTest.php`
```bash
php artisan make:test Auth/SwitchContextTest
```

* **Test Cases**:
  1. `test_user_can_switch_context_to_assigned_role()`
  2. `test_user_cannot_switch_to_unassigned_role()` -> Expects `422 Unprocessable Entity`
  3. `test_invalid_role_name_rejected_by_validation()` -> Expects `422`
  4. `test_unauthenticated_request_rejected()` -> Expects `401 Unauthorized`
  5. `test_switched_token_contains_active_role_and_business_uuid()`
  6. `test_permissions_in_jwt_are_strictly_scoped_to_active_role()`
  7. `test_active_role_middleware_blocks_unauthorized_role()`

---

## 🚀 Execution Priority Checklist for Backend Dev

*Detailed Sprint Backlog with code blueprints: [`identity-service/docs/BACKEND_DEV_TASKS.md`](file:///Users/macbookpro/Projects/smartpos/identity-service/docs/BACKEND_DEV_TASKS.md)*

### Module A: Multi-Role Account Context Switching
- [ ] **Task BE-01**: Update `config/jwt.php` TTL (`ttl => 30` minutes).
- [ ] **Task BE-02**: Create `app/Http/Requests/Auth/SwitchContextRequest.php`.
- [ ] **Task BE-03**: Create `app/Services/Auth/ContextSwitchService.php`.
- [ ] **Task BE-04**: Add `switchContext()` method in `AuthController.php`.
- [ ] **Task BE-05**: Register `Route::post('/switch-context')` in `routes/api/auth.php`.
- [ ] **Task BE-06**: Create `ActiveRoleMiddleware.php` and register alias `'active.role'`.
- [ ] **Task BE-07**: Update `RbacCacheService.php` to cache scoped role permissions in Redis.
- [ ] **Task BE-08**: Run `php artisan test --filter=SwitchContextTest`.

### Module B: Email & Phone Number SMS Verification
- [ ] **Task BE-09**: Create `app/Services/Sms/SmsService.php` with `log` simulation driver.
- [ ] **Task BE-10**: Create `app/Http/Requests/Auth/SendOtpRequest.php` (`email` and `phone` support).
- [ ] **Task BE-11**: Update `ForgotPasswordController.php` to support SMS OTP delivery & verification.
- [ ] **Task BE-12**: Create `app/Helpers/PhoneNumberHelper.php` for E.164 normalization.
- [ ] **Task BE-13**: Add SMS driver configuration in `config/services.php` and `.env.example`.
- [ ] **Task BE-14**: Run `php artisan test --filter=OtpVerificationTest`.

