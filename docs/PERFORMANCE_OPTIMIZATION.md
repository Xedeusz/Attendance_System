# Attendance System performance optimization

Measured on November 18, 2026 in a dedicated loopback environment. The live employee database, tunnel, environment file and private evidence were outside the benchmark. The Vite production build and an additive Laravel migration were delivered in the workspace.

## Architecture and findings

The system is a Laravel 13 application with server-rendered Blade, Alpine, Vite, MariaDB, private attendance and leave disks, and Reverb workspace updates. Login uses Fortify. Attendance writes and leave decisions use transactions and row locks. Approved leave PDFs are rendered with Playwright and Chromium. The largest measured transfer was the desktop login poster. The HR dashboard repeatedly queried the same sets of rows. The attendance reporting scans had limited date-index access, and each successful attendance submission scheduled two workspace updates from its audit entries.

## Changes

- **HR and attendance reporting.** Related employee-account, leave, correction and attendance counts now use conditional aggregation or grouped counts. Attendance statistics went from eight queries to four. Date filtering uses an inclusive lower bound and an exclusive next-day upper bound so the date columns are available to the optimizer.
- **Indexes.** The migration adds standalone indexes on `attendance_events.occurred_at` and `attendance_records.work_date`. Existing compound indexes and constraints remain part of the schema. The table below shows the optimizer's row estimates for representative counts.
- **Employee and Supervisor views.** The employee dashboard loads missing relationships and uses stored leave type snapshots. The Supervisor and Employee leave lists removed two unused eager loads. Employee directory filter options select the IDs and names they render. Search terms, authorization scopes, page sizes and filter behavior remain as before.
- **Attendance submissions.** The result builder reuses the employee already loaded for that capture. Both audit entries are retained and the final event schedules one workspace revision per affected recipient. The authenticated capture, geofence evaluation, uploaded evidence encryption, server timestamp, recovery response and idempotency checks still apply. Browser camera permission, hardware and GPS acquisition time were outside the HTTP timing; location validation starts at the server endpoint.
- **Login and maps.** The guest JavaScript entry carries shared interaction and notification code without loading authenticated features. Leaflet and its styles load when a map starts, including components inserted on a workspace update. The login poster uses a full-resolution WebP of the original artwork, reduced from 2,341,098 to 310,100 bytes. The Manila clock reuses its formatter.
- **Notifications and workspace updates.** The bell uses a 15-second in-page copy of the most recent successful list. Revision, unread count, reconnect and fallback refresh signals invalidate the copy. Mark operations re-fetch an open list. Revision rows for a recipient use one upsert. Queue dispatch still occurs after transaction commit for the authorized recipient's private channel.
- **Documents and QR.** The official form embeds a 640 × 640 print derivative of the LIMCOMA watermark. The HTML preview still uses the original PNG. Approval and issuance remain synchronous and authenticated. Stored PDFs retain their issued bytes and checksum. The verification, revocation, role and audit rules remain in force.
- **Other modules.** The measured calendar uses its existing date-overlap index, approved-only event scopes, filter controls and paginators. The branch, profile, stored-PDF and QR routes were measured to check for regressions.

| MariaDB EXPLAIN query | Before access and estimated rows | After access and estimated rows |
| --- | --- | --- |
| Attendance events over a two-day timestamp window | Index scan, 4,242 | `occurred_at` range, 1 |
| Branch attendance for the previous seven days | Branch index reference with `DATE(work_date)`, 300 | `(branch_id, work_date)` range, 70 |
| Attendance for the previous seven days across branches | Index scan, 7,984 | `work_date` range, 1,890 |

## Method

- Windows/XAMPP, PHP 8.5.12, MariaDB 10.11.16, Laravel 13.42.0, Node 26.13.1 and Microsoft Edge. PHP CLI OPcache was disabled. Two loopback PHP HTTP workers shared a separate MariaDB instance. Private benchmark files had separate storage roots.
- Both schemas started with 270 synthetic employees, 27 branches, 9 departments, 8,100 attendance records, 4,320 capture sessions and events, and 540 leave requests. The dataset included one issued official PDF. The benchmark used synthetic HR, Supervisor and Employee accounts. Bcrypt used the same test cost in both runs.
- Read routes use one warm-up and 12 measured requests. Sign-in, Time In and Time Out use eight samples, and GPS validation uses 16. The mixed load ran six waves of four HTTP requests spread over two workers. The two concurrent submissions raced the same capture. Sequential retry and race counts are correctness checks with small sample sizes.
- HTTP timings include response receipt. PHP timings include framework bootstrap and handling through response headers. SQL timing sums Laravel query events and PHP memory reports the request's peak in MiB. The table uses nearest-rank percentiles. A p95 from eight or twelve measurements is sensitive to the slowest sample.
- Browser data comes from four fresh contexts per viewport, with production assets, third-party requests excluded and no CPU throttle. The mobile viewport was 390 × 844 at 150 ms RTT and 200 KB/s downlink. A loopback proxy used the application to render login and served the built static assets without compression. Browser and HTTP data measure different work.
- The issuance benchmark ran the actual approval service, Chromium rendering, encryption and storage eight times per phase. It verified the checksum of every decrypted PDF. The HTTP test server hit a Node OpenSSL entropy error starting the renderer, so the report shows CLI service timing for issuance and HTTP timing for serving an already approved PDF. The QR row measures the authenticated verification result page. Guest token capture and authenticated scan have separate authorization coverage in the test suite.
- Raw measurements are in `.tools/performance-before.json`, `.tools/performance-after.json`, `.tools/performance-after-repeat.json` and `.tools/performance-pdf-{before,after}.json` in the local checkout. The benchmark scripts in `.tools` are workspace-local profiling tools.

