# Admin Dashboard System

You are a senior Symfony 6/7 architect working on an existing production-ready Symfony application.

## Context

The project already includes:

- User entity with roles and an **account status field** (`ACTIVE` or `DISABLED`)
- Doctrine repositories
- Structured service layer
- JWT-based authentication system
- API-first architecture
- Twig-based frontend

Your task is to extend and refine the system to implement a complete **Admin Dashboard** without rewriting existing logic.

---

# Constraints

- Reuse the existing account status field (`ACTIVE` / `DISABLED`)
- Block `DISABLED` users from logging in
- Do not duplicate business logic
- Keep controllers thin
- Follow SOLID principles
- Do not rewrite authentication
- Apply minimal, scalable changes

---

# Objective

Implement a secure Admin Dashboard with the following capabilities:

- User management
- Account status control (based on existing `ACTIVE` / `DISABLED` field)
- Admin profile editing
- Modular placeholders for future sections

---

# 1. Authorization Enhancements

## Status Integration

Use the existing `account_status` field to enforce:

- `ACTIVE` → full access
- `DISABLED` → login denied

## Authentication Flow Updates

- Block login if `account_status` = `DISABLED`
- Allow login if `account_status` = `ACTIVE`

## Security Integration

- Update or create a `UserChecker` to validate account status
- Hook into the existing authenticator without replacing it

## Access Control

Update `security.yaml`:

```yaml
access_control:
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/api/admin, roles: ROLE_ADMIN }
```

---

# 2. User Management (Extend Existing Services)

Do not create new business logic from scratch.

- Extend the existing `UserService` or create a thin `AdminUserService` that reuses existing methods

### Features

- **List Users**: pagination, filtering by email, role, and account status
- **User Actions**: edit, delete, toggle account status (ACTIVE/DISABLED)

### API Endpoints

```text
GET    /api/admin/users
PUT    /api/admin/users/{id}
DELETE /api/admin/users/{id}
PATCH  /api/admin/users/{id}/status
```

---

# 3. Admin Profile Editing

- Reuse the current profile update service
- Allow admin to edit their own profile and other users
- Allow changing roles and toggling account status (ACTIVE/DISABLED)

---

# 4. Admin Controllers

Create controllers in a dedicated namespace:

```text
Controller/Admin/
 - DashboardController
 - UserManagementController
 - ProfileController
```

Rules:

- Controllers must remain thin
- Delegate business logic to services
- Return structured JSON responses for API routes

---

# 5. Admin UI (Twig)

Enhance the existing UI without converting it to SPA.

## Layout

- Sidebar navigation
- Topbar/header
- Main content area

## Sections

- **Dashboard**: show stats using existing repository methods
- **User Management**: table showing email, roles, account status; actions: edit, delete, toggle status
- **Profile**: admin can edit their profile and other users
- **Placeholder Sections**: navigation links only (no backend logic yet)
  - Consultation Management
  - Exercises Management
  - Forum Management
  - Mood Management
  - Sleep Management

---

# 6. Account Status Enforcement

Ensure the existing `account_status` is enforced system-wide.

### API Error Example

```json
{
    "error": "account_disabled",
    "message": "Your account is disabled."
}
```

### Service Layer

- Centralize status checks before sensitive operations
- Avoid duplicating validation logic

---

# 7. UX Enhancements

- Status badges (Active / Disabled)
- Delete confirmation modal
- Inline status update (dropdown or toggle)
- Search input (debounced optional)
- Flash messages or toast notifications

---

# 8. Future-Proof Structure

All admin modules must follow a consistent pattern:

```text
/admin/{module}
/api/admin/{module}
```

Examples:

- /admin/consultations
- /api/admin/consultations

---

# Final Goal

Deliver a clean, minimal, and scalable extension of the existing system that:

- Fully integrates admin capabilities
- Enforces account status consistently (ACTIVE / DISABLED)
- Reuses current architecture and services
- Supports future modules without refactoring
