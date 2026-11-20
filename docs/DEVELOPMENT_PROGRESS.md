# URIMS — Development Progress

**University Research & Innovation Management System**
Kampala International University in Tanzania (KIUT)

Last updated: 20 November 2026
Status: Modern Emerald + Corporate Minimalist UI/UX implemented across all modules.

---

## 1. Summary

The workspace started empty, so URIMS was implemented as a complete application on the approved stack:
HTML5, CSS3, vanilla JavaScript, pure PHP 8 and MySQL/MariaDB through PDO. No framework, build step,
Node.js, Composer or external UI library is used. Charts are rendered as themed HTML/CSS from PHP.

Implemented:

- Fixed left sidebar with grouped, permission-aware navigation and a collapsible desktop state.
- Sticky top bar with breadcrumbs, page title, notifications, theme toggle, profile dropdown and sign-out.
- Light and dark themes driven by CSS variables and `data-theme`, persisted in `localStorage`,
  applied before first paint to avoid a flash of the wrong theme.
- Emerald design tokens, corporate-minimal components and a responsive shell (drawer navigation on tablets/phones).
- Live dashboard with role-scoped statistics, five charts and activity panels backed by MySQL.
- 25 data modules with search, filters, sorting, pagination, detail views, create/edit forms,
  validation, status badges, authorised row actions and delete confirmation modals.
- Reports & Analytics and Financial Reports with CSV export and print layouts.
- Notification inbox with read/unread handling, audit trail, system settings and profile/password management.
- CSRF protection, prepared statements, password hashing, login throttling, secure remember-me tokens.

---

## 2. Project structure

```
config/
  config.php              Constants, base URL, session, bootstrap
  database.php            PDO factory, query helpers, env/local overrides
  permissions.php         Role → module and write/delete matrix
  resources.php           Metadata registry for every module
database/
  schema.sql              Full schema (25 tables, foreign keys, indexes)
  install.php             Recreates schema and seeds demonstration data
includes/
  init.php                Page bootstrap + friendly setup screen if DB is missing
  auth.php                Login, logout, remember-me, guards, row scope
  functions.php           Escaping, URLs, CSRF, flash, formatting, pagination, audit
  icons.php               Inline SVG icon set
  header.php              Document head, top bar, theme bootstrap, toasts
  sidebar.php             Permission filtered navigation
  footer.php              Footer, confirm dialog, scripts
  charts.php              Bars, horizontal bars, donut charts + data tables
  resource_engine.php     Shared list/form/detail/delete controller
assets/
  css/themes.css          Design tokens (light + dark)
  css/style.css           Reset, shell, sidebar, top bar, branding, login
  css/components.css      Buttons, cards, tables, forms, badges, modals, charts…
  css/responsive.css      Desktop → tablet → mobile adaptations
  js/theme.js             Theme toggle and persistence
  js/sidebar.js           Collapse, drawer, active item
  js/app.js               Dropdowns, toasts, modals, validation, totals
modules/                  One thin wrapper per module (sets $RESOURCE)
uploads/                  Document uploads (script execution blocked)
dashboard.php  reports.php  financial_reports.php  notifications.php
settings.php   profile.php  login.php  logout.php  index.php
```

Adding a module means adding one entry to `config/resources.php` and a five-line wrapper in `modules/`.

---

## 3. Design system

### Tokens
`assets/css/themes.css` defines the approved palette: primary `#047857`, hover `#065F46`,
primary-light `#D1FAE5`, primary-soft `#ECFDF5`, accent `#10B981`, light background `#F8FAFC`,
surfaces and borders, plus the emerald sidebar tokens. Dark theme overrides the same names with
navy surfaces (`#0F172A`, `#1E293B`, `#334155`) and the emerald highlight palette.

Additional tokens cover badge tones, soft alert surfaces, six chart colours, radii, shadows,
layout widths (`260px` sidebar, `76px` collapsed, `72px` top bar) and transitions.

