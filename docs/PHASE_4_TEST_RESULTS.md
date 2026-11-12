# Phase 4 test results

Date: 2026-11-12. Local PHP 8.5.12/Laravel 13.42.0/MariaDB 10.11.16, Windows, installed Microsoft Edge headless. No real employee evidence was collected for automated tests.

## Executed checks

| Check | Result | Scope |
| --- | --- | --- |
| Pre-change PHP baseline | 65 tests, 348 assertions passed | Phase 1–3 regression baseline |
| Final complete PHP suite | **91 tests, 627 assertions passed** | 26 new attendance/geofence tests plus existing suite, SQLite memory |
| Complete Edge suite | **4 tests passed** | Authentication/CSRF/session flags, responsive shell, Phase 3 CRUD/activation/revocation and Phase 4 attendance |
| Expanded attendance browser rerun | **1 passed** | MariaDB HR exception approval, 64px action size, enumeration fallback and recovery after a committed punch's response is dropped; original outside-geofence outcome preserved |
| Production asset build | Passed | Vite compiled local assets; no CDN runtime compiler |
| Pint / Prettier | Passed after formatting | Existing repository tooling |
| Blade compile | Passed | `php artisan view:cache` |
| Migration status | Passed | All five migrations applied, Phase 4 in batch 3 |
| Actual DB inspection | Passed | 23 InnoDB tables; generated active/effective unique indexes and restrictive attendance FKs inspected |
| Scheduler inspection | Passed | Daily attendance evidence prune, hourly existing local-mail prune |

Executed PHP result artifacts are ignored local files `.tools/phase4-final.xml` and `.tools/phase4-final-output.txt`. Browser outputs are `.tools/phase4-browser-output.txt` and `.tools/phase4-browser-final.txt`. Screenshots under ignored `test-results/screenshots/` contain synthetic records only. These artifacts are not committed or deployment assets.

All identified test failures were resolved: GD inheritance in the child PHP test process, test-client session cookie propagation, assertions for human-readable status text and an ambiguous “Completed” selector that also matched a filter option. Final runs have no failing/skipped PHP tests. A Node console warning about `NO_COLOR`/`FORCE_COLOR` is cosmetic. The first `db:show --counts` attempt needed Intl for formatted numbers; rerunning with command-local `-d extension=intl` succeeded. No new package was installed.

## Coverage

- Authentication, inactive account denial, missing employee/assignment, inactive/future branch assignment and Employee-to-HR access denial.
- Server identity/time/status spoofing rejection, token/ownership checks, expiry, branch-policy changes, competing-tab invalidation, same-result retry and conflicting replay denial.
- Valid server-timestamped Time In/Out, separate evidence, midnight/overnight work date, Manila daily-total boundary and server-clock regression rejection.
- Missing Time In/photo/location, repeated image, stale/outside/inaccurate/out-of-range/unconfigured GPS and invalid/tiny/oversized image rejection.
- Encrypted private files/location, owner/HR retrieval, cross-employee photo/detail denial and own-only history.
- Explicit pending review, approval preserving original time/GPS, rejected first punch, rejected checkout/retry, no self-review, required remarks/confirmation, one final decision and audit side effects.
- Assistance and HR disposition creating no attendance punch; retention deleting private evidence without changing core times; bounded pending hold and approval refusal after purge.
- Database rollback and staged-file cleanup on audit failure, throttling, capture-page-only permission headers and whitelisted filters.

Edge's Phase 4 test mocks MediaDevices with synthetic moving Canvas streams and Geolocation readings. It exercises camera permission failure/recovery, live preview, switching/old-track cleanup, capture/retake/cancel, enumeration-denied fallback, no file input, backend submission, Time In/Out, protected image responses, employee history, HR monitoring and exception approval. A network interceptor lets Laravel commit Time In, drops the response, then verifies that the status lookup recovers the saved result before proceeding. UI geometry is checked at 320/360/375/390/430/768/1440 widths, with no page overflow. This does not emulate an iOS camera permission dialog, real GPS or physical camera hardware.

Fixtures use unique QA markers and delete only their own accounts/master data/events/photos/reviews/captures/audits. After cleanup, the real local database retained its two users, one employee, one branch and one department; no synthetic attendance rows remained. No existing account or company configuration was replaced.

## Reproduce

```powershell
cd C:\xampp\htdocs\Attendance_System
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task test
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
# Keep the server running in another terminal:
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task serve
# From a separate terminal:
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task browser
```

The helper supplies the project GD configuration to parent and child processes. Browser tests require installed Edge and the real local MariaDB service; PHP tests do not use the company database. Never substitute a shared/production database for synthetic browser testing.

## Manual verification still required

None of the following physical-device/production checks has been executed. Use the trusted HTTPS setup in [CAMERA_IMPLEMENTATION.md](CAMERA_IMPLEMENTATION.md), record tester/date/device/OS/browser/network/branch and actual results.

| Device / case | Verify |
| --- | --- |
| Android phone, Chrome | Trusted HTTPS, no passive permission request, Time In/Out, front/rear switch, portrait/landscape, retake, cancel/background/navigation release |
| iPhone, Safari | Trusted CA, `playsinline`, permission grant/deny/regrant, camera orientation/switch fallback, preview quality and evidence retrieval |
| Desktop with real webcam | Busy/missing camera, permission denial, one/multiple cameras, no gallery option and readable whole-frame photo |
| Indoor/edge/outside branch | Accuracy variations, stale/timeout results, uncertainty margin, retry and explicit pending review; never false normal approval |
| Wi-Fi/mobile data/slow network | Interrupted upload, uncertain status lookup, same-result retry, session expiry and no duplicate punch |
| Disabled location/camera | Clear instructions and HR support path; no attendance created by support disposition |
| Overnight and missing checkout | Original work date/session, Time Out next day, open warning and branch transfer assistance |
| Accessibility | Keyboard/screen reader, visible focus, labels/status announcements, zoom and alternative HR procedure |
| HR review | Separate HR reviewer, original evidence/time/GPS retained, approve/reject and employee update, purged photo unavailable |
| Operations | Scheduler actually executes, retention/deletion/backup lifecycle, APP_KEY restore and privacy notice/access review |

## Unexecuted technical gates / residual risks

No physical Android/iPhone/webcam tests, Firefox/Safari automation, real satellite/location reception, production TLS installation, burst/load testing, multi-process database race/deadlock stress, penetration test, external-mail delivery, disaster restore or legal-compliance certification was performed. The current cross-branch mutex prioritizes correctness and needs peak-load measurement. Browser evidence remains spoofable; normal verification is not physical-presence attestation. Company exception/retention/scheduling and privacy processes require approval before rollout. These are explicit deployment gates, not passing tests.
