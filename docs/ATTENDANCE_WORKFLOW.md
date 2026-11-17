# Attendance workflow — Phase 4

Approved method: authenticated employee + reported GPS/geofence checks + live browser camera photo + server-recorded time. HR and Employee accounts use attendance; Supervisors remain restricted to Leave Management. No facial recognition, enrollment, face matching, or liveness service is used.

## Time In

1. Sign in with an activated account and open `/employee/attendance`.
2. Select Time In. Laravel resolves the employee from the session and checks active employment, current primary assignment, active branch, and absence of an open/pending session.
3. Laravel creates a short-lived capture UUID, random secret token, hashed browser-session binding, employee attendance version, and branch/policy snapshot. The default lifetime is 180 seconds.
4. Browser automatically watches location while capture verification is active. The panel previews distance, accuracy, freshness and geofence readiness locally from the saved branch policy; submission recalculates it on the server. See [automatic GPS monitoring](AUTOMATIC_GPS_GEOFENCE_MONITORING.md) for lifecycle, recovery and privacy behavior.
5. Select **Start live camera**, allow camera access, capture an appropriate office photo, and preview or retake it. There is no gallery/file-selection control.
6. Submit. Laravel validates the token, session, current assignment/policy/version, image, coordinates, age, accuracy, and attendance lifecycle. Staged images are validated/normalized before the short database transaction.
7. Laravel records its receipt time in UTC. The work date is the Time In date in Asia/Manila. The browser clock and location retrieval timestamp never set the official punch time.
8. Only after commit does the UI display the saved event, branch, original server time, verification status, and detail link.

## Time Out and overnight sessions

Time Out requires an accepted open Time In, a new capture session, new location check, and separate camera photo. The event attaches to the existing record even after midnight; it retains the original work date. No automatic midnight closure or fabricated Time In exists. A server clock that precedes Time In causes rejection. A changed assignment during an open session requires HR assistance rather than silently switching branches.

A completed record may be followed by another Time In. There is no one-record-per-calendar-day rule because shift scheduling is not implemented. One open or pending record per employee is enforced in the database.

## Independent statuses

| Concept | Values and meaning |
| --- | --- |
| Session | `pending`: first punch awaits review; `open`: accepted Time In without accepted Time Out; `completed`: both accepted; `cancelled`: rejected first punch |
| Verification | `location_validated`, `pending_review`, `exception_approved`, `rejected` |
| Review | `not_required`, `pending`, `approved`, `rejected` |
| Classification | Reserved nullable field; no automatic present/late/absent/leave/undertime classification |

Open sessions older than 24 hours are a follow-up warning. They are not automatically absent or fraudulent. “Location validated” means reported data met the configured rules; it does not authenticate physical presence or the image's origin.

## Exceptions and HR review

| Situation | Outcome |
| --- | --- |
| Valid image, configured branch, acceptable location | Automatically recorded as location validated |
| Outside/uncertain/stale/missing/inaccurate GPS, missing geofence configuration, or repeated normalized image | Rejected normally; may be submitted explicitly as pending review with a 10–1,000-character explanation when policy permits |
| Missing/inactive/future assignment, inactive account/employment, unauthorized/expired/stale token, malformed or missing image | Rejected; no accepted or pending attendance event is manufactured |
| Camera unavailable, missing Time In, branch transfer during an open session | Assistance request; no punch created |

HR opens the original event and protected photo, examines untrusted reported coordinates, accuracy/distance and branch snapshot, enters remarks and confirms approval/rejection. HR cannot review their own attendance. Only pending events can receive one decision. Approval creates `exception_approved`, not `location_validated`; rejection retains original evidence until its retention deadline. Neither changes the original event timestamp or geofence result.

Pending first punch blocks further punches. Pending checkout keeps the accepted Time In open and also blocks further punches until reviewed. Rejected first punch cancels that record and permits a new Time In. Rejected checkout leaves the original session open and permits a fresh checkout; the rejected event remains in its timeline.

Assistance has its own pending/resolved/rejected lifecycle. HR disposition records follow-up only. Creating/correcting punches without valid capture belongs to a separately authorized future correction workflow.

## Retries, duplicate prevention and errors

Database transactions lock the existing HR administration mutex and employee, re-read account/assignment state, and consume a capture atomically. The employee version invalidates competing tabs. Unique generated columns prevent two open records or two effective actions per record; the capture-to-event FK is unique.

An identical retry of a consumed capture returns the original event, including after expiry; changed evidence returns 409. Exact request/photo data must be reused. On an ambiguous response the UI preserves the capture identifier across refresh and automatically checks the status endpoint without retrying the POST. Unconsumed captures remain unknown until serialized status inspection confirms expiry; then a fresh photo/session is required. Validation errors are 422; authorization failures 403; stale/reused captures 409; missing retained evidence 410; throttling 429. Confirmed HTTP rejections retain their status even with HTML bodies. Session expiry/deactivation requires sign-in/HR assistance. No optimistic success or offline punch queue exists. See [Attendance submission recovery](ATTENDANCE_SUBMISSION_RECOVERY.md) for state transitions, runtime setup, and regression results.

History `/employee/attendance/history` is owner-scoped and paginated. HR `/hr/attendance` supports employee name/number search, employee/branch/date/status filters and whitelisted sorting. `/notifications` shows database-backed exception updates; refresh to see decisions. There is no push/email provider.
