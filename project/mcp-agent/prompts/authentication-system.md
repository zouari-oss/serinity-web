# Authentication System

You are a senior Symfony security architect and full-stack engineer specialized in Symfony 6/7, API security, and modern authentication systems (JWT, cookies, OAuth-ready design). You also have strong frontend skills (Twig, UX, animations).

Context:

- Existing Symfony project (access-control module already scaffolded)
- Current login route is static and only renders a Twig template:

    #[Route('/login', name: 'ac_ui_login', methods: ['GET'])]
    public function login(): Response
    {
    return $this->render('access_control/pages/login.html.twig');
    }

- Logo path (to integrate in UI):
  /home/zouari_omar/Desktop/projects/zouari-oss/serinity/serinity-web/res/img/logo

Objective:

Transform the static login page into a complete, secure, modern authentication system with both backend logic and improved UI/UX.

---

### Tasks

### 1. Security Architecture Design

- Design a modern authentication system using:
  - Symfony Security component
  - JWT authentication (via LexikJWTAuthenticationBundle or equivalent)
  - Optional refresh tokens
  - Secure cookies (HttpOnly, SameSite, Secure)
- Explain whether to use:
  - Stateless JWT
  - Session-based auth
  - Hybrid approach (recommended if relevant)

---

### 2. Symfony Security Configuration

- Provide full configuration for:
  - security.yaml (firewalls, providers, password hashing)
  - User provider (Doctrine entity)
  - Custom authenticator (if needed)
- Configure login, logout, and access control rules

---

### 3. User Entity & Persistence

- Implement or refine:
  - User entity (email, password, roles, timestamps)
  - Password hashing (auto / bcrypt / argon2)
- Ensure Doctrine mapping is clean and production-ready

---

### 4. Authentication Logic

- Implement:
  - Sign-in (login)
  - Sign-up (registration)
  - Logout
- Include:
  - Validation (Symfony Validator)
  - Error handling (invalid credentials, user exists, etc.)
- Use DTOs where appropriate

---

### 5. API Endpoints (API-first approach)

Create REST endpoints:

- POST /api/auth/login
- POST /api/auth/register
- POST /api/auth/logout
- GET /api/auth/me

Return proper JSON responses:

- success responses (token, user data)
- error responses (structured)

---

### 6. JWT Integration

- Configure JWT bundle
- Show:
  - token generation
  - token validation
  - attaching JWT to requests
- Optional:
  - Refresh token mechanism

---

### 7. Controller Refactor

Refactor the existing login controller:

- Keep `/login` for UI rendering
- Move logic to API controllers
- Keep controllers thin, use services

---

### 8. Services Layer

- Create services for:
  - Authentication logic
  - User management
- Use dependency injection properly
- Follow SOLID principles

---

### 9. Frontend (Twig UI Enhancement)

Enhance the login page:

- Integrate logo from:
  `/res/img/logo` (adapt path properly for Symfony public assets)

- Improve UI with:
  - modern layout (centered card, clean spacing)
  - CSS animations (fade-in, input focus effects, button transitions)
  - responsive design

- Add:
  - login + signup toggle (single page or separate pages)
  - form validation feedback
  - loading states

---

### 10. Optional (Advanced UX)

- Add:
  - "Remember me"
  - password visibility toggle
  - subtle animations (CSS or minimal JS)
  - dark/light theme readiness

---

### 11. Output Requirements

Provide:

- Full file structure
- All Symfony config files (security.yaml, routes, services)
- Entities, DTOs, services, controllers
- Twig templates (login/register)
- CSS (or Tailwind if preferred)
- Example API responses

Code must be:

- Production-ready
- Clean and modular
- Using PHP 8+ features
- Following Symfony best practices

---

### Goal

Deliver a fully working, secure, scalable authentication system (login + signup) with modern UX, replacing the current static login page.
