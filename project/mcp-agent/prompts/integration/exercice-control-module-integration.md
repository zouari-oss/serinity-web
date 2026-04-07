# Exercice Control Module Integration (Architecture-Driven)

You are a senior Symfony 6/7 architect integrating **Exercice Control** into a production-ready system that already includes:

- JWT authentication
- User entity with `account_status` (`ACTIVE` / `DISABLED`)
- Service layer and repositories
- Admin and User dashboards (placeholders already implemented)

---

## 1. Analyze the Module

- Source path:

    ```
    /home/zouari_omar/Downloads/Esprit-PIDEV-3A25-2526-Serinity/exercice-control-web
    ```

- Identify:
  - Entities (`Exercice`, `Control`, relations)
  - Services / Managers
  - Controllers / Routes
  - Templates / Assets
  - Any dependencies (JS/CSS libraries, Symfony bundles)

> Avoid blindly copying controllers or services — reuse what fits your current architecture.

---

## 2. Entity Adaptation

- **Do not create a new User entity**; reuse the existing one.

- Refactor `Exercice` and `Control` entities to match current project conventions:
  - Proper Doctrine relations with `User`
  - Normalize fields (e.g., timestamps, statuses)
  - Use existing traits (`Timestampable`, `SoftDelete`, etc.) if available

- Decide whether to merge `Control` into `Exercice` or keep separate depending on domain logic.

---

## 3. Database

- Generate migrations after entity adaptation:

    ```bash
    php bin/console make:migration
    php bin/console doctrine:migrations:migrate
    ```

- Add indexes for frequent queries:
  - `user_id`
  - `status`
  - `date` fields if needed

- Ensure foreign key constraints are consistent.

---

## 4. Service Layer

- Create `ExerciceService` / `ControlService` **only if needed**.
- Delegate all business logic (validation, rules, calculations) to services.
- Avoid duplicating existing logic (e.g., authentication checks, user filtering).
- Consider DTOs for API responses if needed.

---

## 5. Controllers

- Split into **User** and **Admin** controllers:

    ```
    Controller/User/ExerciceController.php
    Controller/Admin/ExerciceController.php
    ```

- Keep controllers thin:
  - Validate requests
  - Call the service layer
  - Return responses (JSON or Twig render)

---

## 6. Dashboard Integration

### User Side

- Replace placeholder with:
  - List of assigned exercices / controls
  - Add / submit exercices
  - Track completion
  - Basic stats (completion %, scores)

### Admin Side

- Replace placeholder with:
  - Global exercice monitoring
  - Assign exercices to users
  - Filtering, moderation, statistics

---

## 7. Security and Account Status

- Enforce `ACTIVE / DISABLED`:
  - Disabled users cannot add, edit, or view exercices
  - Admin routes should verify user roles + status

- Leverage existing security layer:

    ```php
    $this->denyAccessUnlessGranted('ROLE_USER');
    if ($user->getAccountStatus() !== 'ACTIVE') {
        throw new AccessDeniedException('User is disabled');
    }
    ```

---

## 8. API Design

- Follow existing conventions:

    ```
    /api/user/exercice
    /api/admin/exercice
    ```

- Decide endpoints:
  - GET / list exercices
  - POST / submit exercice
  - PUT / update status
  - DELETE / optional removal

- Response structure consistent with other modules (JSON with `success`, `data`, `message`).

---

## 9. UI Integration (Twig)

- Extend existing layouts:

    ```twig
    {% extends 'base.html.twig' %}
    ```

- Replace placeholders in dashboards with real data.
- Keep UI consistent:
  - Use existing CSS and JS from dashboard theme
  - Integrate charts / tables as needed

---

## 10. Assets & JS/CSS

- Check if the module comes with its own assets:
  - Copy only what is necessary
  - Avoid duplicating libraries already loaded in your main dashboard

- Integrate via `AssetMapper` or import maps properly.

---

## 11. Code Quality & Architecture Principles

- Follow SOLID and DRY principles
- Keep controllers thin
- Centralize business logic in services
- Refactor poorly designed code from the original module
- Ensure scalability (new exercice types, rules, or reporting)

---

## 12. Final Steps

1. Adapt entities → run migrations
2. Implement services → integrate logic
3. Build User/Admin controllers → thin, secure, delegate logic
4. Update dashboards → replace placeholders
5. Test thoroughly → account status, permissions, API
6. Deploy → ensure assets compiled (`asset-map:compile`)

---

✅ After this, **Exercice Control** will fit naturally into both Admin and User dashboards, respect architecture, enforce account status, and be maintainable.
