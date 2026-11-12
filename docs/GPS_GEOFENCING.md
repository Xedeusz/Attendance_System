# GPS and geofencing — Phase 4

Primary files: `resources/js/attendance.js`, `app/Services/GeofencingService.php`, `app/Services/AttendanceService.php`, `config/attendance.php`.

## Reading and validating location

The browser calls `getCurrentPosition` only during the explicit attendance workflow, using `enableHighAccuracy: true`, `maximumAge: 0`, and a 15-second timeout. It sends latitude, longitude, reported accuracy in metres, and the browser's retrieval timestamp in epoch milliseconds. It does not watch or continuously track employees. Permission denial, unavailable services, unsupported/insecure browsers and timeout can be retried or routed for review/assistance. [MDN API reference](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/getCurrentPosition).

Employee identity and branch are resolved server-side from the active current primary assignment. Client-supplied branch/employee IDs are prohibited. Multiple historical assignments are supported; the one current primary assignment is authoritative. Arbitrary branch choice is unavailable. Future-dated, inactive or missing assignment/branch blocks attendance.

HR configures each real branch's coordinates and radius through existing Branch Management. No location or radius is guessed or seeded. Missing configuration returns `branch_not_configured`, never normal success. A capture freezes the branch/assignment/policy snapshot. Changes require a fresh capture; a transfer during an open session requires assistance.

## Server calculation

Haversine distance uses a mean Earth radius of 6,371,008.8 metres and clamps its intermediate value to avoid floating-point domain errors. Distances are computed on the backend, then rounded to two decimal places for storage/display. Acceptance uses the unrounded value.

```text
a = sin²((lat2-lat1)/2) + cos(lat1) cos(lat2) sin²((lon2-lon1)/2)
distance = R × 2 × asin(sqrt(clamp(a, 0, 1)))
```

Angles are converted to radians. This is appropriate for configurable office circles; it does not model floors, walls, polygons or indoor GPS reception.

The request requires finite numeric coordinate ranges −90…90 and −180…180, positive reported accuracy 0.1…100,000 metres, and all four location fields together. Missing all fields can only support an explicitly requested review with technically valid photo evidence.

| Check, in order | Initial policy / result |
| --- | --- |
| Missing branch coordinates/radius | `branch_not_configured` |
| Missing reading | `location_unavailable` |
| Invalid coordinates/numbers | Request validation error / `location_error` |
| Older than 45 seconds, or more than 15 seconds in the future | `stale_location` |
| Reported accuracy greater than 50 metres | `accuracy_insufficient` |
| Calculated point outside radius | `outside_geofence` |
| Point inside but distance + reported accuracy exceeds radius | `boundary_uncertain` |
| All checks met | `within_geofence`, eligible for `location_validated` |

The whole uncertainty circle must fit inside the branch radius. This conservative policy can reject legitimate indoor readings; review is available. The 45-second/50-metre defaults are development proposals requiring HR/IT approval and branch trials. Radius is individually configured. The snapshot stores the actual policy used.

Location preview is recalculated at final submission. The UI refreshes an old reading before the initial upload, but preserves the exact body for uncertain-network retries. A location retrieval timestamp is an untrusted freshness signal, not authoritative time. Clock skew can need HR review. Both Time In and Time Out need independent validation.

## Storage and risks

Each accepted/reviewable event saves encrypted reported coordinates/retrieval timestamp, accuracy, calculated distance, original result, branch snapshot and server receipt time. HR can inspect precise readings; employees see only their own status/accuracy/distance. Coordinates are hidden from model serialization and are purged with photo retention. Audit logs contain status/record IDs, not raw locations or images.

Browser accuracy is an estimate; coordinates and timestamps may be spoofed. Passing a circle does not prove that a person or camera was physically present. Failed GPS is not automatic absence or evidence of misconduct. Production needs approved exception policy, employee notice, device trials and HR review practices. No Google Maps key, map billing, continuous tracking or external location provider is needed for this phase.
