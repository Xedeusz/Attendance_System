# Employee Biometric Attendance System

BranchSync is a responsive, browser-based employee attendance and management application planned for a company with approximately 27 branches. **Phase 2 provides the Laravel/authentication/UI foundation.** Employee CRUD, branch permissions, facial/PAD verification, GPS attendance, leave workflows and reporting are future phases; current module screens are clearly labeled placeholders.

## Installed stack

Laravel 13.42 · PHP 8.5 · Fortify 1.42 · Blade · Tailwind 4.3 · Alpine 3.17 · Vite 8.3 · MariaDB 10.11 (XAMPP). Full installed versions and decisions: [Phase 2 setup](docs/PHASE_2_SETUP.md).

## Run on this computer

In PowerShell:

```powershell
cd C:\xampp\htdocs\Attendance_System
.\scripts\dev.ps1 -Task provision
.\scripts\dev.ps1 -Task serve
```

Start MariaDB in XAMPP if needed. `provision` prompts for a private local login; no default password/account exists and no Admin role is assigned. Run it once for your chosen account. Open **http://127.0.0.1:8000/login**. If the application is already running on that port, use the existing server.

The helper uses the workspace-local Node tools and XAMPP PHP without changing global PATH. You can also run the PHP commands directly:

```powershell
& 'C:\xampp\php\php.exe' artisan app:provision-user
& 'C:\xampp\php\php.exe' artisan serve --host=127.0.0.1 --port=8000
```

Built assets are present locally. For live frontend development, open another terminal and run `.\scripts\dev.ps1 -Task assets`. Stop servers with Ctrl+C. Serve only `public/`; the old `http://localhost/Attendance_System/` root is deliberately denied by Apache configuration in this repository.

## Fresh checkout installation

Use compatible PHP, Composer 2, Node 26 LTS/npm, and a running supported MySQL/MariaDB server. `.tools/` is not committed; install tools separately or provide your own PATH.

```powershell
composer install
npm ci
Copy-Item .env.example .env
# Configure your private DB connection in .env before continuing.
php artisan key:generate
php artisan migrate
npm run build
php artisan app:provision-user
php artisan serve --host=127.0.0.1 --port=8000
```

Copy `.env` and generate a key only for a new environment. Do not overwrite existing `.env`, regenerate an established key, or reset a database to reinstall. If PHP ZIP/Intl are disabled but installed, command-local `-d extension=zip` / `-d extension=intl` flags can enable them; see setup notes. [Database creation and troubleshooting](docs/DATABASE_SETUP.md) explain dedicated credentials and Unicode configuration. No credentials belong in Git.

## Verification

```powershell
.\scripts\dev.ps1 -Task test
.\scripts\dev.ps1 -Task build
.\scripts\dev.ps1 -Task browser
.\scripts\dev.ps1 -Task format
```

Browser tests require this local server and installed Microsoft Edge. They create/remove only a temporary synthetic login. PHP feature tests use SQLite memory; migrations and actual browser authentication were separately checked on MariaDB. [Test results and manual device checklist](docs/PHASE_2_TEST_RESULTS.md).

## Security and current boundaries

- Real Fortify login/logout, framework hashing, CSRF/session protection, encrypted session storage and login throttling.
- No public self-registration. All workspace pages require login; all domain actions remain unavailable until backend authorization/business modules exist.
- Recovery views/actions are present and tested, but password-reset routes are disabled until private delivery is configured. Contact the administrator for local account help.
- Role previews contain no privileged data and do not grant roles. No camera, GPS, biometric template, attendance punch, export or leave approval is simulated.
- Local HTTP/debug setup is for development. Production deployment, HTTPS, MFA, full RBAC and privacy/operations acceptance remain pending.

## Documentation

- [System Blueprint](docs/SYSTEM_BLUEPRINT.md)
- [Requirements and company decisions](docs/REQUIREMENTS.md)
- [Technology decisions](docs/TECH_STACK.md)
- [Development progress](docs/DEVELOPMENT_ROADMAP.md)
- [Phase 2 setup and limitations](docs/PHASE_2_SETUP.md)
- [Database setup](docs/DATABASE_SETUP.md)
- [UI components](docs/UI_COMPONENTS.md)
- [Verification record](docs/PHASE_2_TEST_RESULTS.md)

Phase 2 implementation is complete subject to the documented manual/production gates. **No next phase starts without a new instruction.** No GitHub remote/publication was configured.
