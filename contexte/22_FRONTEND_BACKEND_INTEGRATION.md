# 🔗 Phase 5 - Frontend-Backend Integration Guide

**Status:** Frontend ready, Backend API endpoints need JWT authentication integration

---

## 📊 Current State

### ✅ Frontend (Complete)
- Vue 3 dev server running on **http://localhost:5173/**
- All 21 components and views implemented
- API client configured with JWT interceptors
- Production build successful (`npm run build`)
- Ready for API integration testing

### ⚠️ Backend (Partial)
- **Authentication:** Form-based only (no JSON API endpoint)
- **Database APIs:** ✅ Implemented and ready
  - `GET/POST/PUT/DELETE /api/admin/database-connections`
  - `GET /api/admin/database-connections/:id/test`
  - `POST /api/admin/database-connections/:id/test`
- **Audit APIs:** ✅ Implemented and ready
  - `GET /api/admin/audit/logs`
  - `GET /api/admin/audit/user/:userId`
  - `GET /api/admin/audit/access-denied`
  - `GET /api/admin/audit/resource/:type/:id`
- **Database Browser APIs:** ✅ Implemented
  - `GET /api/admin/database/:id/structures`
  - `POST /api/admin/database/:id/query`

### ❌ API Gap Identified
**Frontend expects:** `POST /api/login` → returns `{token, refreshToken, expiresAt}`
**Backend provides:** `POST /login` → renders HTML form (Symfony form authentication)

---

## 🚀 Next Steps (Priority Order)

### Phase 5.2A - Implement JWT Authentication API (HIGH PRIORITY)

Create a new API endpoint that returns JSON tokens instead of rendering HTML forms.

#### 1. Create Authentication API Controller

**File:** `src/Controller/Admin/API/AuthController.php`

```php
<?php

namespace App\Controller\Admin\API;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
{
    /**
     * Login endpoint for mobile/SPA apps
     * 
     * POST /api/login
     * Content-Type: application/json
     * 
     * Request:
     * {
     *   "username": "admin",
     *   "password": "password123"
     * }
     * 
     * Response (200):
     * {
     *   "success": true,
     *   "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
     *   "refreshToken": "...",
     *   "expiresAt": "2026-07-02T12:00:00Z"
     * }
     * 
     * Response (401):
     * {
     *   "success": false,
     *   "message": "Invalid credentials"
     * }
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, AuthenticationUtils $authUtils): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json(
                ['success' => false, 'message' => 'Missing username or password'],
                400
            );
        }
        
        // TODO: Authenticate user and generate JWT token
        // This requires implementing JWT token generation
        
        return $this->json([
            'success' => false,
            'message' => 'Authentication not yet implemented'
        ], 401);
    }

    /**
     * Validate token endpoint
     * 
     * GET /api/validate-token
     * Authorization: Bearer <token>
     * 
     * Response (200):
     * {
     *   "success": true,
     *   "user": {
     *     "id": 1,
     *     "email": "admin@example.com",
     *     "displayName": "Admin User",
     *     "isGlobalAdmin": true
     *   }
     * }
     */
    #[Route('/validate-token', name: 'validate_token', methods: ['GET'])]
    public function validateToken(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(
                ['success' => false, 'message' => 'Invalid token'],
                401
            );
        }
        
        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'isGlobalAdmin' => in_array('ROLE_GLOBAL_ADMIN', $user->getRoles())
            ]
        ]);
    }

    /**
     * Logout endpoint
     * 
     * POST /api/logout
     * Authorization: Bearer <token>
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        // Token-based logout typically just requires frontend to discard token
        return $this->json(['success' => true, 'message' => 'Logged out']);
    }
}
```

#### 2. Implement JWT Token Generation

**File:** `src/Service/JwtTokenService.php`

```php
<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtTokenService
{
    public function __construct(
        private string $jwtSecret,
        private string $jwtAlgorithm = 'HS256',
        private int $tokenExpiry = 3600 // 1 hour
    ) {}

    /**
     * Generate JWT token for user
     */
    public function generateToken(User $user): array
    {
        $now = new \DateTime();
        $expiresAt = $now->modify("+{$this->tokenExpiry} seconds");
        
        $payload = [
            'iss' => 'obstack',
            'sub' => (string)$user->getId(),
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];
        
        $token = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);
        
        return [
            'token' => $token,
            'refreshToken' => $this->generateRefreshToken($user),
            'expiresAt' => $expiresAt->format(\DateTime::ATOM),
            'expiresIn' => $this->tokenExpiry,
        ];
    }

    /**
     * Verify and decode JWT token
     */
    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid token: {$e->getMessage()}");
        }
    }

    /**
     * Generate refresh token
     */
    private function generateRefreshToken(User $user): string
    {
        // Refresh tokens typically last longer (7-30 days)
        $expiresAt = (new \DateTime())->modify('+7 days');
        
        $payload = [
            'iss' => 'obstack',
            'sub' => (string)$user->getId(),
            'type' => 'refresh',
            'iat' => (new \DateTime())->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ];
        
        return JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);
    }
}
```

#### 3. Update Dependencies

Add JWT support to `composer.json`:

```bash
composer require firebase/jwt
```

#### 4. Configure JWT Secret

Add to `.env` or `.env.local`:

```
JWT_SECRET=your-super-secret-key-change-this-in-production
JWT_ALGORITHM=HS256
JWT_EXPIRY=3600
```

---

### Phase 5.2B - Implement JWT Guard (Middleware)

Create a Symfony guard to authenticate API requests using JWT tokens.

**File:** `src/Security/JwtAuthenticator.php`

