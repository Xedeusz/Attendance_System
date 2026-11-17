# Attendance submission recovery

## Findings and reproduction

The supplied browser screenshot shows HTTP 429. Numeric Laravel throttle middleware without a prefix shares the authenticated user's counter across routes, regardless of the configured limit. Attendance start, location, submit, status, photo, and unrelated routes used that counter. Repeated capture/status activity could therefore reject a submission. Attendance start/location/submit/status now use separate prefixes with the original limits.

The frontend attempted JSON parsing before classifying HTTP errors. An HTML or malformed rejection lost its HTTP status and became an uncertain result. Missing/malformed success replies, timeouts, network failures, and server errors require reconciliation; a received rejection does not.

Plain `C:\xampp\php\php.exe` in this environment also lacks GD. The old image preparation guard returned 503 before any database insertion; the client replaced that explanation with a generic server error. Missing GD now returns a specific photo validation error before any write. Start development using the existing project extension configuration:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 serve
```

Stop the earlier Artisan server with Ctrl+C first. This helper sets `PHP_INI_SCAN_DIR` to `scripts/php`, which enables GD without changing global PHP settings. The project-only ini also disables opcode caching: the isolated PHP 8.5 development server otherwise failed during configuration bootstrap (`Cannot instantiate interface RecursiveIterator`, followed by `A facade root has not been set`). Serving the same application with opcode caching disabled restored login and passed Edge coverage. This setting does not change global or production PHP configuration. Run `scripts/dev.ps1 build` to rebuild assets.

Reproduce the old rate-limit defect by reading capture status thirty times and then submitting: the shared counter rejects the submit at its twenty-request limit. Reproduce ambiguous response delivery by letting the POST commit and dropping its response. Reproduce HTML rejection handling by returning an HTML 429 with `Retry-After`.

All current migrations were already applied. Historical logs contain older missing-column errors for `effective_time_out_at`, but no corresponding new attendance exception for the screenshot. Read-only inspection of employee 0123 found 27 captures, two earlier events, one record, and three latest unconsumed captures. No attendance data was reset or deleted.

## Request and persistence lifecycle

The development helper uses `artisan serve --no-reload` because Laravel 13's default server child-process environment filter drops `PHP_INI_SCAN_DIR`. Without that flag the parent PHP process loads GD but the HTTP server does not. With the flag, project-scoped GD and opcode-cache settings reach the HTTP server. Restart the server manually after changing `.env`; ordinary PHP source changes still take effect on the next request.

1. Authenticated start creates a server-generated UUID, hashed random token, session binding, assignment/policy snapshot, attendance version, and expiration.
2. Browser requests fresh GPS and captures a live camera frame. Submission uses same-origin credentials, CSRF, multipart evidence, and a 30-second timeout.
3. Laravel validates and normalizes the photo, stages encrypted private evidence, then serializes writes using the existing administration/employee/capture locks.
4. The transaction records the event, evidence reference, consumed capture, attendance version, derived record, and audit entries together. Failed transactions discard staged files.
5. A confirmed result carries the saved event, original server timestamp, review status, and current action availability. The UI clears the error and updates its status and buttons directly.

## Idempotency and recovery

The capture UUID is the logical submission key. The existing request fingerprint covers normalized evidence and location/review input. An identical replay returns the original result; conflicting evidence gets 409. Unique capture-event, effective-action, and open-employee constraints remain enforced. The client never automatically retries an attendance POST.

Before transmitting, the client stores only the UUID, action, and expiry in sessionStorage scoped to employee ID. It stores no token, photo, or coordinates. Refresh restores the pending identifier and checks status. Storage-disabled browsers retain recovery only for the current page lifetime.

The owner/session-protected GET `/employee/attendance/captures/{capture}/status` uses the same transaction locks as submission and reloads the capture under lock:

| Status | Meaning | UI behavior |
| --- | --- | --- |
| succeeded | Consumed capture with saved event | Show saved result and authoritative action availability |
| unknown | Unconsumed capture still within its execution window | Keep actions and cancellation blocked; preserve identifier |
| not_recorded | Expired, unconsumed capture after serialized inspection | Clear pending state and require a fresh photo/capture |

An unconsumed capture is not proof of failure while another request could still reach its transaction. After expiry, an outstanding request cannot pass the existing freshness check under the same lock. Missing status records, inaccessible sessions, malformed replies, and unavailable status services never establish failure.

Automatic status checks are bounded to four attempts with 0/2/4/8-second delays. Recovery stops on authentication, permission, session, or rate-limit errors. The visible Check submission status action performs only GET; unresolved cases direct employees to existing HR assistance. Cancellation cannot discard an uncertain submission. No repeated uncertainty toasts are emitted.

## HTTP behavior

- 200 with a valid saved event: confirmed success.
- 401/403/419: access/session guidance; preserve pending IDs if encountered during recovery.
- 409/413/422: specific conflict, upload, or validation rejection; permit correction when appropriate.
- 429: rate-limit guidance including Retry-After; no uncertain state for a rejected POST.
- 408, network failure, timeout, malformed success, or 5xx: reconcile the original UUID without resubmitting.
- HTML error bodies retain their HTTP classification. HTML/redirected successful responses never fabricate attendance success.

## Real-time and security

This checkout has no Reverb/Echo attendance integration or broadcast event in the submission path. Persistence and direct responses do not depend on a WebSocket or queue worker. HR monitoring/history continue to read committed records through the existing page-refresh behavior; no new live broadcast system was introduced.

Authentication, ownership/session binding, role restrictions, CSRF, server timestamps, fresh GPS, geofencing, review policy, private encrypted camera evidence, audit transactions, and duplicate prevention remain enforced. SweetAlert2 remains the notification system; modal-only backdrop, heightAuto, and returnFocus options are excluded from toast configuration.

## Verification

- Full PHP suite with project GD configuration: 190 tests passed, 1,302 assertions.
- Dedicated recovery tests cover independent throttle budgets, status recovery after submit throttling, unknown versus expired captures, and recovery after committed capture expiry.
- Existing workflow/security suites cover Time In/Out, stale/malformed GPS and images, ownership, replay conflicts, competing tabs, original timestamps, and transaction rollback/file cleanup.
- Frontend production build passed; PHP formatting passed.
- Microsoft Edge browser suite: passed (one end-to-end scenario, 40.6 seconds), including Time In/Out, HTML 429 rejection, automatic lost-response recovery, blocked cancellation during uncertainty, refresh recovery, private photos, history, HR review, no SweetAlert2 warnings, and responsive widths from 320 to 1440 pixels. Browser verification uses synthetic camera/GPS and isolated local MariaDB fixtures. Physical device permissions and genuine GPS hardware remain manual checks.

## Affected files and limits

- `app/Http/Controllers/EmployeeAttendanceController.php`: delegates secure status inspection to the service.
- `app/Services/AttendanceService.php`: serialized status inspection, result review status, and authoritative action availability.
- `app/Services/AttendancePhotoStorage.php`: specific pre-write photo-processing rejection when GD is unavailable.
- `routes/web.php`: separate attendance throttle counters.
- `resources/js/attendance.js`: HTTP classification, bounded GET recovery, pending identifier storage, safe cancellation, and action/status updates.
- `resources/js/notifications.js`: toast-compatible SweetAlert2 options.
- `resources/views/attendance/capture.blade.php`: reactive status and guarded recovery controls.
- `scripts/php/attendance.ini`: project-only development-server opcode-cache setting alongside existing GD configuration.
- `scripts/dev.ps1`: preserves project PHP settings in the HTTP server using `--no-reload`.
- `playwright.config.js`: optional isolated browser-test base URL.
- `tests/Feature/AttendanceSubmissionRecoveryTest.php`: four recovery regressions.
- `tests/Browser/attendance.spec.js`: HTML 429, lost reply, refresh recovery, and toast warning regressions.
- `docs/ATTENDANCE_WORKFLOW.md`: updated recovery instructions.
- `docs/ATTENDANCE_SUBMISSION_RECOVERY.md`: this investigation and implementation report.

The original running server must be restarted with the project helper to load the local PHP settings. Existing open pages need refresh to load the rebuilt JavaScript. No production deployment, physical camera/GPS verification, new broadcast infrastructure, or historical attendance correction was performed. The earlier PHP bootstrap failure prevented the first browser attempts from reaching login; the final browser scenario passed using the isolated server with opcode caching disabled.
