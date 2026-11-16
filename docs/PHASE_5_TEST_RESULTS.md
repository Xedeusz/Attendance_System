# Phase 5 test results
Executed 2026-11-16 on Windows: PHP 8.5.12, Laravel 13.42.0, Fortify 1.42.0, PHPUnit 12.5.43, XAMPP MariaDB 10.11.16 and installed Microsoft Edge through the existing Playwright setup. No new test dependency was installed.

## Final executed checks
| Check | Result | Scope |
| --- | --- | --- |
| Complete backend suite | **186 passed, 1199 assertions** | Isolated SQLite migrations plus all existing and Phase 5 feature/unit regressions; final run 19.3 seconds |
| Focused Phase 5 tests | **52 passed** (earlier focused run: 308 assertions; final validation also included in full suite) | Leave lifecycle, balances, private documents, policy failures, corrections, Supervisor authorization/provisioning/assignments |
| Complete browser suite | **16 passed** | Existing auth/account activation, camera/GPS capture, branch map/Photon, notifications and UI regressions plus Phase 5; 3.7 minutes |
| Final affected browser rerun | **1 passed** | Phase 5 employee → Supervisor final approval → HR correction workflow after final stale-request and UI refinements; 27.5 seconds including worker startup |
| Production frontend build | Passed | Existing Vite build, no dependency changes |
| PHP formatting | Passed/fixed | vendor/bin/pint --dirty --format agent |
| Whitespace review | Passed | git diff --check; only an existing CRLF normalization notice remained |
| Local additive migration | Passed | Phase 5 migration applied as batch 4; no fresh/reset/wipe |
| Routes/schedule | Passed | Employee/HR/Supervisor paths registered; hourly routing and daily document purge scheduled |
| Local database inspection | Passed | 32 tables and exactly 3 role rows; existing users/attendance/evidence retained and synthetic workflow data cleaned |

All final executed test suites passed. Intermediate selector/validation/date-comparison/Blade issues were corrected and rerun. There are no outstanding failed or blocked automated tests. The optional db:show --counts diagnostic initially required Intl; it passed using the already installed extension via a per-command -d extension=intl flag, with no global PHP change.

## Regression coverage
- Exactly three seeded roles; Supervisor login/landing and leave-only navigation; 25 unrelated direct module URLs forbidden.
- Employee/HR ordinary leave decision denial; owner and Supervisor IDOR; private document access; Supervisor attendance/photo/correction denial including own records.
- HR-only account creation, secure hashed expiring single-use invitations, controlled Employee identity reuse, retained attendance and old-session invalidation.
- Explicit assignments, inclusive dates, conflict prevention, audited immediate reassignment, future boundary handoff, expiry/inactive/revocation checks and no self-review.
- Unassigned application saved/notified/routed without resetting submitted_at; actual scoped statistics and HR monitoring without another approval.
- Date ordering, overlap, half-day policy, forged identity/state, required supporting document, invalid content/size and confirmation checks.
- Approval policy revalidation, inactive requester, absent/insufficient balances, charge limits, one final debit/review, repeat-decision 409, database final-review uniqueness and pending-only cancellation/history/notices.
- Encrypted private bytes/reasons, protected no-store attachment download and configured expiry purge retaining metadata/application.
- Corrections: ownership, pending duplication, time validation, HR-only/no self-review, overlap, stale manual revision and genuine camera checkout after submission, original evidence immutability, effective checkout/open guard, rejected/cancelled changes and supplemental missing-record entry without fabricated attendance/evidence.
- Persistent owned notifications/read operations, Supervisor leave-category isolation, and coexistence with existing attendance review updates.
- Browser confirmation cancel leaves the application pending; confirmation finalizes once; server-confirmed top-right success toast has no action buttons; configured balance changes from synthetic 5 to 3 only on final approval.
- Browser leave upload/submission, Supervisor review and HR manual correction use the actual local MariaDB application.

## Responsive and visual evidence
The Phase 5 leave application and Supervisor review pages were checked for page overflow at 320, 360, 375, 390, 430, 768, 1024 and 1280 pixels. Employee and HR correction/history views were checked at 390 pixels. Existing UI regressions also passed at their desktop/mobile widths. Browser page errors were empty in the Phase 5 workflow.

Screenshots generated in the ignored local test-results directory:
- test-results/screenshots/phase5-supervisor-review.png
- test-results/screenshots/phase5-employee.png
- test-results/screenshots/phase5-hr.png

Screenshots use synthetic identities/documents only. Fixtures delete their scoped users, assignments, leave types/requests/reviews/ledger/documents, corrections, native notifications and private file bytes. Existing company records are not reset or wiped.

## Reproduce
```powershell
cd C:\xampp\htdocs\Attendance_System
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task test
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task browser
```

With PHP and project Node on PATH and PHP_INI_SCAN_DIR set to scripts/php:
```powershell
php artisan test --compact tests/Feature/PhaseFiveWorkflowTest.php tests/Feature/SupervisorAuthorizationTest.php
npm run test:browser -- tests/Browser/phase5.spec.js
php artisan migrate:status --no-interaction
php artisan schedule:list --no-interaction
```

## Unexecuted operational checks and policy boundaries
Physical Android Chrome, iPhone Safari, tablet/camera/GPS hardware, HTTPS field trials, target multi-worker peak-load/deadlock tests, external SMTP delivery, penetration testing and encrypted backup/APP_KEY restore drills were not executed. Edge viewport tests do not certify those devices. SQLite tests and retry/uniqueness checks do not establish InnoDB multi-worker capacity.

Official company types/credits, accrual/carry-over, schedules/holidays, deadlines, document retention/legal holds, approved-leave/manual-entry reversal and review coverage still require company confirmation. The application exposes configuration/manual review and does not invent those policies. Schedule classification, payroll, formal exports and Phase 6 are outside this implementation.
