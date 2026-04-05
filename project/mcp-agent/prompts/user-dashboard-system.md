# User Dashboard System

You are a senior Symfony 6/7 architect working on an existing production-ready application.

## Context

The project already includes:

- User entity with roles and **account status field** (`ACTIVE` / `DISABLED`)
- Doctrine repositories
- Structured service layer
- JWT-based authentication
- API-first architecture
- Twig-based frontend

Your task is to implement a **User Dashboard** that allows regular users to manage their profile, view personal data, and access application modules in a secure and responsive way.

---

# Constraints

- Reuse existing entities, services, and repositories
- Keep controllers thin and delegate logic to services
- Follow SOLID principles
- Only expose functionality appropriate for `ROLE_USER` (or equivalent)
- Reuse existing authentication and account status checks

---

# Objective

Implement a secure and responsive **User Dashboard** with:

- Personal profile management
- Access to application modules (consultations, exercises, forum, mood, sleep)
- User-specific settings
- Basic statistics or summary data

---

# 1. Authorization Enhancements

- Ensure that **only users with ACTIVE status** can access the dashboard.
- Block DISABLED users with a proper error response:

```json id="8fht3d"
{
    "error": "account_disabled",
    "message": "Your account is disabled."
}
```

- Protect routes under `/user/*` and `/api/user/*` with `ROLE_USER`:

```yaml id="djg4fp"
access_control:
    - { path: ^/user, roles: ROLE_USER }
    - { path: ^/api/user, roles: ROLE_USER }
```

---

# 2. User Profile Management

- Allow users to:
  - View and edit their profile (name, email, password, settings)
  - Update preferences (notifications, theme, etc.)
  - Change password securely using existing services

- Use **existing UserService or DTOs** where possible.

- Validate inputs using Symfony Validator.

---

# 3. User Dashboard Controllers

Create controllers in a **User namespace**:

```text
Controller/User/
 - DashboardController
 - ProfileController
 - SettingsController
```

Rules:

- Controllers remain thin
- Delegate business logic to services
- Return structured JSON for API endpoints

---

# 4. Dashboard UI (Twig)

Enhance the Twig frontend for users:

- **Layout:** Sidebar navigation, topbar, main content area

- **Sections:**
  - **Dashboard Overview:** Display personal stats, progress, or summary
  - **Profile:** Edit personal information
  - **Modules:** Navigation placeholders for:
    - Consultations
    - Exercises
    - Forum
    - Mood tracking
    - Sleep tracking

- **Mobile-Ready UI:** Hamburger menu for mobile view

- **Responsive design:** Cards, tables, or summary panels

- **UX Enhancements:**
  - Loading states
  - Inline editing for quick updates
  - Status badges if applicable

---

# 5. API Endpoints

Define API endpoints for user dashboard:

```text
GET    /api/user/me              # Get current user info
PUT    /api/user/me              # Update current user profile
GET    /api/user/dashboard       # Fetch summary / stats
PATCH  /api/user/settings        # Update user-specific settings
```

- Return structured JSON with success/error responses
- Enforce validation and account status checks

---

# 6. Future Modules Placeholders

- Include navigation links for all future features, without backend logic:
  - Consultations
  - Exercises
  - Forum
  - Mood
  - Sleep

- Structure backend to allow future module integration under `/api/user/{module}`

---

# 7. Account Status Enforcement

- Check account status in services or middleware
- Prevent disabled users from performing sensitive actions

---

# 8. UX Enhancements

- Hamburger menu for mobile view
- Responsive and accessible design
- Inline editable fields with validation
- Toast or flash messages for feedback
- Simple and clean layout for user focus

---

# 9. Future-Proof Structure

All user modules must follow a consistent structure:

```text
/user/{module}
/api/user/{module}
```

- Example: `/user/exercises` and `/api/user/exercises`

---

# Final Goal

Deliver a **clean, secure, and responsive User Dashboard** that:

- Provides personal profile management and settings
- Exposes modular placeholders for application features
- Enforces ACTIVE/DISABLED account status
- Reuses existing services, entities, and authentication
- Is fully mobile-ready and scalable for future modules
