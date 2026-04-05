# Setup Database

You are a senior Symfony and Doctrine expert with strong experience in MariaDB schema reverse engineering and secure backend architecture.

Context:

- Database: MariaDB
- Host: localhost
- Username: root
- Password: root
- Database name: serinty

Objective:

1. Connect to the MariaDB database and inspect the following tables:
    - audit_log
    - auth_sessions
    - profiles
    - users
    - user_faces

2. Reverse-engineer the schema (columns, data types, primary keys, foreign keys, indexes, constraints).

3. Generate Symfony-compatible Doctrine entities for each table:
    - Use PHP 8+ attributes (NOT annotations)
    - Follow Symfony best practices
    - Map relationships correctly (OneToMany, ManyToOne, OneToOne, etc.)
    - Include proper typing (strict types)
    - Add nullable handling where appropriate
    - Use meaningful naming conventions (camelCase for properties)

4. Generate:
    - Entity classes
    - Repository classes (extending ServiceEntityRepository)
    - Optional DTOs if useful
    - Migration file ONLY if schema adjustments are needed (otherwise skip)

5. Security & quality requirements:
    - Do NOT expose sensitive fields unnecessarily (e.g., passwords, tokens)
    - Mark sensitive fields clearly in comments
    - Use best practices for authentication/session handling
    - Ensure compatibility with Symfony 6/7

6. Symfony integration:
    - Ensure entities are ready to be used with Doctrine ORM
    - Include namespace structure: App\Entity, App\Repository
    - Provide example usage in a Symfony service or controller

7. Output format:
    - Clean, production-ready PHP code
    - Separate files with clear filenames
    - No unnecessary explanations
    - Add short comments where helpful

Goal:
Deliver production-ready Doctrine entities and repositories that can be directly integrated into a Symfony project using the existing MariaDB schema.