### Themes
- `data-theme="light"` is the default; `data-theme="dark"` is applied by `theme.js`.
- Choice is stored in `localStorage` under `urims-theme`; an inline head script restores it before paint.
- Toggle lives in the top bar and on the login page, with a dynamic `aria-label` and icon swap.
- Tables, forms, selects, dropdowns, modals, charts and the sidebar all consume the same variables.
- `prefers-reduced-motion` disables non-essential animation.

### Layout
Fixed sidebar (independent scrolling, brand, grouped navigation, user block), sticky top bar
(breadcrumbs, title, bell, theme, avatar menu), flexible content area with 24–32px padding,
optional footer. Desktop collapse state is stored in `localStorage` (`urims-sidebar`).
Below 1024px the sidebar becomes an off-canvas drawer with backdrop, Escape handling and focus return.

### Components
Buttons (primary/secondary/ghost/danger, sizes, loading), cards, statistic cards, toolbar with search
and filters, tables with sorting and stacked mobile presentation, badges with text + dot, forms with
labels, required markers, help text and inline errors, selects, textareas, password reveal,
modals/confirm dialog, dropdowns, tabs, alerts, toasts, pagination, progress bars, avatars,
breadcrumbs, empty states, loading spinner and charts with accessible data tables.

Typography uses Inter with `system-ui` fallback. Icons are inline SVG with `aria-hidden` unless labelled.

---

## 4. Navigation and permissions

Sidebar groups: Overview, University Management, Academic Research, Institutional Research,
Innovation, Finance, System. Items are filtered by `can_access()`; unauthorised URLs return HTTP 403.

| Role | Modules | Write access | Data scope |
|------|---------|--------------|------------|
| System Administrator | All | All | All |
| Research Officer | All except users, settings, audit logs | All research, innovation, finance, university data | All |
| Faculty Dean | University reference data, academic research, institutional research, innovation, finance, reports | Proposals, evaluations, publications, lecturer research, teams, funding, budgets | All |
| Head of Department | Same read set as dean (no financial reports) | Proposals, evaluations, milestones, documents, publications, lecturer research, teams | All |
| Research Supervisor | Dashboard, students, projects, proposals, milestones, documents, evaluations, publications, reports | Projects, proposals, milestones, documents, evaluations | Own supervised projects |
| Student Researcher | Dashboard, projects, proposals, milestones, documents, notifications | Proposals, documents | Own student projects |
| Finance Officer | Dashboard, funding, budgets, expenditure, financial reports, reports | Funding, budgets, expenditure | All |
| Innovation Judge | Dashboard, innovation projects, competitions, evaluation results | Evaluation results | All |

Navigation was verified to hide unauthorised links (for example the student sidebar contains no
`modules/users.php` link) and to return 403 for direct access.

---

## 5. Modules

| Module | Features |
|--------|----------|
| Dashboard | Greeting/role/date, shortcuts, 7 live statistic cards, research activity, status donut, department bars, innovation pipeline, funding summary, recent submissions, pending approvals, deadlines, supervisor feedback, notifications |
| Users | Accounts, roles, faculty/department assignment, status, passwords, last login |
| Faculties / Departments / Programmes | Reference data with counts, dean/HOD, level and duration |
| Academic Years / Semesters | Calendar with current-year/semester flags |
| Students | Registry linked to user accounts and programmes |
| Research Projects | Student, supervisor, department, year, status, progress, budget, dates |
| Proposals | Submission, review status, decisions, comments, budget |
| Supervisors | Read/update supervisor profiles with active project counts |
| Milestones | Due dates, completion dates, overdue signalling |
| Documents | Versioned project documents with file upload and download |
| Evaluations | Criteria, methodology, presentation scores with auto total |
| Lecturer Research / Research Teams / Publications | Staff research portfolio and outputs |
| Innovation Projects / Teams | Incubation stages and team composition |
| Competitions / Judges / Evaluation Results | Competition setup, judging panels, scored rankings |
| Funding Applications | Requests, sources, decisions, remarks |
| Budgets / Expenditure | Allocations, utilisation, spending records |
| Notifications | Inbox, unread filter, mark read/unread, mark all read, delete |
| Reports & Analytics | Faculty/department/status/year analytics, publications, supervision load, funding, CSV export, print |
| Financial Reports | Allocated/spent/pending/remaining, utilisation by category and project, funding by source |
| Audit Logs | Read-only activity trail (user, action, module, IP, time) |
| Settings | Institution details, contact addresses, fiscal year, page size; runtime diagnostics |
| Profile | Personal details and password change; remember tokens revoked on password change |

