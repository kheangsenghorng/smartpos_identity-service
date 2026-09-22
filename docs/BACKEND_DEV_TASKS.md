# SmartPOS Identity Service — Backend Developer Task Backlog

This backlog provides comprehensive, actionable task tickets for the **Backend Developer** to implement:
1. **Module A: Account Role & Multi-Business Context Switching** (`BE-01` to `BE-08`)
2. **Module B: Email & Phone Number SMS Verification System** (`BE-09` to `BE-14`)

---

## 📊 Sprint Overview & Task Matrix

| Task ID | Priority | Module | Title | Target File(s) | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`BE-01`** | **P0** | Context Switch | JWT Configuration & Short-Lived TTL | `config/jwt.php` | `Ready` |
| **`BE-02`** | **P0** | Context Switch | Create `SwitchContextRequest` DTO & Validation | `app/Http/Requests/Auth/SwitchContextRequest.php` | `Ready` |
| **`BE-03`** | **P0** | Context Switch | Create `ContextSwitchService` Business Logic | `app/Services/Auth/ContextSwitchService.php` | `Ready` |
| **`BE-04`** | **P0** | Context Switch | Add `switchContext()` Controller Endpoint | `app/Http/Controllers/Api/AuthController.php` | `Ready` |
| **`BE-05`** | **P0** | Context Switch | Register API Route with Rate Limiting | `routes/api/auth.php` | `Ready` |
| **`BE-06`** | **P0** | Context Switch | Create & Register `ActiveRoleMiddleware` | `app/Http/Middleware/ActiveRoleMiddleware.php` | `Ready` |
| **`BE-07`** | **P1** | Context Switch | Redis Permission Caching & Invalidation | `app/Services/RbacCacheService.php` | `Ready` |
| **`BE-08`** | **P1** | Context Switch | Automated Tests for Context Switching | `tests/Feature/Auth/SwitchContextTest.php` | `Ready` |
| **`BE-09`** | **P0** | Verification | SMS Gateway Service & Mock Log Driver | `app/Services/Sms/SmsService.php` | `Ready` |
| **`BE-10`** | **P0** | Verification | Multi-Channel OTP Request (Email & Phone) | `app/Http/Requests/Auth/SendOtpRequest.php` | `Ready` |
| **`BE-11`** | **P0** | Verification | Update `ForgotPasswordController` for Phone SMS | `app/Http/Controllers/Api/ForgotPasswordController.php` | `Ready` |
| **`BE-12`** | **P1** | Verification | Phone Number Normalization & E.164 Helper | `app/Helpers/PhoneNumberHelper.php` | `Ready` |
| **`BE-13`** | **P1** | Verification | Environment Configuration for SMS Driver | `config/services.php`, `.env.example` | `Ready` |
| **`BE-14`** | **P1** | Verification | Automated Tests for Email & Phone SMS OTP | `tests/Feature/Auth/OtpVerificationTest.php` | `Ready` |

---

## 🎫 Detailed Task Tickets

### 🏷️ TASK `BE-01`: JWT Configuration & Short-Lived TTL
- **Target File**: `config/jwt.php`
- **Objective**: Reduce Access Token TTL to 15–30 minutes to reduce security exposure when roles change, while relying on the 30-day refresh session.
- **Changes**:
  ```php
  'ttl' => env('JWT_TTL', 30), // 30 minutes
  'refresh_ttl' => env('JWT_REFRESH_TTL', 43200), // 30 days in minutes
  ```
- **Acceptance Criteria**:
  - `exp` claim in issued JWT is 30 minutes from `iat`.

---

