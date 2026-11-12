# Technology stack and decisions

Current baseline: 2026-11-12, through Phase 4. The approved project is a responsive employee attendance application with live camera photo evidence and reported GPS geofencing. Facial recognition, biometric enrollment, face matching and liveness have been removed from the architecture.

| Layer | Installed / selected | Rationale |
| --- | --- | --- |
| Backend | PHP 8.5.12, Laravel 13.42.0 | Existing maintained Laravel application, conventions and validation/transactions; no rebuild |
| Authentication | Fortify 1.42.0, session guard | Existing login/logout/activation/recovery; no public registration or shared default password |
| Authorization | Two fixed roles: HR and Employee | Company-wide HR administration; owner-scoped employee self-service; middleware/policies and active-state checks |
| Database | XAMPP MariaDB 10.11.16, InnoDB | Explicitly accepted MySQL-compatible local engine; actual generated-column/FK migrations and browser workflow tested here |
| Frontend | Blade, HTML5, Tailwind 4.3.5, Alpine 3.17.5 | Existing responsive light design, reusable components and small interactive modules; no SPA dependency |
| Build | Vite 8.3.8, Node 26.13.1/npm 11.20.0, Composer 2.11.2 | Existing pinned setup; exact dependency graph in lockfiles |
| Camera | Browser MediaDevices + Canvas | Explicit live preview/capture/retake/switch, no file picker; Blob submitted to Laravel |
| GPS | Browser Geolocation + PHP Haversine service | Fresh one-shot reported reading and backend policy checks; no continuous tracking |
| Image processing | PHP GD | Existing extension enabled project-locally; decoded format/dimension checks and re-encoding/metadata removal without a new Composer package |
| Evidence | Laravel filesystem private disk + authenticated encryption | Encrypted files outside `public/`, protected owner/HR retrieval, retention command |
| Testing | PHPUnit, SQLite memory; existing Playwright 1.65.0 + installed Edge | Isolated backend tests and actual MariaDB browser operations; synthetic camera/location only |
| Formatting | Pint, Prettier 3.10.0 | Existing repository tools |
| Local account delivery | Existing encrypted private local mail | Not external email; activation/reset instructions remain in account workflow documentation |

No new npm/Composer dependency or cloud account was added for Phase 4. GD is enabled by `scripts/php/attendance.ini`, inherited via `scripts/dev.ps1`; global PHP configuration is unchanged. A different web runtime needs its own compatible PHP/extensions configuration.

## Decisions and boundaries

- Laravel remains the authoritative business layer. Browser-reported identity, success flags and punch timestamps cannot approve attendance. Browser APIs provide evidence, not physical-presence attestation.
- A relational record/event/evidence/review design separates original facts from derived accepted session times. Unique generated indexes and transactions protect retries and open sessions. The actual supported local engine is MariaDB; test SQL portability before switching to MySQL or another engine.
- The existing HR administration mutex is reused for consistent account/branch changes. Image work happens before that short lock. Load/concurrency testing must inform any redesign before 27-branch deployment.
- Maps visualization and Google Maps billing are unnecessary for circle validation. HR enters approved coordinates/radius. No Maps key is required. A future map UI needs separate authorization and cost review.
- No Python/FastAPI biometric service, vendor SDK, model license, embedding/template store or enrollment module remains planned under the approved scope.
- Scheduler runs private-mail and attendance evidence cleanup. Attendance update lists are queried from events; there is no unnecessary notification provider/queue. Exports, leave and scheduling are later authorized phases.
- Production must use HTTPS, a maintained runtime, non-public DB/storage, managed secrets, monitoring and tested backups. XAMPP and Artisan serve are development tools; they are not a completed production deployment.

Tailwind's supported browser floor and real employee devices must be checked before rollout; API support alone does not guarantee styling/camera usability. [Tailwind compatibility](https://tailwindcss.com/docs/compatibility). Camera and location need secure contexts and explicit permission. [MediaDevices](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia), [Geolocation](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/getCurrentPosition).

Policy defaults (180-second capture, 45-second location age, 50-metre accuracy, 30-day evidence, 90-day pending hold) are development proposals in `config/attendance.php`, not confirmed company/legal policy. [Security/privacy](ATTENDANCE_SECURITY.md) and [GPS policy](GPS_GEOFENCING.md).

Historical environment/bootstrap details are in [Phase 2 setup](PHASE_2_SETUP.md); account/master-data implementation is in [Phase 3](PHASE_3_IMPLEMENTATION.md). Those phase reports describe their dates, not the current feature availability.