```php
<?php

namespace App\Security;

use App\Service\JwtTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JwtTokenService $tokenService,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('Invalid authorization header');
        }
        
        $token = substr($authHeader, 7);
        
        try {
            $decoded = $this->tokenService->verifyToken($token);
            $userId = $decoded['sub'];
        } catch (\Exception $e) {
            throw new AuthenticationException("Invalid token: {$e->getMessage()}");
        }
        
        return new SelfValidatingPassport(
            new UserBadge($userId)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?JsonResponse
    {
        return null; // Let request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey()
        ], 401);
    }
}
```

---

### Phase 5.2C - Configure Firewall for API Routes

Update `config/packages/security.yaml`:

```yaml
security:
  firewalls:
    api:
      pattern: ^/api/
      stateless: true
      authenticators:
        - App\Security\JwtAuthenticator
    
    main:
      lazy: true
      provider: app_user_provider
      form_login:
        login_path: app_login
        check_path: app_login
      logout:
        path: app_logout
        target: app_login
```

---

## 🧪 Testing Workflow

### 1. Test Backend API Connectivity

```bash
# Test database connections endpoint
curl -X GET "http://localhost:8000/api/admin/database-connections" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test audit logs
curl -X GET "http://localhost:8000/api/admin/audit/logs?limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Test Frontend Dev Server

Navigate to: **http://localhost:5173/**

Expected behavior:
1. Redirects to login page (no token)
2. Login form appears
3. Enter credentials: `admin@obstack.local` / `TestPassword123`
4. On success: Redirect to dashboard
5. Dashboard loads statistics
6. Can navigate between views

### 3. Check Browser Console

Open DevTools (F12) → Network tab:
- Look for API requests to `http://localhost:8000/api/...`
- Check request headers for `Authorization: Bearer ...`
- Verify response status codes (200, 401, etc)
- Check JSON responses

### 4. Troubleshooting

```bash
# Frontend errors?
# Check console in browser, look for:
# - 404 errors (API endpoint doesn't exist)
# - 401 errors (authentication failed)
# - 500 errors (server-side error)

# Backend logs?
tail -f /path/to/var/log/dev.log

# Clear caches?
php bin/console cache:clear
```

---

## 📋 Implementation Checklist

### Backend JWT Authentication (HIGH PRIORITY)
- [ ] Create `AuthController.php` with `/api/login` endpoint
- [ ] Create `JwtTokenService.php` for token generation
- [ ] Create `JwtAuthenticator.php` for request authentication
- [ ] Update `security.yaml` with API firewall
- [ ] Install `firebase/jwt` package
- [ ] Test endpoints with `curl`
- [ ] Verify token is returned on successful login

### Frontend Integration (IMMEDIATE)
- [ ] Test login page works with new JWT endpoint
- [ ] Verify auth store receives and stores token
- [ ] Test API interceptor adds Bearer token
- [ ] Test 401 response redirects to login
- [ ] Test dashboard loads with authenticated user

### Additional Endpoints (PHASE 5.3)
- [ ] User management API (`/api/admin/users`)
- [ ] Role/permission API (`/api/admin/roles`)
- [ ] Settings API (`/api/admin/settings`)
- [ ] Export endpoints (CSV, JSON)

---

## 🔐 Security Considerations

### In Development
- Use a test JWT secret: `dev-secret-key-change-in-production`
- Tokens expire in 1 hour
- No HTTPS required locally

### In Production
- Generate strong JWT secret (32+ characters)
- Use environment variables for secrets
- Enable HTTPS for all API calls
- Use refresh tokens for longer sessions
- Implement token revocation (logout)
- Add rate limiting on login endpoint
- Log authentication failures

---

## 📚 References

### Frontend Architecture
- Frontend expects: `src/services/auth.ts` → POST `/api/login`
- Auth store: `src/stores/auth.ts` (manages token, redirects on 401)
- API interceptor: `src/services/api.ts` (adds Bearer token)
- Router guard: `src/router/index.ts` (requires auth for protected routes)

### Backend Existing APIs
- Database connections: `src/Controller/Admin/API/DatabaseConnectionController.php`
- Audit logs: `src/Controller/Admin/API/AuditLogController.php`
- Database browser: `src/Controller/Admin/API/DatabaseBrowserController.php`

---

## 🎯 Expected Timeline

| Phase | Task | Estimated Time |
|-------|------|-----------------|
| 5.2A | Implement JWT Auth API | 2-3 hours |
| 5.2B | Implement JWT Guard | 1-2 hours |
| 5.2C | Configure Firewall | 30 minutes |
| 5.3 | Full integration testing | 2-3 hours |
| 5.4 | Fix remaining API issues | 2-4 hours |

**Total: 8-12 hours**

---

## ✅ Success Criteria

When this integration is complete:
- ✅ Frontend login page accepts credentials
- ✅ Backend returns JWT token on successful login
- ✅ Frontend stores token in localStorage
- ✅ Frontend sends token in Authorization header for all API calls
- ✅ Dashboard loads with authenticated user data
- ✅ Database connections list loads via API
- ✅ Audit logs load via API
- ✅ 401 errors redirect to login
- ✅ Logout clears token and redirects to login

---

## 📞 Notes

- The frontend is **100% ready** for API integration
- Backend **database and audit APIs** are implemented and ready
- Only **authentication API** needs to be implemented
- This is estimated at **2-3 hours of backend development**
- After that, frontend should work end-to-end

**Status:** Frontend infrastructure complete ✅
**Blocker:** Backend JWT authentication API (easily fixable)
**Estimated completion:** Phase 5.2 completion in 1-2 days
