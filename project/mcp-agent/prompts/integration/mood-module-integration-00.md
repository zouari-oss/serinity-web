# Mood Module Integration (Flexible & Architecture-Driven)

You are a senior Symfony 6/7 architect integrating a Mood Tracking module into an existing production-ready system that already includes:

- JWT authentication
- User entity with `account_status` (`ACTIVE` / `DISABLED`)
- Service layer and repositories
- Admin and User dashboards (placeholders already implemented)

---

# Mission

Integrate the Mood module from:

```bash
/home/zouari_omar/Downloads/Esprit-PIDEV-3A25-2526-Serinity-main
```

into the current application without breaking the existing architecture.

You are expected to analyze the external module and take the best technical decisions to:

- Adapt it to the current system
- Avoid duplication
- Ensure scalability
- Maintain clean architecture

---

# Key Instructions

- Do not blindly copy code
- Do not duplicate User or authentication logic
- Do not break existing services

Instead:

- Refactor, adapt, and reuse
- Keep controllers thin
- Centralize logic in services
- Follow SOLID principles

---

# Integration Guidelines

## 1. Entity Adaptation

- Reuse the existing `User` entity
- Merge or refactor the Mood entity to fit the current project
- Ensure proper Doctrine relations

You are free to:

- Rename fields
- Normalize structure
- Use existing traits in the project

---

## 2. Database

- Generate and adjust migration
- Add indexes if needed
- Ensure performance and consistency

---

## 3. Service Layer

- Reuse existing services when possible
- Create a `MoodService` only if necessary
- Avoid business logic duplication

You decide:

- Whether to split User/Admin logic
- Whether to introduce DTOs

---

## 4. Controllers

Integrate into:

```text
Controller/User/
Controller/Admin/
```

- Keep controllers minimal
- Delegate all logic to services

---

## 5. Dashboard Integration

### User Side

- Replace the Mood placeholder with real functionality
- Allow users to:
  - Add moods
  - View history
  - See basic statistics

### Admin Side

- Replace placeholder with:
  - Global mood monitoring
  - Filtering and moderation

---

## 6. Security and Account Status

- Enforce existing `ACTIVE / DISABLED` logic everywhere
- Prevent disabled users from interacting with the module

---

## 7. API Design

Follow existing conventions:

```text
/api/user/mood
/api/admin/mood
```

You decide:

- Exact endpoints
- Response structure (must stay consistent with project)

---

## 8. UI Integration (Twig)

- Extend existing layouts (do not redesign everything)
- Improve placeholders progressively
- Keep UI consistent with the current dashboard

---

## 9. Code Quality Expectations

- Clean, readable, maintainable code
- No duplication
- Reusable services
- Scalable structure for future modules

---

# Important

You are not just integrating a module.

You are adapting it into a mature system.

If something in the external module is poorly designed:

- Refactor it
- Replace it
- Or ignore it

Always prioritize:

- Existing architecture
- Maintainability
- Performance

---

# Final Goal

Deliver a fully integrated Mood module that:

- Fits naturally into both Admin and User dashboards
- Respects existing architecture
- Enforces account status
- Is clean, modular, and scalable