### 🏷️ TASK `BE-02`: Create `SwitchContextRequest` FormRequest
- **Target File**: `app/Http/Requests/Auth/SwitchContextRequest.php`
- **Objective**: Validate incoming `business_uuid` and ensure `role` is an allowed enum value.
- **Allowed Roles**: `'owner'`, `'admin'`, `'manager'`, `'cashier'`, `'staff'`.
- **Implementation**:
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

      public function messages(): array
      {
          return [
              'role.in' => 'Invalid role specified. Allowed: ' . implode(', ', self::ALLOWED_ROLES),
          ];
      }
  }
  ```

---

### 🏷️ TASK `BE-03`: Create `ContextSwitchService`
- **Target File**: `app/Services/Auth/ContextSwitchService.php`
- **Objective**: Centralize business logic for verifying user membership, loading permissions strictly for the active role, signing a new JWT, and emitting an audit log.
- **Key Method**:
  ```php
  public function switchContext(User $user, string $businessUuid, string $targetRoleCode): array
  ```
- **Business Rules**:
  1. Fail if `$user->status !== 'active'` &rarr; `422`.
  2. Query `$user->roles()` matching `$businessUuid` or `business_uuid IS NULL` (global admin roles).
  3. If user has `is_owner == true` and requests `'owner'`, grant owner role.
  4. If target role is not assigned to user for this business, throw `ValidationException` (`422`) or abort `403`.
  5. Load permissions strictly for the chosen role (`RbacCacheService::getRolePermissions($matchedRole)`).
  6. Generate a new JWT preserving the existing `sid` (session UUID) and `device_uuid`.
  7. Log `[ROLE_CONTEXT_SWITCHED]`.

---

### 🏷️ TASK `BE-04`: Add `switchContext()` Controller Endpoint
- **Target File**: `app/Http/Controllers/Api/AuthController.php`
- **Implementation**:
  ```php
  public function switchContext(
      \App\Http\Requests\Auth\SwitchContextRequest $request,
      \App\Services\Auth\ContextSwitchService $service
  ) {
      $data = $service->switchContext(
          user: auth('api')->user(),
          businessUuid: $request->input('business_uuid'),
          targetRoleCode: $request->input('role')
      );

      return response()->json([
          'success' => true,
          'message' => 'Context switched successfully.',
          'data'    => $data,
      ]);
  }
  ```

---

### 🏷️ TASK `BE-05`: Register API Route
- **Target File**: `routes/api/auth.php`
- **Route Definition**:
  ```php
  Route::middleware(['auth:api', 'session.active'])->group(function () {
      Route::post(
          '/switch-context',
          [AuthController::class, 'switchContext']
      )->middleware('throttle:30,1');
  });
  ```
- **Endpoint**: `POST /api/v1/auth/switch-context`

---

### 🏷️ TASK `BE-06`: Create `ActiveRoleMiddleware`
- **Target File**: `app/Http/Middleware/ActiveRoleMiddleware.php`
- **Objective**: Protect owner/admin only routes from accounts in cashier/staff mode.
- **Implementation**:
  ```php
  namespace App\Http\Middleware;

  use Closure;
  use Illuminate\Http\Request;

  class ActiveRoleMiddleware
  {
      public function handle(Request $request, Closure $next, string ...$roles)
      {
          $payload = auth('api')->payload();
          $activeRole = strtolower(trim($payload->get('active_role', '')));

          if (!in_array($activeRole, array_map('strtolower', $roles), true)) {
              return response()->json([
                  'success' => false,
                  'error'   => 'ACTIVE_ROLE_UNAUTHORIZED',
                  'message' => "Requires active role of: " . implode(', ', $roles) . ". Please switch context.",
                  'current_active_role' => $activeRole ?: null,
              ], 403);
          }

          return $next($request);
      }
  }
  ```
- **Registration**: Register alias in `bootstrap/app.php`:
  ```php
  $middleware->alias(['active.role' => \App\Http\Middleware\ActiveRoleMiddleware::class]);
  ```

---

### 🏷️ TASK `BE-07`: Redis Permission Caching
- **Target File**: `app/Services/RbacCacheService.php`
- **Implementation**:
  ```php
  public static function getRolePermissions(Role $role): array
  {
      $key = "rbac:role:{$role->uuid}:permissions";

      return Cache::remember($key, now()->addMinutes(30), function () use ($role) {
          return $role->permissions()->pluck('code')->map('strtolower')->values()->all();
      });
  }

  public static function invalidateRole(Role $role): void
  {
      Cache::forget("rbac:role:{$role->uuid}:permissions");
  }
  ```

---

### 🏷️ TASK `BE-08`: Automated Tests for Context Switching
- **Target File**: `tests/Feature/Auth/SwitchContextTest.php`
- **Command**: `php artisan make:test Auth/SwitchContextTest`
- **Test Scenarios**:
  1. `test_user_can_switch_from_admin_to_owner()`
  2. `test_user_cannot_switch_to_unassigned_role()`
  3. `test_switched_token_contains_active_role_and_business_uuid()`
  4. `test_cashier_token_does_not_contain_owner_permissions()`
  5. `test_active_role_middleware_blocks_unauthorized_role()`

---

### 🏷️ TASK `BE-09`: SMS Gateway Service & Mock Log Driver
- **Target File**: `app/Services/Sms/SmsService.php`
- **Objective**: Multi-provider SMS gateway supporting `log` (free dev/testing) and live gateways (Twilio, Plasgate, AWS SNS).
- **Implementation**:
  ```php
  namespace App\Services\Sms;

  use Illuminate\Support\Facades\Log;

  class SmsService
  {
      public function sendOtp(string $phone, string $code): bool
      {
          $driver = config('services.sms.driver', 'log');

          if ($driver === 'log') {
              Log::info("[SMS_OTP_SIMULATION] To: {$phone} | Code: {$code} | Expires: 10 mins");
              return true;
          }

          // Production drivers (Twilio, Plasgate, etc.)
          return true;
      }
  }
  ```

---

### 🏷️ TASK `BE-10`: Multi-Channel OTP Request (Email & Phone)
- **Target File**: `app/Http/Requests/Auth/SendOtpRequest.php`
- **Objective**: Accept either `email` OR `phone` for OTP delivery.
- **Validation Rules**:
  ```php
  return [
      'channel'    => ['required', 'string', 'in:email,sms'],
      'identifier' => ['required', 'string', 'max:150'],
  ];
  ```

---

### 🏷️ TASK `BE-11`: Update `ForgotPasswordController` for Phone SMS
- **Target File**: `app/Http/Controllers/Api/ForgotPasswordController.php`
- **Objective**: Support sending and verifying OTP via Phone SMS in addition to Email.
- **Key Enhancements**:
  1. In `sendCode(Request $request)`:
     - Check if `channel === 'sms'`: search `$user = User::where('phone', $identifier)->first()`.
     - Dispatch `app(SmsService::class)->sendOtp($user->phone, $code)`.
     - Record in `auth_otps` with `channel = 'sms'`.
  2. In `verifyCode(Request $request)`:
     - Query `auth_otps` with matching `identifier` and `channel`.
     - Verify with row-level lock (`lockForUpdate()`).

---

### 🏷️ TASK `BE-12`: Phone Number Helper (E.164 Normalization)
- **Target File**: `app/Helpers/PhoneNumberHelper.php`
- **Objective**: Standardize Cambodian / International phone numbers:
  - `012345678` &rarr; `+85512345678`
  - `+855 12 345 678` &rarr; `+85512345678`

---

### 🏷️ TASK `BE-13`: SMS Environment Config
- **Target Files**: `config/services.php`, `.env.example`
- **Add to `config/services.php`**:
  ```php
  'sms' => [
      'driver' => env('SMS_DRIVER', 'log'),
      'from'   => env('SMS_FROM', 'SmartPOS'),
  ],
  ```

---

### 🏷️ TASK `BE-14`: Automated Tests for Email & SMS Verification
- **Target File**: `tests/Feature/Auth/OtpVerificationTest.php`
- **Test Scenarios**:
  1. `test_send_email_otp_success()`
  2. `test_send_sms_otp_success()` (asserts log output)
  3. `test_verify_sms_otp_success()`
  4. `test_brute_force_otp_lockout_after_5_attempts()`
  5. `test_expired_otp_rejected()`

---

## 🧪 Quick Curl Verification Commands

### Test 1: Context Switch API
```bash
curl -X POST "http://localhost:8000/api/v1/auth/switch-context" \
  -H "Authorization: Bearer <CURRENT_JWT>" \
  -H "Content-Type: application/json" \
  -d '{
    "business_uuid": "7a3b49c1-12f4-4b55-8910-bc89ef234567",
    "role": "owner"
  }'
```

### Test 2: Request SMS OTP
```bash
curl -X POST "http://localhost:8000/api/v1/auth/forgot-password/send-code" \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "identifier": "+85512345678"
  }'
```

### Test 3: Verify SMS OTP
```bash
curl -X POST "http://localhost:8000/api/v1/auth/verify-reset-code" \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "identifier": "+85512345678",
    "code": "123456"
  }'
```
