> Historical phase snapshot. Its two-role and deferred-leave/correction scope is superseded by Phase 5. Current roles and workflow: [Phase 5 implementation](PHASE_5_IMPLEMENTATION.md) and [permissions](RBAC_PERMISSIONS.md).

# Phase 4 development report

Completed 2026-11-12. Approved project: **Web-Based Employee Attendance and Management System with Live Camera Photo Capture and GPS Geofencing**. Phases 1–3 working features and existing local records were preserved. No Phase 5 implementation, deployment, paid API integration or new dependency was started.

## Branch map enhancement (2026-11-16)

HR can configure the existing branch geofence using a Leaflet/OpenStreetMap picker and live radius circle. This enhancement adds the pinned Leaflet npm dependency, no schema migration, and no new attendance decision path. Saved branch configuration continues through the existing capture/event snapshots and backend Haversine/accuracy checks. Configuration changes invalidate stale captures and audit before/after office values; historical events stay unchanged. Active branches now require a complete configuration. [Map behavior, files and tests](BRANCH_MAP_GEOFENCING.md).

## A. Implementation summary

Implemented real camera/GPS attendance against Laravel/MariaDB: employee readiness checks, capture/session lifecycle, server geofence, image validation/private encryption, authoritative Time In/Out including overnight sessions, idempotency and conflicting-tab prevention. Added own history/protected evidence, company-wide HR monitoring/filters/sorting/pagination, explicit pending exception approval/rejection, assistance disposition, real dashboard totals, refresh-based review updates, audit and retention maintenance.

The system has exactly HR and Employee. Facial recognition, enrollment, face matching and liveness were removed from the current UI/plans. There is no gallery/file-picker fallback or simulated successful punch. Scheduling, leave, formal correction and payroll functionality remain outside this phase.

## B. Exact file inventory

Paths below are relative to `C:/xampp/htdocs/Attendance_System/`. Existing uncommitted Phase 3 files were present before this work; they are not all Phase 4 additions.

| Created | Responsibility |
| --- | --- |
| `database/migrations/2026_11_12_062140_create_attendance_core_tables.php` | Six new tables, employee version, restrictive FKs and generated uniqueness |
| `config/attendance.php` | Capture/GPS/review/retention development policy |
| `app/Models/AttendanceRecord.php` | Session summary and accepted times |
| `app/Models/AttendanceEvent.php` | Original punch facts and encrypted location/explanation |
| `app/Models/AttendancePhotoEvidence.php` | Private normalized image reference/metadata/purge marker |
| `app/Models/AttendanceCaptureSession.php` | Bound expiring single-use coordination |
| `app/Models/AttendanceReview.php` | Final HR decision |
| `app/Models/AttendanceAssistanceRequest.php` | Support-only request/disposition |
| `app/Services/AttendanceService.php` | Readiness, capture, lifecycle, atomic submission, retries/review |
| `app/Services/GeofencingService.php` | Haversine and reported-location policy |
| `app/Services/AttendancePhotoStorage.php` | GD validation/normalization and encrypted storage |
| `app/Services/AttendanceQueries.php` | Authorized listings/filters and actual statistics |
| `app/Http/Requests/AttendanceSubmissionRequest.php` | Image/location/token input validation and validation audit |
| `app/Policies/AttendanceRecordPolicy.php` | Owner or company-wide HR record/evidence access |
| `app/Http/Controllers/EmployeeAttendanceController.php` | Capture/location/submit/status/history/detail/assistance endpoints |
| `app/Http/Controllers/AttendanceEvidenceController.php` | Authorized no-store JPEG retrieval |
| `app/Http/Controllers/HR/AttendanceController.php` | HR monitoring and pending support |
| `app/Http/Controllers/HR/AttendanceReviewController.php` | Confirmed HR decisions/dispositions |
| `app/Console/Commands/PruneAttendanceEvidence.php` | Retention and optional orphan reconciliation |
| `resources/js/attendance.js` | Camera/GPS state, capture/retake/switch/cleanup, network outcome handling |
| `resources/views/attendance/capture.blade.php` | Mobile attendance interface |
| `resources/views/attendance/index.blade.php` | Own history/HR monitoring and assistance |
| `resources/views/attendance/show.blade.php` | Event timeline, protected evidence and review forms |
| `resources/views/attendance/updates.blade.php` | Owner review updates/HR pending queue |
| `scripts/php/attendance.ini` | Project-only GD and exception argument protection |
| `tests/Support/AttendanceData.php` | Isolated employee/branch/session/photo fixtures |
| `tests/Feature/AttendanceWorkflowTest.php` | Server-time/overnight/retry/lifecycle/query boundaries |
| `tests/Feature/AttendanceSecurityTest.php` | Auth/identity/location/file/access/rollback/rate boundaries |
| `tests/Feature/AttendanceReviewTest.php` | Pending/approve/reject/assistance transitions |
| `tests/Feature/AttendanceRetentionTest.php` | Evidence deletion/holds and unchanged core facts |
| `tests/Unit/GeofencingTest.php` | Pure distance and geofence-policy edge cases |
| `tests/Browser/attendance.spec.js` | Synthetic media UI plus real MariaDB attendance/review |
| `docs/PHASE_4_IMPLEMENTATION.md` | This final A–M development report |
| `docs/ATTENDANCE_WORKFLOW.md` | State transitions and error/retry behavior |
| `docs/CAMERA_IMPLEMENTATION.md` | Camera lifecycle, image limits, HTTPS/mobile instructions |
| `docs/GPS_GEOFENCING.md` | Coordinate/accuracy/freshness/distance policy |
| `docs/ATTENDANCE_DATABASE.md` | Relational schema/constraints and privacy maintenance |
| `docs/ATTENDANCE_SECURITY.md` | Implemented defenses, privacy decisions and residual risks |
| `docs/PHASE_4_TEST_RESULTS.md` | Executed tests and unexecuted device/production gates |