## HTTP and database results

The primary post-change timings included a concurrent PHPUnit run during part of the read batch. Times rose across several routes, including routes with no query changes. The repeat below checks whether those increases persisted with a quieter machine. Negative percentage values indicate lower time.

| Route or operation | HTTP ms p50 | HTTP ms p95 | PHP ms p50 | PHP ms p95 | PHP p50 change | SQL queries p50 | Cumulative SQL ms p50 | Peak PHP MiB p50 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| POST sign in | 412.0 → 394.8 | 593.0 → 523.5 | 323.2 → 312.9 | 506.2 → 435.5 | −3.2% | 23 → 23 | 13.7 → 15.8 | 26.01 → 26.01 |
| GET sign in | 371.0 → 354.2 | 456.8 → 544.8 | 285.5 → 270.4 | 368.0 → 431.9 | −5.3% | 2 → 2 | 5.1 → 4.9 | 24.20 → 24.20 |
| HR dashboard | 461.4 → 560.3 | 595.1 → 949.1 | 377.6 → 437.7 | 512.9 → 844.3 | +15.9% | 32 → 22 | 31.4 → 29.4 | 26.04 → 26.08 |
| Employee dashboard | 443.5 → 651.2 | 874.5 → 958.2 | 358.3 → 564.8 | 794.7 → 865.4 | +57.6% | 15 → 13 | 11.6 → 32.5 | 25.99 → 25.96 |
| Supervisor overview | 492.8 → 752.1 | 840.0 → 964.2 | 410.1 → 624.3 | 750.2 → 772.3 | +52.2% | 24 → 22 | 33.2 → 76.9 | 27.85 → 27.79 |
| Employee directory | 465.1 → 658.8 | 725.7 → 995.3 | 376.6 → 570.9 | 642.7 → 901.1 | +51.6% | 13 → 13 | 10.7 → 95.0 | 27.33 → 27.28 |
| Employee search | 478.7 → 610.5 | 726.5 → 1,628.2 | 392.4 → 521.6 | 617.6 → 1,362.7 | +32.9% | 13 → 13 | 12.6 → 15.7 | 27.67 → 27.62 |
| Employee profile | 422.8 → 563.1 | 437.8 → 884.9 | 337.4 → 476.3 | 350.6 → 799.6 | +41.2% | 15 → 15 | 11.5 → 22.8 | 25.97 → 25.97 |
| Attendance monitoring with date filter | 512.5 → 782.1 | 538.0 → 1,113.8 | 425.7 → 689.6 | 446.4 → 1,016.9 | +62.0% | 21 → 17 | 31.1 → 73.0 | 27.23 → 27.24 |
| Attendance record detail | 415.0 → 489.7 | 444.5 → 842.4 | 329.0 → 396.9 | 359.0 → 737.7 | +20.6% | 11 → 11 | 8.4 → 9.1 | 25.88 → 25.88 |
| Branch directory | 425.9 → 445.3 | 461.9 → 677.3 | 341.7 → 360.2 | 375.6 → 595.2 | +5.4% | 7 → 7 | 7.4 → 8.3 | 26.92 → 26.92 |
| HR leave queue | 466.4 → 597.9 | 475.4 → 851.0 | 379.4 → 503.7 | 389.8 → 762.2 | +32.7% | 17 → 17 | 12.0 → 16.6 | 27.58 → 27.59 |
| Employee leave detail | 440.0 → 484.2 | 519.6 → 729.4 | 348.3 → 390.4 | 432.7 → 562.0 | +12.1% | 19 → 19 | 12.5 → 14.9 | 26.51 → 26.52 |
| HR leave calendar | 402.2 → 401.3 | 588.1 → 504.5 | 314.1 → 311.9 | 502.1 → 422.7 | −0.7% | 9 → 9 | 8.6 → 8.5 | 25.69 → 25.69 |
| Calendar events JSON | 526.6 → 531.8 | 651.9 → 740.7 | 441.4 → 443.7 | 565.7 → 650.7 | +0.5% | 19 → 19 | 22.5 → 21.2 | 28.68 → 28.68 |
| Notification dropdown JSON | 380.1 → 384.4 | 677.4 → 418.6 | 294.8 → 294.5 | 594.3 → 332.6 | −0.1% | 12 → 12 | 6.8 → 7.2 | 24.73 → 24.74 |
| Workspace state JSON | 364.0 → 367.8 | 448.8 → 438.3 | 280.2 → 280.8 | 361.2 → 349.6 | +0.2% | 12 → 12 | 7.0 → 7.6 | 24.75 → 24.75 |
| Authenticated QR verification result | 453.0 → 463.8 | 504.1 → 641.6 | 366.2 → 378.9 | 410.2 → 502.1 | +3.5% | 15 → 15 | 11.3 → 11.4 | 31.05 → 31.05 |
| Serve an existing approved PDF | 429.5 → 455.5 | 500.7 → 603.7 | 338.7 → 339.3 | 358.1 → 496.1 | +0.2% | 15 → 15 | 10.6 → 12.6 | 31.14 → 31.14 |
| Mixed load, four concurrent reads | 901.0 → 974.7 | 1,566.6 → 1,400.9 | 562.7 → 452.0 | 795.7 → 667.7 | −19.7% | 13 → 13 | 43.1 → 25.3 | 26.04 → 26.08 |
| Identical attendance retry | 698.0 → 586.2 | 698.0 → 586.2 | 589.1 → 496.0 | 589.1 → 496.0 | −15.8% | 21 → 20 | 34.7 → 28.0 | 37.61 → 37.62 |
| GPS and geofence validation | 433.8 → 432.0 | 892.4 → 864.0 | 344.9 → 343.7 | 796.0 → 774.6 | −0.3% | 13 → 13 | 13.5 → 13.3 | 26.56 → 26.56 |
| Time In upload and submission | 713.4 → 801.1 | 4,192.3 → 1,199.8 | 588.5 → 684.5 | 4,087.6 → 1,092.8 | +16.3% | 49 → 41 | 40.7 → 61.1 | 37.61 → 37.61 |
| Time Out upload and submission | 818.1 → 725.5 | 4,407.1 → 1,453.7 | 721.1 → 634.8 | 4,257.3 → 1,322.1 | −12.0% | 46 → 38 | 91.3 → 44.2 | 37.62 → 37.62 |
| Concurrent duplicate Time In | 755.7 → 794.6 | 804.6 → 833.1 | 647.2 → 684.9 | 647.4 → 690.4 | +5.8% | 21 → 20 | 17.5 → 36.2 | 37.61 → 37.61 |

