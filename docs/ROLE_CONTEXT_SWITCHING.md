# SmartPOS Identity Service — Account Role & Business Context Switching

## 1. Executive Summary & Architecture Goal

In SmartPOS, a single identity (e.g. `admin@gmail.com`) can belong to multiple business tenants and hold multiple distinct roles within each tenant:

```text
User: admin@gmail.com

Business A
├── Owner
├── Admin
└── Cashier

Business B
└── Admin
```

To maintain strict zero-trust security across microservices, authorization decisions cannot rely on one combined list of permissions or a single static role. The system must know on every request:

```text
WHO?                  -> user_uuid
WHICH DEVICE?         -> device_uuid
WHICH BUSINESS?       -> business_uuid
WHICH ROLE IS ACTIVE? -> active_role
WHAT CAN USER DO?     -> permissions (strictly scoped to active_role)
```

The user can safely switch between assigned roles and businesses without logging out or invalidating their device session.

---

## 2. Specification Tasks (Backend & Identity Service)

### Task 1 — Add Active Context to JWT Payload
When issuing an access token (on login or context switch), include explicit active context claims:

```json
{
  "iss": "smartpos-identity-service",
  "aud": "smartpos-api",
  "sub": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "user_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "device_uuid": "e2c34981-5d9c-4f1b-8012-78d10b784e14",
  "business_uuid": "7a3b49c1-12f4-4b55-8910-bc89ef234567",
  "roles": [
    "owner",
    "admin",
    "cashier"
  ],
  "active_role": "owner",
  "permissions": [
    "businesses.view",
    "businesses.update",
    "business_users.manage",
    "outlets.manage"
  ],
  "sid": "sess_89ef4321_ab12_43cd_bce5_876543210fed",
  "jti": "jti_99aa11bb_22cc_33dd_44ee_55ff66778899",
  "iat": 1741600000,
  "nbf": 1741600000,
  "exp": 1741601800
}
```

* **`roles`**: Full array of roles assigned to the user within the active business that they are eligible to switch into.
* **`active_role`**: The single role currently in use.
* **`permissions`**: **Strictly the permissions granted to `active_role`** (never the sum of all roles).

---

### Task 2 — Create Switch Context API Endpoint
* **Method**: `POST`
* **Route**: `/api/v1/auth/switch-context`
* **Middleware**: `jwt.auth`, `throttle:30,1`

#### Request Payload
```json
{
  "business_uuid": "7a3b49c1-12f4-4b55-8910-bc89ef234567",
  "role": "owner"
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Context switched successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "eyJhbGciOiJSUzI1NiIs...",
    "expires_in": 1800,
    "active_context": {
      "business_uuid": "7a3b49c1-12f4-4b55-8910-bc89ef234567",
      "role": "owner"
    },
    "roles": [
      "owner",
      "admin",
      "cashier"
    ],
    "permissions": [
      "businesses.view",
      "businesses.update",
      "business_users.manage"
    ]
  }
}
```

---

### Task 3 — Create `SwitchContextRequest` FormRequest
Create `app/Http/Requests/Auth/SwitchContextRequest.php`:

```php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchContextRequest extends FormRequest
{
    public const ALLOWED_ROLES = ['owner', 'admin', 'manager', 'cashier', 'staff'];

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
}
```
* Whitelists valid role slugs to prevent arbitrary string injection.

---

### Task 4 — Verify Business Membership & Authorization
Before issuing a new token, verify:
1. Authenticated user exists and `status === 'active'`.
2. Target business exists and `status === 'active'`.
3. User is an active member of `business_uuid`.
4. The requested `role` is actually assigned to this user within this business.
5. If the user does not possess the requested role or is not an active member, immediately abort with `403 Forbidden`:

```json
{
  "success": false,
  "error": "FORBIDDEN_ROLE_CONTEXT",
  "message": "You do not have authorization to assume this role in the specified business."
}
```

---

### Task 5 — Create `ContextSwitchService`
Create `app/Services/Auth/ContextSwitchService.php`:
* **Responsibilities**:
  1. Validate user and business membership.
  2. Resolve and verify assigned roles for target business.
  3. Load and cache permissions for requested `active_role`.
  4. Generate and sign new short-lived JWT.
  5. Emit `SecurityEvent::ROLE_CONTEXT_SWITCHED`.
  6. Return formatted active context payload.

---

### Task 6 — Improve JWT Service
Update `app/Services/Auth/JwtService.php` to accept context parameters:
```php
public function generateAccessToken(
    User $user,
    string $sessionUuid,
    string $deviceUuid,
    string $businessUuid,
    string $activeRole,
    array $availableRoles,
    array $permissions
): string;
```

---

### Task 7 — Generate New Token When Switching (Immutability)
* Never attempt to edit or mutate an existing JWT in place.
* Issue a brand-new signed JWT with updated `active_role`, `business_uuid`, and `permissions`.
* Client receives the new JWT and updates its `Authorization: Bearer` storage.

