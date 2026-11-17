> Superseded layout: the two event maps have been combined into one daily comparison map. See [Combined attendance evidence map](ATTENDANCE_EVIDENCE_COMBINED_MAP.md) for the current layout, selection rules and verification. Historical evidence/privacy principles below still apply.

# Attendance Location Map

The existing protected attendance-details page now shows an Attendance Location map beside each original Time In/Time Out photograph. The HR monitoring table continues to load no interactive maps. No routes, database schema, GPS validation rules, or attendance write paths were changed.

## Recorded evidence

AttendanceLocationEvidence prepares a small presentation array from each AttendanceEvent. The device marker uses only that event's encrypted location latitude/longitude. The branch marker and circle use only branch_snapshot latitude, longitude, radius and branch_name recorded for that event. Current Branch configuration is never substituted. Distance and accuracy are the saved distance_meters and accuracy_meters; there is no display-distance recalculation or new geofence decision. A later branch edit, HR exception approval or manual correction cannot change original markers or the stored geofence result. The existing correction summary remains separate.

Device milliseconds are validated and formatted as Location Captured At (Device-Reported), while occurred_at is shown separately as Attendance Recorded At (Server-Recorded). Both display Asia/Manila. Invalid or absent device timestamps show Not available without modifying the original. Technical Location Details retain the original scalar values for inspection, including invalid coordinates; raw values never become invalid map markers. Zero latitude/longitude are valid. Purged employee locations are not exposed.

## Leaflet and OpenStreetMap

The Alpine attendanceLocationMap component reuses installed Leaflet 1.9.4, its CSS, and config/maps.php tile URL, attribution and maximum zoom. The default provider is OpenStreetMap. No geocoder or reverse-geocoding request is made. Only evidence-detail containers initialize maps, with mouse/touch zoom, keyboard controls, dragging/panning and a Fit recorded locations button. Zoom is capped for the initial view to avoid excessive closeups. A ResizeObserver invalidates size after layout changes or visibility changes. The initial map view is set before projecting circle bounds.

The teal (#087C68) branch pin shows the recorded branch name, coordinates and radius. The purple (#2F1B72) employee dot shows employee name, Time In/Time Out, reported coordinates, estimated accuracy and original server time. Distinct pin and dot shapes help distinguish coincident coordinates. Both markers are non-draggable. Popups use DOM textContent rather than interpolated HTML. The circle uses the recorded radius in metres, teal border and a subtle #10AB8C fill. Bounds include valid markers and the historical circle with padding. Marker clicks and map movement perform no evidence writes.

## Missing data and service failures

Absent/invalid employee coordinates show Attendance location data is unavailable and no employee marker. An available historical branch can still be shown and is labeled separately. Missing branch coordinates show an explanation; a valid employee point can still be displayed. Missing, nonpositive or invalid historical radius produces no circle. If both locations are unavailable no Leaflet instance or tile requests are created. Missing coordinates/radius never borrow current branch settings or example coordinates.

Tile failures show a compact inline message and automatically expand technical details. Coordinates, original statuses, distance/accuracy, timestamps and the existing protected photo remain accessible. No success/error toast appears for ordinary map movement. The map uses the existing no-store protected attendance response. JavaScript-disabled browsers retain readable details and an explanatory message.

## Authorization and privacy

The existing auth/auth.session middleware and AttendanceRecordPolicy::view protect the entire evidence page. HR can view company attendance evidence; employees can view their own evidence only; supervisors are denied by both the policy and existing leave-only middleware. There is no new public or JSON GPS endpoint. Photo routes and encryption remain unchanged. No navigator.geolocation calls or live tracking are used on evidence pages. No coordinates are added to broadcast payloads.

As with the existing branch maps, basemap tiles are fetched by the viewer's browser from the configured tile provider; the provider receives requested tile areas and ordinary request metadata. Employee names and detailed evidence are not sent to a geocoding service. Required attribution is retained.

## Layout, refresh and cleanup

Each event has a dedicated card so Time In and Time Out evidence cannot be mixed. Wide screens show photo and map in two balanced columns; smaller screens stack photo, map, metadata and technical details. GPS caveats distinguish device-reported evidence from verified presence. The existing review form, confirmation, original photo and retention states remain available.

Existing Reverb/Echo workspace refresh recognizes data-map-config: unchanged maps are preserved; changed or purged configurations destroy and replace the component. Destruction disconnects ResizeObserver and removes the Leaflet instance and its handlers. This avoids duplicate maps and preserves dirty review forms. The feature works on initial requests and polling fallback without Reverb.

## Files

- app/Services/AttendanceLocationEvidence.php (new)
- resources/views/components/attendance-location-map.blade.php (new)
- resources/js/attendance-location-map.js (new)
- resources/js/app.js
- resources/css/app.css
- resources/views/attendance/show.blade.php
- tests/Feature/AttendanceLocationMapTest.php (new)
- tests/Feature/AttendanceSecurityTest.php (heading assertion updated)
- tests/Browser/attendance-location-map.spec.js (new)
- docs/ATTENDANCE_LOCATION_MAP.md (new)
- docs/PHASE_4_IMPLEMENTATION.md (linked enhancement)

## Verification

PHP feature tests cover actual saved Time In and Time Out evidence, stored geometry/status/accuracy/distance, distinct Manila timestamps, historical settings after branch changes and correction summaries, missing/invalid/purged GPS and snapshots, unavailable radius, invalid device clocks, escaped employee/branch text, owner/HR access, denied cross-employee/supervisor/guest access and map-free monitoring rows.

The Edge browser regression uses existing synthetic-account fixtures and real attendance capture/submit endpoints with CSRF. It checks marker/circle rendering, popups, non-draggable controls, fit/zoom, protected photographs, responsive widths, unchanged map identity across an actual workspace refresh and deliberately blocked tiles. It makes live Geolocation API calls fail if the evidence page attempts one. Synthetic fixtures are cleaned through the existing cleanup path. These synthetic coordinates are test data only.

Run PHP tests with scripts/php enabled for GD. Run the browser test with npm exec -- playwright test tests/Browser/attendance-location-map.spec.js. OpenStreetMap availability is external; historical configuration cannot be reconstructed when a legacy record did not save it.

### Executed results

- Focused attendance/map PHP tests: 37 passed, 427 assertions.
- Complete PHP suite: 237 passed, 1,723 assertions (including leave, corrections and profile access).
- Edge attendance-location-map browser test: 1 passed. Checked real OpenStreetMap rendering, event-specific geometry and popups, read-only markers, fit/zoom, photograph decoding, 1440/768/390/320 px widths without horizontal overflow, actual workspace refresh preserving map identities, no live GPS requests, and forced tile failures with retained evidence.
- Production build, Pint, Blade compilation and git diff --check passed.
- Browser fixtures were cleaned up through the existing synthetic-account cleanup workflow.
