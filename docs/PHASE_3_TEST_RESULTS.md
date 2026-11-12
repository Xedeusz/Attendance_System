# Phase 3 Test Results

Date: 2026-11-12. Windows; PHP 8.5.12; Laravel 13.42.0; PHPUnit 12.5.43; MariaDB 10.11.16; Playwright 1.65.0 with installed headless Edge.

## Baseline and execution

The Phase 2 baseline passed **24 tests / 121 assertions** before implementation. The new schema was applied additively to real MariaDB without resetting existing data. Temporary browser records use unique synthetic markers and are removed after testing; the existing login is retained.

Commands run from `C:\xampp\htdocs\Attendance_System`:

```powershell
& 'C:\xampp\php\php.exe' artisan migrate --no-interaction
& 'C:\xampp\php\php.exe' artisan migrate:status
& 'C:\xampp\php\php.exe' -d extension=intl artisan db:show --counts
& 'C:\xampp\php\php.exe' -d extension=intl artisan db:table employee_branch_assignments
& 'C:\xampp\php\php.exe' artisan route:list --except-vendor
& 'C:\xampp\php\php.exe' artisan view:cache
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=never
& 'C:\xampp\php\php.exe' vendor\bin\pint --dirty --format agent
.\scripts\dev.ps1 -Task build
.\scripts\dev.ps1 -Task browser
```

Final result: **65 PHP tests / 348 assertions passed; 0 failures and 0 errors.** The final headless Edge suite passed **3 tests in 53.0 seconds**, including the complete Phase 3 workflow on MariaDB. Production asset build, Pint, Prettier check, Blade compilation, route inspection, hourly cleanup schedule and Git diff checks passed. No automated test remains failing.

## Automated PHP coverage

| File | Behavior covered |
| --- | --- |
| `Phase3AuthorizationTest.php` | Exactly two repeatable roles; employee denial on 11 HR/legacy/company-report paths; forbidden writes; no other-employee profile access; protected-field injection; policy checks; HR/shared self-service; pending/deactivated denial; contact-only updates; correct-current-password requirement |
| `EmployeeManagementTest.php` | HR create with fixed pending Employee role; user/profile/assignment/invitation linkage; duplicates and role injection; edit/password preservation; transfer history; search/sort allowlist; deactivate/reinvite; rollback on audit failure; honest delivery failure; sessions/reset-token cleanup on email changes |
| `BranchDepartmentTest.php` | HR branch/department create/edit/search; duplicates; latitude/longitude/radius validation; active-reference checks; assignment-aware deactivation; explicit confirmation; no deletion; generated unique current-assignment constraint |
| `AccountActivationTest.php` | Correct account activation/hashing; one-time use; expired/revoked/used/inactive/deactivated rejection; resend invalidation; strength validation; encrypted local mail and HR reauthentication |
| `HRAccountTest.php` | Reauthentication before HR creation; pending HR invitation; last-active-HR protection; allowed other-HR deactivation; no public bootstrap route; private interactive first HR and single-use bootstrap |
| Updated Phase 2 auth/recovery tests | Secure login/logout/throttling/CSRF configuration, no registration/default login, output escaping, role-aware redirects, disabled future actions, generic recovery, reset expiry/reuse, no recovery-based activation bypass |

PHP tests use SQLite memory and fake mail where appropriate. The local-preview transport test deliberately switches to local environment, supplies a valid CSRF token and exercises the real encrypted transport against the isolated test database. It never uses production SMTP or writes sensitive payloads to logs.

## Real browser and MariaDB coverage

Existing browser regressions verify login, rejection of invalid credentials/missing CSRF, password visibility, cookie flags, protected pages, logout dialog, navigation drawer and no JavaScript page errors.

The Phase 3 browser scenario verifies:

1. Synthetic HR login to real HR dashboard.
2. Branch and department creation through actual forms.
3. Employee registration, assignment and truthful local invitation status.
4. Password reauthentication before private inbox access.
5. Real activation through the fragment-token UI in a separate browser context.
6. Employee login, HR-route 403 and own contact update.
7. HR deactivation followed by rejection of the employee's already-open session.
8. Authorized reactivation and final state display.
9. Whole-page overflow checks for HR dashboard, directories, registration/detail/branch forms, own profile and settings at **320, 360, 375, 390, 430, 768 and 1440px**.

Screenshots of synthetic HR/employee screens are in ignored `test-results/screenshots/`. Sensitive activation/mail steps have tracing and automatic failure screenshots disabled. No raw activation token is intentionally printed by fixture/test code. Cleanup is local-only and restricted by unique synthetic email/record markers; role rows and the pre-existing login are preserved.

## Issues found and resolved

- Old Phase 2 preview expectations contradicted the approved two-role model; tests now verify role routing and denied HR access instead of user-selectable administrative previews.
- The real local-mail transport test initially returned CSRF rejection because switching to `local` also enables framework CSRF checks. Supplying the real CSRF token fixed the test without disabling protection.
- Invitation mail now uses a normal framework Mailable for proper fake/real transport coverage.
- Email changes and activation now invalidate stale reset credentials; recovery does not deliver to ineligible accounts or change account activation state.
- Shared password policy eliminates divergent bootstrap/activation/recovery/change rules.
- Array-valued email input now produces a normal validation response instead of PHP string-conversion warnings; the malformed employee/HR payload regression test passes.

## Manual/production checks not performed

No claim of physical Android/iPhone/tablet, Safari/Firefox, screen-reader, production SMTP delivery, HTTPS deployment, multi-process race stress, penetration testing or load testing is made. Those remain explicit acceptance tasks.

- [ ] Physical devices/OS keyboards, orientation changes, Safari/Firefox and password managers.
- [ ] Keyboard/focus return, screen readers, 200% zoom and contrast audit.
- [ ] Approved SMTP end-to-end receipt/bounce/outage and recovery support.
- [ ] Production HTTPS/proxy/APM secret redaction, backup restore, key rotation and scheduler operation.
- [ ] Concurrent independent-process last-HR/invitation/assignment stress tests and agreed load targets.
- [ ] Company/DPO notices, lawful basis, retention and real-data rollout approval.

No biometric/PAD, camera/GPS, attendance recording, leave approval, payroll or full analytics was implemented or tested. No next development phase was started.

## Completion checks

MariaDB still contains the pre-existing login, two role records and the 17 intended framework/Phase 3 tables. Temporary synthetic HR/users, employee profiles, branches, departments, invitations, mail and associated test audit events were cleaned up. Schema inspection confirmed InnoDB, utf8mb4, restrictive foreign keys and the stored generated unique current-assignment index. No live database wipe/reset or permanent default HR account creation was performed.

Documentation links and code fences passed automated checks. The blueprint, requirements, technology record, roadmap and README now point to exactly HR/Employee. The existing development server remains available at `http://127.0.0.1:8000`; the operator must privately bootstrap the first HR if none exists. No next phase is authorized by this completion record.