| Modified existing files | Phase 4 change |
| --- | --- |
| `routes/web.php` | Authenticated employee/evidence and HR review routes; replace attendance/notification placeholders |
| `routes/console.php` | Scheduled daily evidence pruning |
| `config/filesystems.php` | Non-served private attendance disk |
| `config/workspace.php` | Remove obsolete attendance placeholder definition |
| `app/Http/Middleware/WorkspaceSecurityHeaders.php` | Permit camera/location on capture page only |
| `app/Http/Controllers/DashboardController.php` | Actual attendance stats/employee recent records |
| `app/Http/Controllers/WorkspaceController.php` | Actual review updates; remaining placeholders only leave/reports |
| `resources/views/employee/dashboard.blade.php` | Actual recent attendance and capture entry link |
| `resources/views/hr/dashboard.blade.php` | Actual attendance totals and monitoring link |
| `resources/views/hr/employees/show.blade.php` | Replace biometric placeholder with employee attendance link |
| `resources/views/components/select.blade.php` | Optional unique control IDs for repeated forms |
| `resources/views/components/state.blade.php` | Human-readable attendance states/tones |
| `resources/views/components/layouts/app.blade.php` | Current project branding/footer |
| `resources/views/components/layouts/guest.blade.php` | Remove obsolete biometric branding |
| `resources/js/app.js` | Register reusable attendance Alpine component |
| `resources/css/app.css` | Whole-frame responsive video, 64px actions and textarea styles |
| `scripts/dev.ps1` | Inherit project GD configuration in server/test child PHP |
| `package.json` | Include attendance JavaScript in existing format checks |
| `.env.example` | Current name and configurable policy examples; no credentials |
| `.env` (ignored, local only) | Change only obsolete `APP_NAME`; no key/credential changes |
| `tests/Browser/fixture.php` | Local-only marked attendance fixture and FK-safe evidence cleanup |
| `tests/Feature/AuthenticationFoundationTest.php` | Update former placeholder assertions to functional attendance/update contract |
| `README.md` | Current features/run/test/policy/mobile instructions |
| `docs/SYSTEM_BLUEPRINT.md` | Current architecture replacing obsolete facial-service plan |
| `docs/REQUIREMENTS.md` | Implemented acceptance, two-role matrix and confirmation register |
| `docs/TECH_STACK.md` | Actual stack/GD decisions; no biometric service/dependency |
| `docs/DEVELOPMENT_ROADMAP.md` | Phase 4 completion and unstarted proposed next phases |
| `docs/DATABASE_SCHEMA.md` | Link implemented attendance schema to existing master data |
| `docs/RBAC_PERMISSIONS.md` | Attendance/evidence/review permissions |
| `docs/UI_COMPONENTS.md` | Current functional mobile capture and real metrics |

Existing formatter was run against dirty PHP/frontend files. It made a routine indentation-only adjustment in the already-uncommitted `app/Console/Commands/BootstrapHR.php`; bootstrap behavior was preserved. No Phase 3 schema or account data was reset. Compiled `public/build/` assets and test artifacts are generated/ignored, not new application modules. No fabricated attendance seeder was added; tests create synthetic facts through real endpoints.

## C. Database changes

One incremental migration (batch 3) adds `employees.attendance_version` and `attendance_records`, `attendance_capture_sessions`, `attendance_events`, `attendance_photo_evidence`, `attendance_reviews`, `attendance_assistance_requests`. See [full schema](ATTENDANCE_DATABASE.md) for relationships, indexes, encryption and deletion rules. Unique generated keys prevent two active employee sessions/two effective actions; capture/event/photo/review uniqueness supports atomic consume and final decisions. Core events store original UTC server time and branch-policy snapshot; a separate record summarizes accepted facts.

## D. Attendance workflow