### Repeat after the test suite

The repeat used the warmed post-change schema after its synthetic attendance and approval writes. The repeat is a follow-up check with that sample history. It shows lower median SQL time for dashboard and attendance reporting. Some tail latencies and the employee profile median still exceeded the original measurements.

| Route | Original PHP ms p50 | Repeat PHP ms p50 / p95 | SQL ms p50 before → repeat | Queries p50 before → repeat |
| --- | ---: | ---: | ---: | ---: |
| GET sign in | 285.5 | 271.4 / 628.6 | 5.1 → 5.5 | 2 → 2 |
| HR dashboard | 377.6 | 372.4 / 915.4 | 31.4 → 22.1 | 32 → 22 |
| Employee dashboard | 358.3 | 339.7 / 375.9 | 11.6 → 10.8 | 15 → 13 |
| Supervisor overview | 410.1 | 399.6 / 421.6 | 33.2 → 30.4 | 24 → 22 |
| Employee directory | 376.6 | 385.2 / 593.8 | 10.7 → 9.7 | 13 → 13 |
| Employee search | 392.4 | 400.8 / 517.7 | 12.6 → 13.9 | 13 → 13 |
| Employee profile | 337.4 | 395.3 / 688.4 | 11.5 → 12.4 | 15 → 15 |
| Attendance monitoring with date filter | 425.7 | 440.5 / 649.5 | 31.1 → 23.3 | 21 → 17 |
| HR leave queue | 379.4 | 379.5 / 439.7 | 12.0 → 12.9 | 17 → 17 |

## Cold browser results

| Viewport | Requests p50 | Bytes transferred p50 | Load ms p50 | Load ms p95 | LCP ms p50 | LCP ms p95 | CLS p50 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Desktop | 7 → 8 | 3,132,758 → 778,894 | 692.1 → 824.1 | 1,118.3 → 939.6 | 768.0 → 928.0 | 1,224.0 → 1,132.0 | 0.00 → 0.00 |
| Throttled mobile | 5 → 6 | 686,409 → 363,543 | 4,129.0 → 2,513.8 | 4,324.4 → 2,874.4 | 2,568.0 → 2,544.0 | 2,772.0 → 2,908.0 | 0.00 → 0.00 |

