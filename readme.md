# KSIJ Connect

Hackathon build for the KSIJ Jamaat community platform.

## First-Time Setup

1. Pull the repo and work from your own branch.
2. Put the project folder inside XAMPP: `C:\xampp\htdocs\ksij-connect`.
3. Create a local database named `ksij_platform`.
4. Run the full SQL script from `PROJECT_SCHEMA.md`, section 2.
5. Create your own local `config.php`. Do not commit real passwords or API keys.
6. Start Apache and MySQL in XAMPP.
7. Open the app through `http://localhost/ksij-connect/`.

## Important Docs

- `PRD.md` - product requirements and role rules.
- `PROJECT_SCHEMA.md` - database schema, seed data, and test credentials.
- `TEAM_TASK_BREAKDOWN.md` - who owns which build area.
- `SAMIR.md`, `HASAN.md`, `QAMAR.md`, `ASAD.md` - individual task lists.
- `TEAM_WORKFLOW.md` - branch, commit, and handoff rules.

## Team Ownership

- Samir: frontend/user-side flows, notifications, final staff chatbot integration.
- Hasan: admin panel and admin AI context.
- Qamar: volunteer/CC member login, dashboard, assignment flow, staff context.
- Asad: continuous testing, GitHub repo upkeep, demo/PPT support.

## Login Rules

- Member login is public.
- Volunteer/CC member login is public through `team_login.php`.
- Admin login uses `staff_login.php` directly and must not be linked publicly.

## Test Credentials

See `PROJECT_SCHEMA.md`, section 3. All dummy staff passwords are `test123`.

## Before Coding

Everyone should confirm the database imports successfully and that the seeded test accounts work. After that, build only inside your assigned area and ask Asad to test each completed piece immediately.
