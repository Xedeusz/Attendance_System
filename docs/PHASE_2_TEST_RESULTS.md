# Phase 2 — Verification Record

Date: 2026-11-12. Windows, PHP 8.5.12, Laravel 13.42.0, MariaDB 10.11.16, Node 26.13.1. Only synthetic accounts were used; no employee biometric/location data was collected.

## Executed checks

| Check | Command | Result |
| --- | --- | --- |
| Application boot/key | `php artisan key:generate`; `php artisan about` | Key configured in ignored `.env`; framework boots |
| MariaDB connection | `php -d extension=intl artisan db:show --counts` | Connection/database/framework tables verified; Intl flag needed by diagnostic formatting |
| Migrations | `php artisan migrate --no-interaction`; `php artisan migrate:status` | Three standard framework migrations applied, batch 1; no domain migrations |
| Frontend assets | `npm run build` | CSS/JS and manifest emitted successfully |
| PHP regression suite | `php vendor/bin/phpunit --testdox --colors=never` | 24 tests, 121 assertions passed |
| Real browser checks | `npm run test:browser` | 2 headless Microsoft Edge tests passed; last responsive run completed in 36.1 seconds |
| PHP style | `php vendor/bin/pint --dirty --format agent` | Applied to new/changed PHP files |
| CSS/JS style | `npm run format`; `npm run format:check` | Prettier formatting applied/checked |
| Routing | `php artisan route:list` | Auth routes present, workspace requires login, registration absent |
| Secret/runtime exclusions | `git check-ignore .env .tools/composer.phar vendor/autoload.php node_modules/alpinejs/package.json` | All ignored |
| Install-time dependency audits | Composer/npm installation output | No known vulnerability advisories reported; not a security certification |

Run commands from `C:\xampp\htdocs\Attendance_System`. If `php` is not on PATH, use `C:\xampp\php\php.exe`; the [setup helper](../scripts/dev.ps1) also selects portable Node. See [setup instructions](PHASE_2_SETUP.md).

## Coverage and database isolation

`tests/Feature/AuthenticationFoundationTest.php` covers login UI, GET/POST registration rejection, disabled unconfigured recovery, guest denial for nine workspace paths, wrong credentials, case-normalized successful login, session rotation, logout/token rotation, throttling, safe placeholder rendering/no POST business actions, dashboard preview allowlist, password hashing/no seeded default account, security headers, local-only provisioning and output escaping.

`tests/Feature/PasswordRecoveryTest.php` enables recovery only inside isolated tests, registers Fortify routes and refreshes their name lookups. It checks framework notifications using a fake, generic known/unknown-email responses, password change, single-use tokens and expiry. This does not prove SMTP delivery. Real local recovery remains disabled.

PHP feature tests use SQLite memory; actual migration/connection checks and Edge login use MariaDB. No SQLite result is presented as a MySQL concurrency test.

`tests/Browser/foundation.spec.js` uses the installed Edge headlessly through Playwright. The temporary synthetic login has a random password held in process memory and is removed with its sessions after the suite. It verifies actual MariaDB-backed sign-in, invalid login, password visibility, missing-CSRF rejection, cookie flags, protected pages, disabled Time In/Out, confirmation/logout and no JavaScript page errors.

Login/dashboard are checked at **320, 360, 375, 390, 430 and 768px**, plus a **1440px** desktop flow. Each module/profile/settings/employee preview is checked for page overflow at the mobile/tablet widths. Drawer open/Escape close is checked at each smaller viewport. Tables may scroll inside their labeled regions; the whole page does not overflow.

Screenshots are local ignored artifacts under `test-results/screenshots/`: `login-desktop.png`, `dashboard-desktop.png`, and `employee-mobile.png`.

## Defects encountered and fixes

1. PowerShell returned checksum text as bytes: saved/read the checksum file, then verified before installer execution.
2. Composer extraction lacked enabled ZIP: used existing DLL with command-local PHP option, preserving global PHP configuration.
3. Vite temporarily contained two default exports: removed the unused scaffold export/font integration; build passes.
4. Initial page tests lacked the asset manifest: built assets before rerunning real view-rendering tests.
5. Recovery tests initially inherited disabled routes: explicitly enabled test-only feature/routes and refreshed URL lookups.
6. Mobile table minimum width expanded a grid: zero minimum widths on tracks/stack children keep scrolling inside the table container.
7. Drawer test queried a trigger made inert by the correct focus trap: included hidden/inert elements for the expanded-attribute assertion, then checked Escape restoration.
8. `db:show` connected but its number formatting needed optional Intl: reran with `-d extension=intl`; the app's normal authentication pages do not require that diagnostic flag.

## Manual checks still required

No physical Android/iPhone/tablet, Safari/Firefox, screen-reader, or production/Apache testing is claimed. Edge viewport emulation is not physical-device testing.

- [ ] Real Chrome/Edge/Firefox/Safari login, invalid errors, remembered login, logout and password manager.
- [ ] Physical mobile/tablet portrait/landscape, OS keyboard and browser background/resume.
- [ ] Keyboard-only skip link, desktop sidebar collapse, drawer focus/return, account menu and dialog.
- [ ] Screen-reader labels/errors/status, table regions and dialog announcements.
- [ ] Contrast audit, 200% zoom/text scaling, reduced motion and touch-target review.
- [ ] Slow network, interrupted form/navigation, and browser Back after logout.
- [ ] Apache restricted to `public/`, if selected instead of Artisan serve.
- [ ] Production HTTPS/cookies/debug, real recovery delivery, security/privacy/operations review.

## Limits

No attendance concurrency, RBAC/branch isolation, facial/PAD/GPS verification, employee CRUD, leave approval, notification delivery, exports or production deployment is implemented or claimed. Test stubs do not approve attendance.

The sandbox helper stopped launching commands after development-guidance setup. Subsequent checks used explicitly approved elevated commands; global PATH and PHP configuration were not changed as a workaround.
