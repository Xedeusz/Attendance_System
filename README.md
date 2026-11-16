# LIMCOMA — Employee Attendance and Management System

Responsive Laravel web application for approximately 27 company branches. **Phases 1–4 are implemented:** HR/Employee accounts, employee/branch/department administration, activation, live camera photo attendance, reported GPS geofencing, server Time In/Out, private evidence, history, HR exception review, real dashboard statistics and audit logs.

Exactly **HR** and **Employee** roles exist; HR administers all branches. There is no public registration. Facial recognition, biometric enrollment, face matching and liveness are removed. Ordinary attendance uses a live browser camera UI with no gallery/file selection. Camera/GPS do not independently prove physical presence.

## Installed stack

Transient feedback uses [LIMCOMA-themed SweetAlert2](docs/SWEETALERT2_NOTIFICATIONS.md), including session flashes, sensitive-action confirmations and attendance feedback. Inline validation, result cards and persistent notifications remain available.

The shared interface uses the [LIMCOMA theme](docs/LIMCOMA_BRAND_THEME.md). HR Branch Management also supports [optional Photon location search](docs/BRANCH_LOCATION_SEARCH.md); Photon is enabled by default without an API key. Manual map selection works without an API key.

PHP 8.5.12 / Laravel 13.42.0 / Fortify 1.42.0 / MariaDB 10.11.16 / Blade / Tailwind 4.3.5 / Alpine 3.17.5 / Vite 8.3.8. Exact dependencies are locked; no new package was required for Phase 4. [Technology decisions](docs/TECH_STACK.md).

HR Branch Management includes a Leaflet 1.9.4/OpenStreetMap location picker, draggable office marker and live radius preview. Active branches require a complete geofence; saved values drive the existing server-side attendance checks. [Map configuration and testing](docs/BRANCH_MAP_GEOFENCING.md).

## Run locally

Start XAMPP's existing database service, then:

```powershell
cd C:\xampp\htdocs\Attendance_System
& 'C:\xampp\php\php.exe' artisan migrate --no-interaction
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task serve
```

Open **http://127.0.0.1:8000/login**. The Phase 4 migration is already applied locally; migrate applies only pending work. Do not use database fresh/reset/wipe to upgrade. The helper loads GD via project-only `scripts/php/attendance.ini`, necessary for attendance image validation. Stop the project server with Ctrl+C before restarting it with the helper. No global PHP configuration was modified.

If no HR exists in a fresh environment, run `php artisan app:bootstrap-hr` and privately enter a unique email/name/password. It refuses when any HR exists and does not replace existing users. HR creates subsequent accounts. Newly registered employees receive activation invitations and **choose their own private passwords**; there is no default employee password.

## First attendance workflow

1. Sign in as HR. Add official company branches and departments; configure each branch's approved coordinates/radius. No geofence values are invented.
2. Register an employee with an active primary branch assignment. In local development, inspect the invitation through **Settings → Protected local mail inbox**, confirm your password and open the complete activation invitation in a separate/private browser context.
3. Activate the employee account by choosing a password, then sign in as that employee.
4. Open **Time In / Time Out** or `/employee/attendance`. Select Time In, obtain location, press Start live camera, capture/retake, and submit. The result is displayed only after the backend saves it.
5. For Time Out, repeat with a new camera capture and location reading. The original open session is retained across midnight.
6. If location fails and policy permits, explicitly request HR review with an explanation and valid camera photo. It remains pending. Camera unavailable/missing Time In uses assistance, which creates no punch.
7. HR opens `/hr/attendance`, filters records, views protected photos/reported coordinates, and confirms a decision with remarks. Original time/location result remains unchanged. Employees see outcomes in history and `/notifications`.

The local encrypted mail preview is not external email delivery and is local-only, password protected and time limited. Production delivery/HTTPS/privacy review is required before real rollout. [Account workflow](docs/EMPLOYEE_ACCOUNT_WORKFLOW.md).

## Policy and maintenance

`config/attendance.php` / `.env.example` define development defaults: 180-second capture, 45-second location age, 50-metre accuracy limit, review requests enabled, 30-day normal evidence retention, 90-day pending hold. **HR/IT/DPO must confirm these policies before production.** Radius is configured per actual branch. No late/absent/payroll/schedule classifications are invented.

Use a monitored scheduler (`php artisan schedule:work` locally, `schedule:run` every minute in production). `php artisan attendance:prune-evidence` deletes expired private photos and exact location/explanation while preserving core timestamps/reviews. Optional `--orphans` scans unreferenced private files older than one hour. It deletes data: use only with the approved retention policy. Existing private-mail pruning remains scheduled.

Images are encrypted in `storage/app/private/attendance`, never served through a public storage link. Preserve `APP_KEY` securely with backups; do not regenerate it in an established environment. Only owner/HR policy-authorized endpoints return images. [Security/privacy and remaining production work](docs/ATTENDANCE_SECURITY.md).

## Verify

```powershell
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task test
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task browser
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task format
```

Browser tests need the local server and installed Edge; fixtures create/remove only uniquely marked synthetic data. Camera/location are browser mocks; requests, photos, reviews and database operations are real. PHP tests use SQLite memory. See [executed results and physical-device checklist](docs/PHASE_4_TEST_RESULTS.md).

For a real phone, plain HTTP to a PC LAN address is insufficient. Use trusted HTTPS and compatible web PHP/GD; exact XAMPP HTTPS/mobile instructions are in [CAMERA_IMPLEMENTATION.md](docs/CAMERA_IMPLEMENTATION.md). No physical Android/iPhone test is claimed.

## Fresh checkout

Use compatible PHP/Composer, Node/npm and MySQL/MariaDB; `.tools/` and `.env` are ignored. For a **new environment only**:

```powershell
composer install
npm ci
Copy-Item .env.example .env
# Privately configure dedicated database credentials and URL.
php artisan key:generate
php artisan migrate --no-interaction
npm run build
php artisan app:bootstrap-hr
# Enable GD in the actual PHP runtime, or use scripts/dev.ps1 -Task serve.
```

Never overwrite an existing `.env` or key during an upgrade. Serve only `public/`; the workspace root `.htaccess` intentionally denies direct Apache access. [Database setup](docs/DATABASE_SETUP.md), [Phase 2 setup](docs/PHASE_2_SETUP.md).

## Documentation and next work

- [Phase 4 report / exact file inventory](docs/PHASE_4_IMPLEMENTATION.md)
- [Attendance workflow](docs/ATTENDANCE_WORKFLOW.md), [camera](docs/CAMERA_IMPLEMENTATION.md), [GPS](docs/GPS_GEOFENCING.md)
- [Attendance schema](docs/ATTENDANCE_DATABASE.md), [master-data schema](docs/DATABASE_SCHEMA.md)
- [Security/privacy](docs/ATTENDANCE_SECURITY.md), [tests](docs/PHASE_4_TEST_RESULTS.md)
- [Blueprint](docs/SYSTEM_BLUEPRINT.md), [requirements](docs/REQUIREMENTS.md), [roadmap](docs/DEVELOPMENT_ROADMAP.md)
- [Phase 3 report](docs/PHASE_3_IMPLEMENTATION.md), [roles](docs/RBAC_PERMISSIONS.md)

Leave, formal corrections, scheduling/classification, exports and payroll are not implemented. Phase 5 is proposed in the roadmap; **stop after Phase 4 and await the next instruction**. No deployment, remote publication or optional integration was performed.