---

### Task 8 — Scoped Role Permissions (No Permission Merging)
* Prevent privilege leakage across modes.
* When active role is `cashier`:
  - Allowed: `pos.access`, `pos.checkout`, `products.view`, `customers.view`.
  - Blocked: `businesses.update`, `roles.manage`, `subscriptions.view` (even if the user also has the `owner` role assigned).

---

### Task 9 — `ActiveRoleMiddleware`
Create `app/Http/Middleware/ActiveRoleMiddleware.php`:
```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveRoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $activeRole = $request->attributes->get('jwt_active_role');

        if (!in_array($activeRole, $roles, true)) {
            return response()->json([
                'success' => false,
                'error'   => 'ACTIVE_ROLE_UNAUTHORIZED',
                'message' => 'Your current active role does not have access to this action. Please switch roles.',
            ], 403);
        }

        return $next($request);
    }
}
```
* Register alias: `'active.role' => \App\Http\Middleware\ActiveRoleMiddleware::class`.

---

### Task 10 — Continue Permission Middleware
Endpoints continue checking granular permissions:
```php
Route::post('/products', [ProductController::class, 'store'])
    ->middleware(['jwt.auth', 'permission:products.create']);
```

---

### Task 11 — `BusinessContextMiddleware`
Create `app/Http/Middleware/BusinessContextMiddleware.php`:
* Ensures the `business_uuid` in route parameters or query matches the `business_uuid` in the JWT token.
* Prevents cross-tenant parameter tampering:
  ```text
  JWT: business_uuid = A
  Request: GET /businesses/B/products -> 403 Forbidden
  ```

---

### Task 12 — Access Token & Refresh Token Lifetimes
* **Access Token**: Short-lived (15 to 30 minutes, `1800` seconds).
* **Refresh Token**: Long-lived (30 days) stored securely in HttpOnly cookie or secure storage.
* Minimizes window of exposure if a role or permission is altered on the backend.

---

### Task 13 — Preserve Refresh Token Session on Role Switch
* When switching roles, **reuse the same `session_uuid` and `device_uuid`**.
* Do not revoke the active refresh session or create duplicate entries in `user_sessions`.
* Only rotate or issue the `access_token`.

---

### Task 14 — Redis Permission Caching & Invalidation
* Cache role permissions with Redis:
  ```text
  Key: role:{role_uuid}:permissions
  TTL: 15-30 minutes (900-1800s)
  ```
* Invalidate cache on role/permission updates:
  ```php
  RolePermissionUpdatedEvent -> Redis::del("role:{$roleUuid}:permissions");
  ```

---

### Task 15 — Security Audit Logging
Log context switches in `security_events`:
```json
{
  "event_type": "ROLE_CONTEXT_SWITCHED",
  "user_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "session_uuid": "sess_89ef4321_ab12_43cd_bce5_876543210fed",
  "device_uuid": "e2c34981-5d9c-4f1b-8012-78d10b784e14",
  "business_uuid": "7a3b49c1-12f4-4b55-8910-bc89ef234567",
  "old_role": "admin",
  "new_role": "owner",
  "ip_address": "192.168.1.10",
  "user_agent": "Mozilla/5.0 ...",
  "created_at": "2026-09-10T08:21:00Z"
}
```

---

### Task 16 — Protect Owner Role Against Self-Promotion
* Strict security rule: A user cannot promote themselves to `owner` via `PUT /users/me` or `/roles`.
* Ownership can only be assigned during business creation or transferred through dedicated secured APIs.

---

### Task 17 — Separate Role Switch from Ownership Transfer
* Role switch: Changes client operating mode between roles already assigned to the user.
* Ownership transfer: Changes legal tenant owner entity via `POST /businesses/{business}/transfer-ownership`.

---

### Task 18 — Frontend Role Switcher Integration
* Header & User Menu role switcher displays current active role with checkmark.
* Clicking another role sends `POST /api/v1/auth/switch-context`.
* Replaces active `access_token` in cookies and memory, updating permissions.

---

### Task 19 — Frontend State Architecture
* Centralize in `useAuthStore`:
  ```typescript
  interface AuthState {
    user: User | null;
    activeRole: 'owner' | 'admin' | 'manager' | 'cashier' | 'staff' | null;
    availableRoles: string[];
    activeBusinessUuid: string | null;
    permissions: string[];
    switchRole: (role: string, businessUuid?: string) => Promise<void>;
  }
  ```

---

### Task 20 — Automated Test Suites
1. **Unit Tests**:
   - `test_user_can_switch_from_admin_to_owner`
   - `test_user_can_switch_from_owner_to_cashier`
   - `test_switching_to_unassigned_role_is_forbidden`
   - `test_new_jwt_contains_strictly_scoped_permissions`
2. **Security Tests**:
   - `test_cross_business_role_switching_blocked`
   - `test_revoked_session_cannot_switch_context`
   - `test_tampered_active_role_claim_rejected`