---

## 6. Database

25 tables in `urims`: faculties, departments, programmes, academic_years, semesters, users,
remember_tokens, students, research_projects, proposals, milestones, documents, evaluations,
lecturer_research, research_teams, publications, innovation_projects, innovation_teams, competitions,
judges, evaluation_results, funding_applications, budgets, expenditure, notifications, audit_logs, settings.

Foreign keys, indexes on status/department/role/created_at, and `ON DELETE` rules prevent orphans
while protecting historical financial data. Seeded demonstration data includes 5 faculties,
10 departments, 12 programmes, 22 users, 15 students, 34 research projects, 26 proposals,
64 milestones, 24 documents, 18 evaluations, 20 publications, 10 innovations, 4 competitions,
18 funding applications and 26 budget lines with expenditure.

---

## 7. Security

- PDO prepared statements throughout; no string interpolation of user input.
- Passwords hashed with `password_hash` (bcrypt default).
- CSRF token on every mutating form, verified server-side.
- Session cookie: HttpOnly, SameSite=Lax, secure under HTTPS; session ID regenerated on login.
- Remember-me uses selector + hashed validator, 30-day expiry, revocable on sign-out and password change.
- Five failed logins trigger a five-minute throttle per session.
- Output escaped with `e()`; role checks on every module (`require_permission`) plus write/delete checks.
- Uploads restricted by extension and size, stored with randomised names, script execution denied.
- Audit trail written for login/logout and every create, update and delete.

---

## 8. Demonstration accounts

| Role | Email | Password |
|------|-------|----------|
| Administrator | admin@kiut.ac.tz | Admin@123 |
| Research Officer | research@kiut.ac.tz | Research@123 |
| Dean | dean@kiut.ac.tz | Dean@123 |
| Head of Department | hod@kiut.ac.tz | Hod@123 |
| Supervisor | salma.juma@kiut.ac.tz | Super@123 |
| Student | student@kiut.ac.tz | Student@123 |
| Finance Officer | finance@kiut.ac.tz | Finance@123 |
| Judge | judge@kiut.ac.tz | Judge@123 |

Usernames (`amina`, `salma`, `aisha`, …) are also accepted by the sign-in form.

---

## 9. Running locally

XAMPP on Windows:

1. Start **Apache** and **MySQL** from the XAMPP control panel.
2. The project lives at `C:\xampp\htdocs\project-mgnt-sys`; open `http://localhost/project-mgnt-sys/`.
3. If the schema is missing, run `php database/install.php` from the project folder
   (or visit `database/install.php?confirm=install`). The installer is non-destructive by default:
   it creates only missing tables and seeds demo data only when the `users` table is empty.
   Use `php database/install.php --fresh` for a deliberate local rebuild that drops and recreates tables,
   `--no-seed` for schema only, and `--check` to verify the connection without changing anything.
4. Database credentials default to `127.0.0.1`, user `root`, empty password, database `urims`;
   override in `config/database.local.php` or with the environment variables `DB_HOST`, `DB_PORT`,
   `DB_NAME`, `DB_USER`, `DB_PASS` (documented in `.env.example`).

### Deploying on Railway

