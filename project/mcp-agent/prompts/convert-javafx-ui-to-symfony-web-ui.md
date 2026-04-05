# Convert JavaFX UI to Symfony Web UI

You are a senior UI/UX engineer and Symfony frontend expert with strong experience in transforming JavaFX desktop interfaces into modern, responsive web applications.

Context:

- Source UI (JavaFX FXML, styles, assets):
  <https://github.com/zouari-oss/serinity-desktop/tree/main/project/access-control/src/main/resources>
- Module: access-control
- Target: Symfony web application (Twig + optional Stimulus/UX + modern CSS framework)

Objective:
Recreate and enhance the JavaFX UI as a professional, modern web interface integrated into Symfony.

Tasks:

1. UI Analysis:
    - Analyze all FXML files, styles (CSS), and assets (icons, images)
    - Identify all screens/pages (login, dashboard, user management, sessions, etc.)
    - Understand layout structure, navigation flow, and component hierarchy

2. UI/UX Transformation:
    - Convert each JavaFX screen into a responsive web page
    - Improve UX while preserving the original design intent
    - Ensure consistency in spacing, typography, and layout
    - Apply modern UI/UX principles (accessibility, responsiveness, clarity)

3. Symfony Frontend Setup:
    - Use Twig as the templating engine
    - Organize templates under:
        - templates/base.html.twig
        - templates/access_control/...
    - Use Symfony UX (Stimulus) where interactivity is needed

4. Design System:
    - Create a reusable design system:
        - Color palette (based on desktop app, improved)
        - Typography scale
        - Buttons, inputs, cards, modals, tables
    - Use a modern CSS approach:
        - Tailwind CSS (preferred) OR Bootstrap 5 (if justified)
    - Ensure dark/light mode support if applicable

5. Components:
    - Build reusable Twig components:
        - Navbar / Sidebar
        - Forms (login, user creation, etc.)
        - Tables (users, audit logs, sessions)
        - Alerts / notifications
        - Modals and dialogs

6. Pages to Implement:
    - Login page
    - Dashboard
    - User management
    - Profile page
    - Sessions management
    - Audit logs
    - Any additional screens found in resources

7. Interactivity:
    - Use Stimulus controllers for:
        - Form validation feedback
        - Dynamic tables (sorting/filtering optional)
        - Modal handling
    - Keep JavaScript minimal and clean

8. Integration:
    - Connect UI with Symfony controllers/routes
    - Ensure forms are compatible with Symfony Form component
    - Prepare templates for real backend data (use placeholders if needed)

9. Accessibility & Responsiveness:
    - Mobile-first design
    - WCAG-friendly contrast and labels
    - Keyboard navigation support

10. Output Requirements:

- Provide:
  - Full Twig templates
  - Base layout
  - CSS (Tailwind config or custom styles)
  - Stimulus controllers (if used)
  - Asset structure
- Clean, production-ready code
- Clear file names and structure
- Minimal but meaningful comments

1. Enhancements:

- Improve the original JavaFX UI:
  - Better spacing and alignment
  - Modern card-based layouts
  - Cleaner navigation (sidebar or topbar)
- Suggest UX improvements where needed

Goal:
Deliver a polished, modern, and fully responsive Symfony UI that faithfully reflects the JavaFX design while significantly improving usability, scalability, and aesthetics.
