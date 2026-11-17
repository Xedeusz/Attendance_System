# Automatic GPS and geofence monitoring

## Workflow

Selecting Time In or Time Out starts a single `navigator.geolocation.watchPosition` watcher for that capture. No location collection starts merely by opening the attendance page. The options are high accuracy, zero cached-age allowance and a 15-second acquisition timeout. Browser/device behavior determines update frequency; updates are not guaranteed every second.

Each new sample replaces the previous coordinates, reported accuracy and original acquisition timestamp. The panel shows the assigned branch, distance, allowed radius, accuracy, reading age and clear status text. The ordinary Retry Location button has been removed. A small Restart location access control appears only after denied permission or exhausted automatic recovery.

GPS does not create an attendance event. The employee must still take a live camera photo and explicitly submit.

## Preview policy and server authority

`resources/js/attendance-location.js` uses the capture's server-supplied branch snapshot, not hardcoded coordinates. Its Haversine calculation uses the same 6,371,008.8-metre earth radius as GeofencingService and the same ordered policy: valid coordinate/accuracy input, timestamp freshness/future tolerance, maximum accuracy, distance versus radius, then distance plus accuracy versus radius. Boundary uncertainty is displayed separately and never treated as inside. The preview does not enlarge the radius, retain a previous inside decision after movement, or smooth away a failed reading.

Repeated samples producing the same status do not generate toasts. Status text and reported values update in place. Colors supplement text rather than substitute for it. No HTTP location-preview requests are made for GPS updates. Reverb is independent and is not required to obtain or evaluate location.

The existing Laravel location-preview endpoint remains available for compatibility; normal browser monitoring does not call it. Submission still runs AttendanceSubmissionRequest and AttendanceService/GeofencingService checks against current assignment, policy snapshot, fresh coordinates, actual photo evidence and server timestamps. No backend validation or exception-review policy was relaxed.

## Freshness and recovery

The original position timestamp is compared with the server-aligned clock. Fractional milliseconds are floored only to match the existing integer-millisecond request contract; they are never replaced with the current time. The displayed reading age is recalculated while the workflow is active, so a quiet watcher cannot leave an old inside reading permanently valid.

An outdated reading becomes unready immediately. Stale samples schedule at most three controlled watcher restarts, five seconds apart. A usable fresh sample clears the restart timer. Recoverable timeout/unavailable errors use at most three restart delays of 2, 5 and 10 seconds. After exhaustion the panel offers deliberate recovery. Permission denial stops the watcher and does not automatically trigger another permission prompt; the employee must re-enable permission and use the recovery action.

Before constructing a new submission body, the client reevaluates freshness. If needed it makes one bounded `getCurrentPosition` request for a fresh sample. Known invalid normal attendance does not reach the POST; an explicit existing HR-review request may carry failed or unavailable GPS under the unchanged exception policy. The image and coordinate payload are frozen when transmitted. Ambiguous HTTP results retain original submission idempotency/reconciliation and do not restart GPS monitoring.

## Privacy and lifecycle

The watcher ID is retained, and an existing watcher is cleared before another is started. Generation checks ignore callbacks from a previous capture or cleared watcher. Collection stops on cancellation, capture expiry, submission transmission/success, component destruction, hidden tabs and navigation/pagehide. Hidden tabs clear location readiness; returning to a still-valid active workflow starts fresh monitoring, unless permission was denied or submission outcome is uncertain.

There is no all-day location tracking, background location history, or GPS broadcast. Only an explicitly submitted attendance request sends the evidence/location to the existing protected backend. Photo capture, private storage, session ownership, role restrictions, audit transactions and official timestamps remain unchanged.

## Browser testing

For Edge or Chrome desktop development, use localhost or HTTPS. Allow location access. In DevTools, open Sensors and select a custom location near the actual saved assigned branch. Begin Time In/Out. Change the simulated coordinates from outside to inside and back, and inspect accuracy/status. Different browsers may not immediately emit a watch callback after a Sensors change; wait for an actual callback or cancel and begin a fresh verification. Simulation is handled through the standard API, with no production bypass.

On Android or iPhone use HTTPS, enable browser/site and OS location permissions, begin an attendance capture, and move toward/away from the saved branch. Confirm cleanup after cancellation/navigation and fresh monitoring after returning from suspension. Physical mobile hardware, signal accuracy and manual Chrome Sensors UI testing were not executed here. Automated Edge tests used browser-protocol geolocation overrides through the real watchPosition API; synthetic streams cover camera controls.

## Verification

- 13 Node tests passed: inside/outside, poor accuracy, boundary uncertainty, stale/future timestamps, malformed/missing/configuration inputs, movement/fresh recovery, one active watcher, ignored late callbacks, denied permission, hidden/expired/uncertain privacy gating, fresh acquisition at submit and explicit HR exception handling.
- 17 existing Laravel attendance workflow/security tests passed (192 assertions), including server GPS/image/freshness validation, ownership, duplicate prevention and transaction rollback. Backend PHP was not changed.
- Microsoft Edge: both browser scenarios passed (1.8 minutes total). Native GPS movement, accuracy changes, watcher cleanup, no preview POSTs/automatic punches, WebSocket-independent monitoring, camera capture, Time In/Out, submission reconciliation and HR review were exercised. The attendance scenario used synthetic camera/GPS; the dedicated monitoring scenario used Edge browser-protocol position changes through the native Geolocation API.
- Vite production build, Prettier checks, Pint and diff whitespace checks passed.
- Initial browser attempts exposed an empty-output JSON parse in fixture cleanup and a review-dialog timing assumption in the existing attendance harness. These were corrected before rerunning.

## Modified files

- `resources/js/attendance-location.js`: local evaluator, watcher lifecycle and controlled recovery.
- `resources/js/attendance.js`: capture lifecycle integration and final freshness check.
- `resources/views/attendance/capture.blade.php`: compact automatically updating location panel and exceptional recovery action.
- `tests/Browser/attendance-location.test.js`: focused deterministic policy/lifecycle tests.
- `tests/Browser/gps-monitoring.spec.js`: native browser movement, independence from WebSockets, no preview POSTs and no automatic punches.
- `tests/Browser/attendance.spec.js`: watcher-aware camera and real database attendance regression.
- `docs/ATTENDANCE_WORKFLOW.md` and this document: updated workflow and operational guidance.