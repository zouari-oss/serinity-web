# Symfony Web Version from JavaFX Access Control Module

You are a senior full-stack architect specialized in Symfony (6/7), Doctrine ORM, and enterprise application design. You also have strong experience in JavaFX and translating desktop architectures into modern web applications.

Context:

- Source project (JavaFX): <https://github.com/zouari-oss/serinity-desktop/tree/main/project/access-control/src/main/java/com/serinity/accesscontrol>
- Module: access-control
- Target: Symfony web application (backend + optional Twig or API-first)

Objective:
Analyze the JavaFX access-control module and transform it into a clean, scalable Symfony web architecture.

Tasks:

1. Codebase Analysis:
    - Inspect all directories (especially services, models, controllers, utils, etc.)
    - Understand business logic, authentication flows, session handling, and user management
    - Identify key components: services, entities/models, DTOs, and workflows

2. Architecture Mapping:
    - Translate JavaFX structure into Symfony architecture:
        - Java services → Symfony services (App\Service)
        - Java models → Doctrine entities (App\Entity)
        - Controllers → Symfony controllers (App\Controller)
        - Utility classes → Symfony services or helpers
    - Respect SOLID principles and clean architecture

3. Symfony Project Setup:
    - Generate a full Symfony project structure
    - Configure:
        - Doctrine ORM
        - Security (firewalls, providers, password hashing)
        - Environment configuration (.env)
    - Use PHP 8+ features and Symfony best practices

4. Authentication & Access Control:
    - Recreate the access-control logic from JavaFX:
        - Login system
        - Session handling
        - Role/permission system
    - Use Symfony Security component (custom authenticator if needed)
    - Ensure stateless or session-based auth depending on best fit

5. Services Translation:
    - Convert all Java service classes into Symfony services
    - Preserve business logic while adapting to PHP idioms
    - Use dependency injection properly
    - Split responsibilities when needed

6. API / Web Layer:
    - Expose functionality via:
        - REST API (preferred) OR
        - Symfony controllers with Twig
    - Follow REST best practices (status codes, validation, error handling)

7. Database Integration:
    - Use Doctrine entities based on existing schema (serinty database)
    - Map relationships correctly
    - Ensure compatibility with previously generated entities

8. Best Practices:
    - Use DTOs where appropriate
    - Add validation (Symfony Validator)
    - Handle exceptions properly
    - Keep controllers thin, services rich
    - Use interfaces where useful
    - Follow PSR standards

9. Output Requirements:
    - Provide:
        - Full directory structure
        - Symfony configuration files
        - Entities, services, controllers, repositories
        - Security configuration
        - Example API endpoints
    - Code must be production-ready
    - Use clear file separation with filenames
    - Add concise comments where necessary

10. Optional Enhancements:

- Suggest improvements over the JavaFX design if applicable
- Optimize for scalability and maintainability
- Highlight any architectural risks or refactoring opportunities

Goal:
Produce a complete, modern Symfony implementation of the access-control module, faithfully translating the JavaFX logic into a robust web architecture with best practices.
