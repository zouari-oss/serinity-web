# Sleep Control Module Integration (Strict Migration & Architecture-Driven)

You are a senior Symfony 6/7 architect integrating the **Sleep Control module** into a production-ready system that already includes:

- JWT authentication
- User entity with `account_status` (`ACTIVE` / `DISABLED`)
- Service layer and repositories
- Admin and User dashboards (placeholders already implemented)

---

# Core Requirement (MANDATORY)

You MUST **reuse and migrate the existing Sleep module implementation** from:

```
/home/zouari_omar/Downloads/Esprit-PIDEV-3A25-2526-Serinity
```

This includes:

- Entities
- Controllers
- Routes
- Workflow / business logic

You are NOT allowed to redesign the module from scratch.
You must **preserve the original workflow and route logic**, while adapting it to the current architecture.

---

# 1. Module Extraction (MANDATORY)

From the source project, you MUST extract:

- All Sleep-related entities (e.g. `Sleep`, `SleepSession`, etc.)
- Their associated controllers
- Route definitions
- Business logic flow (how data moves from request → controller → persistence)

You must:

- Identify all dependencies between components
- Keep the **functional behavior identical**

---

# 2. Entity Migration & Adaptation

- Import ALL Sleep-related entities into the current project
- **Do NOT recreate them manually if they already exist in the source module**

### Required adaptations

- Replace any User relation with the existing system `User` entity
- Fix namespaces to match the current project
- Normalize fields if needed (timestamps, naming conventions)
- Add traits if used in the current system (`Timestampable`, etc.)

✔ Goal:
Preserve structure + adapt to system consistency

---

# 3. Controller Migration (STRICT)

- Migrate the original Sleep controllers
- Preserve:
  - Methods
  - Logic flow
  - Route behavior

### BUT

- Refactor to:
  - Use the current project’s service layer
  - Keep controllers thin
  - Remove duplicated logic

- Place them into:

```
Controller/User/SleepController.php
Controller/Admin/SleepController.php
```

---

# 4. Route Preservation (CRITICAL)

You MUST preserve the **original route structure and workflow** from the source module.

Then adapt them to match system conventions:

```
/api/user/sleep
/api/admin/sleep
```

✔ Same logic
✔ Same flow
✔ Cleaned naming if necessary

---

# 5. Service Layer Refactor

- Extract business logic from migrated controllers
- Move it into a `SleepService`

You MUST:

- Keep original behavior intact
- Avoid duplicating logic already existing in the system
- Reuse repositories and shared services

---

# 6. Database Integration

- Generate migration AFTER entity adaptation:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

- Ensure:
  - Proper foreign keys with `User`
  - Indexes on:
    - `user_id`
    - `date`
    - relevant fields

---

# 7. Dashboard Integration

## User Dashboard

Replace placeholder with real module behavior:

- Add sleep session
- View history
- Follow same workflow as original module
- Display stats if present in source module

## Admin Dashboard

- Reuse original admin logic if available
- Add:
  - Monitoring
  - Filtering
  - Overview of sleep data

---

# 8. Security Integration

You MUST integrate existing security logic:

```php
$this->denyAccessUnlessGranted('ROLE_USER');

if ($user->getAccountStatus() !== 'ACTIVE') {
    throw new AccessDeniedException('User is disabled');
}
```

✔ Apply this to ALL Sleep endpoints

---

# 9. UI Integration (Twig)

- Reuse templates from source module if available
- Adapt them to:
  - Extend existing layout
  - Match current UI system

```twig
{% extends 'base.html.twig' %}
```

- Do NOT redesign UI — adapt it

---

# 10. Assets Handling

- Copy ONLY Sleep-related assets from source module

- Avoid duplicating libraries already present

- Integrate via:
  - AssetMapper
  - Existing frontend setup

---

# 11. Code Quality Rules

- No duplication
- No new logic unless necessary
- Preserve original workflow
- Refactor only for:
  - Integration
  - Maintainability
  - Consistency

---

# 12. Final Steps

1. Extract module (entities, controllers, routes)
2. Adapt entities → migrate database
3. Refactor controllers → services
4. Integrate routes (preserve flow)
5. Connect dashboards
6. Apply security rules
7. Integrate UI + assets
8. Test full workflow (must match original behavior)

---

# Final Goal

Deliver a **fully migrated Sleep Control module** that:

- Reuses original entities, controllers, and routes
- Preserves workflow and behavior from source project
- Integrates cleanly into the current architecture
- Enforces account status and security
- Remains scalable and maintainable