The desktop transfer fell by 75.1% and the mobile transfer fell by 47.0%. Throttled mobile median load time fell by 39.1%. Desktop median load and LCP increased in this run. The primary Blade CSS is 113 KB uncompressed in both builds. The guest JavaScript loads approximately 110 KB uncompressed across two chunks, compared with the original 422 KB application bundle.

## Newly issued PDFs

| Issuance metric | Before → after |
| --- | ---: |
| Service elapsed ms p50 | 3,526.3 → 3,841.3 |
| Service elapsed ms p95 | 5,238.5 → 5,025.7 |
| SQL queries p50 | 32 → 32 |
| Cumulative SQL ms p50 | 29.9 → 125.2 |
| Peak PHP MiB p50 | 38.75 → 31.73 |
| Generated PDF bytes p50 | 1,061,327 → 138,191 |

Median PDF size fell by 87.0%. Median PHP peak memory also fell. Median issuance elapsed time increased in the CLI sample. Chromium startup, rendering and I/O are included.

## Verification

- PHPUnit passed 262 tests with 2,268 assertions. A separate run passed the new attendance date-boundary regression with 2 assertions.
- Laravel Pint and the Vite production build passed. The additive migration was applied on the isolated MariaDB database. Git reported no whitespace errors.
- Edge smoke verification covered the styled mobile sign-in, the HR dashboard, one request across two quick notification opens, a second request after a changed revision and Leaflet loading for the branch map. The Playwright shared attendance map and live attendance scenarios passed against isolated MariaDB. The live scenario exercised simulated camera and GPS, interrupted upload recovery, live HR updates over Reverb, exception review and the responsive HR dashboard.
- Real MariaDB requests recorded eight synthetic Time In / Time Out pairs per phase. The same capture submitted through two PHP workers returned one event ID. Two simultaneous approval attempts in the optimized service returned one approval and one conflict. All newly issued benchmark PDFs matched their stored checksums.
- Existing feature tests exercise camera and GPS request validation, missing evidence, idempotent retry, notification ownership, supervisor and employee access, authenticated QR verification, revocation and signed PDF integrity.

## Deployment and limits

- Apply the additive reporting indexes during a maintenance window after checking index build and lock duration against staging data. The migration was applied only to the isolated benchmark database.
- Deploy the Vite manifest and its assets together. Confirm the configured HTTPS origin serves the login page, CSS and JavaScript. Measure real navigation through the tunnel or production proxy before treating the local browser numbers as a target.
- Measure with production PHP workers, OPcache and compressed, cacheable fingerprinted assets. Use the existing private response cache policy for authenticated HTML, notification JSON and employee files. No shared full-page cache or worker setting was introduced.
- Keep the application queue worker and Reverb supervised. Monitor the rate and age of queued broadcasts. Measure queue drain, reconnect propagation, concurrent load and failed-job recovery on staging with synthetic accounts.
- The run did not measure production traffic, real devices or GPS acquisition, long-session browser memory, or production WebSocket and queue latency. The synthetic workload is a comparison point for these configurations.

## Files changed

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/HR/EmployeeController.php`
- `app/Http/Controllers/LeaveController.php`
- `app/Http/Controllers/WorkspaceSyncController.php`
- `app/Services/AttendanceQueries.php`
- `app/Services/AttendanceService.php`
- `app/Services/LeaveApplicationDocumentService.php`
- `app/Services/WorkspaceSync.php`
- `database/migrations/2026_11_18_044825_add_attendance_reporting_indexes.php`
- `resources/css/app.css`
- `resources/images/limcoma-leave-watermark.jpg`
- `resources/images/limcoma-poster.webp`
- `resources/js/app.js`
- `resources/js/attendance-location-map.js`
- `resources/js/branch-location-map.js`
- `resources/js/guest.js`
- `resources/js/leaflet-map.js`
- `resources/js/notification-dropdown.js`
- `resources/js/realtime.js`
- `resources/views/components/layouts/guest.blade.php`
- `resources/views/hr/employees/index.blade.php`
- `vite.config.js`
- `tests/Browser/attendance.spec.js`
- `tests/Feature/AttendanceWorkflowTest.php`
- `tests/Feature/OfficialLeaveFormTest.php`
- `docs/LIMCOMA_LEAVE_FORM_WATERMARK.md`
- `docs/NOTIFICATION_DROPDOWN.md`
- `docs/PERFORMANCE_OPTIMIZATION.md`