- Add a MySQL service and set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` on the
  application service to the MySQL service values (`MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`,
  `MYSQLUSER`, `MYSQLPASSWORD`). The MySQL plugin names are also read automatically when the
  `DB_*` names are absent, and `DATABASE_URL` / `MYSQL_URL` are parsed as a fallback.
- `railway.json` starts the app with `bash start.sh`, which runs the idempotent installer and then
  serves the project with PHP's built-in server on `$PORT`. Serving from that entrypoint matters:
  PHP-FPM hosts commonly use `clear_env = yes`, which strips injected variables from the worker
  environment and makes `getenv('DB_HOST')` return `false` even though the variable exists.
- Connection settings are resolved from `$_ENV`, `$_SERVER`, `getenv()` and an optional `.env`
  file, so any of those transports works.
- `start.sh` never aborts the container on a database error; the setup screen then reports the exact
  PDO error, the host/port/database/user it used, the credential source, and which variables were
  detected. Set `URIMS_SKIP_DB_BOOTSTRAP=1` to skip the automatic install step.

Without Apache: `php -S 127.0.0.1:8090 -t .` from the project root.

---

## 10. Verification log

Performed on 20 November 2026 against PHP 8.4.27 and MySQL/MariaDB on this machine.

**Static**
- `php -l` on every PHP file: no syntax errors (one fatal in `profile.php` found and fixed).

**HTTP smoke tests (authenticated as administrator)**
- `/login.php` 200, sign-in 302 → `/dashboard.php`.
- Dashboard, users, research projects (list + create form), proposals, budgets, audit logs,
  supervisors, documents, evaluation results, students, publications, reports, financial reports,
  notifications, settings, profile: all 200 with no PHP warnings or errors.
- All 25 modules: list and detail (`?action=view&id=1`) returned 200 without errors.
- Filters, sorting and search combinations returned 200 without errors
  (`f_role`, `f_status`, `f_fiscal_year`, `sort`/`dir`, `q`, report year filter).
- Create → edit → delete round trip on a temporary faculty: POST 302 each time,
  database reflected the new, renamed and deleted record.
- Delete confirmation modal opens with focus on Cancel, locks background scroll and closes on cancel.

**Authentication and authorisation**
- Wrong password returns the inline error; five failures throttle the session.
- Remember-me: cookie issued, database token row created; a request with only the remember cookie
  (session cookie removed) returned the dashboard 200.
- Logout 302 → `login.php?signed_out=1`; dashboard afterwards 302 → login.
- Student: dashboard/projects/proposals 200; users, funding, reports 403; sidebar has no users link;
  project list showed 3 of 3 records matching the database count for that student.
- Supervisor: 6 of 6 supervised projects; users/settings 403.
- Finance: budgets/funding/financial reports 200; research projects/settings 403.
- Judge: evaluation results/competitions 200; research projects 403.

**Interface**
- Dashboard as administrator: 30 navigation items across 7 groups, 7 statistic cards, 5 charts,
  real aggregates (34 projects, 15 active, 7 completed, 4 pending approvals, 11 overdue,
  8 innovation projects, TZS 164.5M allocations).
- Light theme: background `#F8FAFC`, emerald sidebar `#064E3B`.
- Dark theme: background `#0F172A`, cards `#1E293B`, light text, readable chart labels/badges;
  `localStorage` persisted `dark` and survived reload with correct computed colours.
- Mobile viewport 390×844: sidebar off-canvas (-260px), menu button visible,
  tables stacked with `data-label` captions, single-column statistics, no horizontal overflow;
  opening the drawer sets `sidebar-open`, backdrop, `aria-expanded="true"` and moves focus into navigation.
- Desktop 1440×900: 260px fixed sidebar, 72px top bar, no horizontal overflow.

**Limitation:** the automation browser tab reported `visibilityState: hidden`, so CSS transitions
(mobile drawer slide, toast fade) did not advance during inspection; layout was verified with the
transition applied. Animation timing was therefore not visually observed, and no screenshots were taken.
No automated test suite exists (none was requested); verification used PHP lint, HTTP requests and
browser-side layout inspection.

---

## 11. Known limitations and follow-ups

- Password reset is intentionally not self-service; the login page directs users to the research office.
  A secure token-based reset needs an email service, which is not configured.
- Notifications are generated per user record; there is no real-time push or email delivery.
- Document search covers metadata only, not file contents.
- Reports export CSV (opens in Excel) rather than native XLSX/PDF; print styling is available.
- The front-end font is loaded from Google Fonts with a system fallback; offline use falls back cleanly.
- No paginated API or mobile app; this is a server-rendered PHP application by design.
