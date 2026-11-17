# Combined attendance evidence map

The attendance-details page presents two photo cards, followed by a compact shared Leaflet map beside stacked Time In/Time Out detail cards on desktop. Branch & geofence details sit below that comparison row. The header includes employee name/number, branch, date and existing record status badges. This replaces the repeated map layout documented in ATTENDANCE_LOCATION_MAP.md.

## Shared map and saved evidence

AttendanceLocationEvidence::day builds the comparison from the record's already-authorized, eager-loaded events. It selects the first accepted event for each action (location_validated or exception_approved), matching AttendanceService's record summary. If no event is accepted for an action, the latest attempt is shown with its actual review/rejection state. Earlier attempts remain in an expandable Additional submissions section with original photos, timestamps, metadata, explanations, reviews, technical coordinates and any permitted review controls. No evidence is discarded or rewritten.

One map renders up to three non-draggable markers: teal recorded branch (#087C68), purple Time In (#2F1B72), and an orange Time Out ring (#F97316). The distinct ring lets nearby or coincident pins remain distinguishable. Legend labels are buttons that open the respective popup even when pins overlap; unavailable markers have disabled buttons. Popups display the recorded event type, coordinates, accuracy, device capture time and original server time. DOM textContent prevents saved names from becoming executable HTML.

The single circle uses a historical event snapshot. The first complete branch geometry is preferred (Time In, then Time Out), followed by the first available recorded branch position; otherwise no branch pin/circle is fabricated. The chosen source is labeled in Branch geometry shown. If Time In and Time Out have different saved centers or radii, an explicit notice explains that only the named snapshot is drawn, while each event's original configuration remains in its own details and Technical Attendance Location Details. Current branch settings are never substituted and old geofence decisions are not recalculated.

The initial view and Fit recorded locations button fit the branch, available event pins and circle with padding and a bounded maximum zoom. ResizeObserver refits the map for responsive widths. Mouse/touch zoom, panning and OpenStreetMap attribution remain available through the existing Leaflet 1.9.4/config/maps.php integration. The map issues no GPS requests and is independent of Reverb availability.

## Layout and incomplete records

Photos are side by side at tablet/desktop widths, with original associations, server timestamps and status badges. Missing actions have explicit unavailable photo and detail cards. Purged/missing photos retain a Missing Photo state. Mobile stacks Time In photo, Time Out photo, map, branch details, Time In details and Time Out details. Photos use contain scaling rather than cropping evidence. Detail values wrap; raw coordinates remain secondary in one expandable technical section.

A missing/invalid/purged event location creates no pin; the other saved locations still display. Missing historical radius creates no arbitrary circle. Time Out-only and Time In-only records remain viewable. If all geometry is unavailable, no Leaflet instance or tile requests are created. Tile failure displays an inline fallback and expands technical details while keeping photos, statuses, distances and timestamps accessible.

Review forms stay outside the map's data-map-config subtree so normal workspace refresh can update statuses while preserving dirty form values. Existing realtime morph hooks reuse unchanged map instances; changed configuration destroys and recreates the map. Component destruction clears marker references, disconnects ResizeObserver and removes Leaflet handlers/layers.

## Authorization and integrity

Existing authenticated attendance-details routes, AttendanceRecordPolicy and Supervisor leave-only middleware are unchanged. HR can view authorized company evidence, employees only their own records, and Supervisors cannot access attendance evidence. Photo encryption and protected delivery remain unchanged. There are no new routes, public GPS endpoints, broadcasts of coordinates, dependencies, migrations, live tracking or geofence decision changes. Location values remain device-reported evidence, not independently verified physical presence. Manual corrections remain separate from original photos, positions and timestamps.

## Implementation files

- app/Services/AttendanceLocationEvidence.php
- resources/views/attendance/show.blade.php
- resources/views/components/attendance-location-map.blade.php
- resources/views/components/attendance-evidence-photo.blade.php (new)
- resources/views/components/attendance-event-details.blade.php (new)
- resources/js/attendance-location-map.js
- resources/css/app.css
- tests/Feature/AttendanceLocationMapTest.php
- tests/Feature/AttendanceSecurityTest.php
- tests/Browser/attendance-location-map.spec.js
- docs/ATTENDANCE_EVIDENCE_COMBINED_MAP.md (new)
- docs/ATTENDANCE_LOCATION_MAP.md (supersession link)
- docs/PHASE_4_IMPLEMENTATION.md (updated enhancement link)

## Verification

Feature tests exercise the shared configuration through the protected evidence route, including both actions, missing Time In/Out, differing snapshots, mixed inside/outside status, missing/invalid/purged coordinates, original timestamps, rejected-attempt retention, protected photos, manual correction integrity and role boundaries. Existing attendance workflow/review/retention tests remain part of the full suite.

The Edge test uses synthetic fixture accounts and real capture/submit endpoints with CSRF. It verifies a single map before and after checkout, exactly one branch pin/circle and the expected event pins, side-by-side photos and mobile stacking, read-only markers, distinct popup data, fit/zoom, image decoding, actual realtime refresh preserving map identity and forced tile-failure fallback. It rejects attempts to use live browser geolocation. Fixtures are cleaned through the existing synthetic-only cleanup workflow.

A shared circle cannot simultaneously represent differing historical geofences. The labeled source and per-event details make that limitation explicit; no past evidence is changed to force agreement.

### Executed results

- Focused map, security and review suite: 30 passed, 363 assertions.
- Final map/security recheck after legend controls: 25 passed, 300 assertions.
- Full PHP suite: 240 passed, 1,768 assertions (attendance, leave, corrections and profile access included).
- Microsoft Edge browser regression: 1 passed. Verified one map and circle, missing checkout, all three marker types, distinct popup data, accessible legend selection, non-draggable markers, fit/zoom, decoded photographs, side-by-side photo cards at 1440/768 px, vertical mobile ordering at 390/320 px, no horizontal overflow, refresh preserving the shared map, no live GPS access, and tile failure preserving evidence and expanding technical details.
- Production Vite build, Pint, Blade compilation and git diff --check passed. Existing synthetic fixture cleanup removed the generated browser test accounts/evidence.

## Compact location layout refinement

At desktop widths (1280 px and above), the location row uses approximately 62% map / 38% event details. The shared map is 320 px high. Time In and Time Out remain separate cards stacked on the right, aligned with the top of the map card. Tablet widths stack the map above two event detail columns; mobile stacks map, Time In, Time Out and branch details. The photo comparison remains above this section. Branch details use a compact full-width card below the location row.

Event labels are shortened only in these comparison cards. Captured (device) and Recorded (server) preserve their trust distinction, and full original label meanings remain in title tooltips. Values, timestamps, statuses, HR review forms, missing-data states, evidence selection, saved geometry and permission checks are unchanged. Map controls, legend buttons, three available marker types, circle, attribution and resize refitting are retained. Zoom animation is disabled on the evidence map so a quick zoom/fit sequence cannot let a pending transition override fitted bounds; zoom and pan remain available.

Files changed for this refinement:

- resources/views/attendance/show.blade.php
- resources/views/components/attendance-event-details.blade.php
- resources/css/app.css
- resources/js/attendance-location-map.js
- tests/Browser/attendance-location-map.spec.js
- docs/ATTENDANCE_EVIDENCE_COMBINED_MAP.md

The existing browser regression now measures desktop column proportions, detail/map alignment, the 280-340 px map height, branch placement below the row, tablet/mobile ordering, horizontal overflow, and the existing map interactions and fallback states. No new test dependency or database migration is needed.

Compact-layout verification: 25 map/security PHP tests passed with 300 assertions; Edge browser regression passed at 1440, 768, 390 and 320 px. Desktop ratios/alignment, the 320 px map height, mobile/tablet stacking, missing checkout, all marker types, circle, popups, zoom/fit, unchanged map reuse, photographs and tile-failure fallback were verified. Screenshots were visually inspected. Production build, Blade compilation, Pint, Prettier checks and whitespace checks passed. Synthetic browser fixtures were cleaned up.