Employee action → authenticated eligibility/current primary assignment → short capture session → fresh GPS preview → explicit live photo capture/retake → server revalidation and normalized private evidence → transaction/unique constraints/audit → committed result. Time Out uses a separate photo/location and the existing open record across midnight. Pending exceptions block conflicting new punches until HR decides. Assistance records support, not attendance. [Lifecycle and statuses](ATTENDANCE_WORKFLOW.md).

## E. Camera functionality

MediaDevices preview and Canvas JPEG Blob, appropriate mobile video attributes, whole-frame aspect ratio, capture/retake/cancel/switch and stream/object-URL cleanup. Enumeration failure keeps the active camera usable without switching. No gallery input or face detection. Capture tokens reduce replay but cannot attest a physical camera. [Implementation and mobile HTTPS](CAMERA_IMPLEMENTATION.md).

## F. GPS geofencing

One-shot Geolocation; Laravel resolves the assigned branch and calculates Haversine distance. Range/age/accuracy and conservative distance-plus-accuracy boundary checks determine normal acceptance. Missing configuration never silently passes. Final submission recalculates the result; branch-policy changes invalidate captures. Coordinates/timestamps remain untrusted reported data. [Detailed policy](GPS_GEOFENCING.md).

## G. Photo storage

Technically valid JPEG/PNG ≤2 MiB, dimensions/pixel count bounded, GD decode/normalize to metadata-free JPEG ≤1280 long side. Normalized hashes flag repeats. Files are encrypted at randomized `.bin` paths in `storage/app/private/attendance`. Only authorized owner/HR controller responses return JPEG with no-store/nosniff. Retention removes expired photo/location detail while preserving original attendance/review facts. Protect the encryption key and backups. [Privacy/storage controls](ATTENDANCE_SECURITY.md).

## H. HR monitoring

Actual Time In/Out/day/open/completed/pending/old-open/rejected/geofence-exception counts; searchable company table with employee/branch/date/session/verification filters and allowed sorting. Details include original evidence, branch snapshot, reported coordinates/accuracy/distance and review timeline. HR decisions require remarks/confirmation and cannot be repeated or self-approved. Refresh-based `/notifications` exposes pending exceptions to HR and own decisions to employees.

## I. Security controls

Existing Fortify/CSRF/account revocation preserved. Fresh eligibility/assignment checks, prohibited spoofed fields, session-bound expiring token, employee version, atomic consume, request fingerprint, database uniqueness, explicit manual exception review, private encrypted evidence/location, owner policies, throttling, audit and bounded retention implemented. Filesystem/DB crash reconciliation and real browser evidence spoofing remain documented limitations. [Full security/privacy review](ATTENDANCE_SECURITY.md).

## J. Testing results

91 PHP tests / 627 assertions passed. Complete Edge suite: 4 passed; expanded attendance rerun: 1 passed, including real MariaDB HR exception approval, device-enumeration fallback and lost-response recovery after server commit. Vite build, Pint/Prettier, Blade compile, migration/generated constraints and scheduler inspection passed. Synthetic fixtures cleaned up; real pre-existing local users/master data retained. No physical-device/load/penetration/production-compliance claim. [Exact results and manual checklist](PHASE_4_TEST_RESULTS.md).

## K. Local testing instructions

```powershell
cd C:\xampp\htdocs\Attendance_System
& 'C:\xampp\php\php.exe' artisan migrate --no-interaction
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task serve
```

With existing database running, open `http://127.0.0.1:8000/login`. Sign in as HR, configure actual branch coordinates/radius and an activated employee/current assignment, then use employee attendance and HR monitoring. In another terminal run `scripts/dev.ps1 -Task test` or `-Task browser` (use the PowerShell invocation above if execution policy requires it). Do not create a second server on the same port. No shared employee password exists; invited employees choose theirs.

For physical phones use trusted HTTPS, correct certificate SAN/CA trust, compatible web PHP/GD and only `public/` document root. Do not use plain LAN HTTP or weaken browser security. The exact XAMPP configuration sequence and checklist are in [camera documentation](CAMERA_IMPLEMENTATION.md). No HTTPS certificate/server was installed automatically.

## L. Remaining issues and rollout gates

Physical Android/iPhone/webcam and real indoor GPS checks, company policy/notice/retention/accessibility decisions, multi-process/load/deadlock testing, production HTTPS/security/backup/restore and scheduler monitoring remain required. The shared HR mutex serializes writes and needs capacity measurement. Browser camera/GPS evidence is spoofable and is not definitive presence proof. Core/audit/assistance/capture/backup retention and legal holds require company policy; bounded evidence pruning is implemented. Formal missing-punch corrections, schedules/classifications, leave, exports and payroll are unimplemented by design.

## M. Next phase

Proposed Phase 5: confirm scheduling and missing-punch/correction rules, then implement historically versioned schedules and controlled correction requests without overwriting original evidence. [Revised roadmap](DEVELOPMENT_ROADMAP.md). **Phase 5 has not started. Stop here and await the next development instruction.**
