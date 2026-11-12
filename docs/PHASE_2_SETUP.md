# Phase 2 — Installed Foundation

Historical Phase 2 record. Phase 3 now provides exactly HR and Employee, real master-data administration, activation and encrypted local mail. The old direct-user provisioning command is disabled; use [current Phase 3 setup](PHASE_3_IMPLEMENTATION.md) and app:bootstrap-hr.

Date: 2026-11-12. Workspace: `C:\xampp\htdocs\Attendance_System`.

## Implementation and architecture choice

Laravel was initialized at the existing repository root, preserving all Phase 1 documents. The suggested `employee-biometric-attendance` name is used as a logical project identifier; no redundant nested app directory or workspace rename was introduced. The original login prototype was moved unchanged to `docs/reference/login-prototype.html`.

The application uses plain Blade, Tailwind and Alpine with **Laravel Fortify** providing maintained authentication routes, controllers, password checking, session preparation, logout and the optional recovery broker. This follows the Phase 1 architecture without adding Livewire/Flux solely for placeholder screens. Public registration is not enabled. Dashboard role labels are explicitly design previews, not assigned roles or authorization.

Reviewed [Laravel installation documentation](https://laravel.com/docs), [agent setup guidance](https://laravel.com/for/agents), [Fortify](https://laravel.com/docs/13.x/fortify), and [starter kits](https://laravel.com/docs/13.x/starter-kits). The site's global installer/Cloud defaults were adapted to the user's local XAMPP/Blade requirements. No Cloud CLI, remote hosting or GitHub publication was performed.

## Versions actually installed

| Tool | Version |
| --- | --- |
| PHP CLI / local development web server | 8.5.12, `C:\xampp\php\php.exe` |
| Laravel framework / skeleton | 13.42.0 / 13.11.0 |
| Fortify | 1.42.0 |
| MariaDB | 10.11.16 |
| Composer | 2.11.2, workspace-local `.tools/composer.phar` |
| Node.js / npm | 26.13.1 / 11.20.0, workspace-local distribution |
| Tailwind / Vite / Laravel Vite plugin | 4.3.5 / 8.3.8 / 3.2.1 |
| Alpine / Alpine focus plugin | 3.17.5 / 3.17.5 |
| PHPUnit / Pint | 12.5.43 / 1.32.3 |
| Laravel Boost | 2.10.4, development dependency required by scaffold `AGENTS.md` |
| Playwright / Prettier | 1.65.0 / 3.10.0 |
| Git | 2.56.1.windows.1 |

Composer and Node/npm were missing from PATH; portable copies were downloaded into ignored `.tools/`, verifying Composer installer SHA-384 and Node archive SHA-256 against official checksum files. Existing PHP and system PATH were not replaced. ZIP was disabled but its DLL was installed; setup invoked PHP with `-d extension=zip` for Composer downloads without changing global `php.ini`. The standalone Laravel installer is not required by the Composer `create-project` method and was not installed globally.

Required PHP extensions were found: PDO, pdo_mysql, OpenSSL, mbstring, fileinfo, XML/DOM, ctype, tokenizer, curl, filter, hash and session. Apache itself was not started/configured; the verified web runtime is PHP's Laravel development server. Verify Apache's PHP separately if using it later.

## Initialization procedure used

1. Inspected existing files and read the four Phase 1 planning documents. No pre-existing project-specific `AGENTS.md` or `PROJECT_MASTER_PROMPT.md` existed before scaffolding.
2. Downloaded the Laravel 13 skeleton into ignored `.scaffold/` with `--no-install --no-scripts`; merged only non-colliding entries into the root. Preserved the custom ignore list and original prototype.
3. Installed dependencies with Composer and Fortify, then the development-only Boost requirement from the scaffold's agent instructions. Installed Boost guidelines/skills locally; no MCP service was exposed.
4. Installed frontend dependencies with npm and lockfile, including Alpine focus, Playwright for requested browser checks, and Prettier for shared assets.
5. Configured ignored `.env`, generated an application key, created the dedicated MariaDB connection, and ran only framework migrations.
6. Implemented views/components, protected placeholders, auth configuration, private-file serving restrictions, headers, tests and startup helpers.

Representative commands (portable tools for this workspace):

```powershell
& 'C:\xampp\php\php.exe' -d extension=zip .tools\composer.phar create-project laravel/laravel .scaffold '^13.0' --no-install --no-scripts --no-interaction
& 'C:\xampp\php\php.exe' -d extension=zip .tools\composer.phar require laravel/fortify --no-interaction
& 'C:\xampp\php\php.exe' -d extension=zip .tools\composer.phar require laravel/boost --dev --no-interaction
& 'C:\xampp\php\php.exe' artisan boost:install --guidelines --no-interaction
& 'C:\xampp\php\php.exe' artisan boost:install --skills --no-interaction
& 'C:\xampp\php\php.exe' artisan key:generate
& 'C:\xampp\php\php.exe' artisan migrate --no-interaction
```

These record completed setup, not commands to rerun against this installed workspace. Never regenerate an established application key or reinitialize the scaffold to start the app.

## Run the installed application

Start MariaDB through XAMPP if it is stopped. From the project directory:

```powershell
.\scripts\dev.ps1 -Task provision
.\scripts\dev.ps1 -Task serve
```

`provision` prompts privately for a name/email/password and confirmation. It creates a **local development login**, not an Admin role. Passwords need 12+ characters with mixed case, a number and symbol. There are no committed/default administrator credentials. Role grants, employee profiles, activation and deactivation are Phase 3+ work.

Open **http://127.0.0.1:8000/login**. Built assets already exist locally, so a Vite server is not required to view the interface. If port 8000 is already serving this app, use that server instead of starting a duplicate.

For live asset changes, use a second terminal:

```powershell
.\scripts\dev.ps1 -Task assets
```

For checks:

```powershell
.\scripts\dev.ps1 -Task build
.\scripts\dev.ps1 -Task test
.\scripts\dev.ps1 -Task browser
.\scripts\dev.ps1 -Task format
```

The helper changes PATH only for its current process. If script execution is restricted, review the script and invoke PowerShell with a process-only policy, or use the explicit binaries:

```powershell
& 'C:\xampp\php\php.exe' artisan app:provision-user
& 'C:\xampp\php\php.exe' artisan serve --host=127.0.0.1 --port=8000
```

Stop development servers with Ctrl+C. `APP_URL` is `http://localhost:8000`; `127.0.0.1` is the recommended verification URL. Stay on one hostname per login session to avoid cookie confusion.

## Configuration and security boundaries

- Database/session/cache/jobs use MariaDB; session payloads are encrypted. Cookie is HttpOnly, SameSite=Lax, two-hour session lifetime. Remember-me uses a seven-day duration. HTTP local cookies are not marked Secure; production forces Secure and needs HTTPS.
- Debugging is permitted only when `APP_ENV=local` and `APP_DEBUG=true`; production does not display debug pages through app config.
- The repository root `.htaccess` denies direct Apache access. Serve only `public/` using the supplied Artisan command or a deliberately configured VirtualHost. Do not use `http://localhost/Attendance_System/` as the Laravel entry point.
- Private local disk URL serving is disabled. The app has no upload routes. Root config/docs/tools are not the development web root.
- Phase 2 sends no camera/location requests; Permissions-Policy explicitly disables them pending the relevant phases.
- Login throttling: five attempts/minute per normalized email/IP, plus 30/minute per IP. Recovery/confirmation requests have an IP limit.
- Recovery implementation is present and tested with fake notifications, but disabled locally because private SMTP/delivery is not configured. Do not enable recovery with `MAIL_MAILER=log` for real users: reset URLs are sensitive.
- To enable recovery later: configure and test private mail delivery, set `AUTH_PASSWORD_RESET_ENABLED=true`, clear config/restart, verify the end-to-end link and token behavior. Generic link-request responses avoid explicit email existence disclosure; timing and broader account-recovery risks still need production review.
- Boost's browser log collector is disabled. No persistent provider credentials, face references, GPS tracking, RBAC or domain operations are implemented.

## Known limitations and remaining gates

Not production-ready. Complete role/branch authorization, employee lifecycle, MFA, approved recovery delivery, audit/privacy lifecycle, HTTPS deployment and operational controls in their requested phases. All current authenticated users can view the safe placeholder screens; these contain no privileged domain data/actions. Physical Android/iPhone/Safari/Firefox, real employee devices, screen readers, and production/Apache deployment still need manual testing. The local sandbox command helper became unavailable after tool setup; verification continued using explicitly approved elevated commands without changing global system configuration.

See [database setup](DATABASE_SETUP.md), [UI components](UI_COMPONENTS.md), and [test results](PHASE_2_TEST_RESULTS.md). Stop after Phase 2; do not automatically start RBAC or employee CRUD.
