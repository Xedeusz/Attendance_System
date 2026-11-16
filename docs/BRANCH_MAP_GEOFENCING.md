# Branch maps and interactive geofencing

Implemented 2026-11-16 as a Branch Management enhancement to Phases 3–4. No later phase was started.

## Technology and integration

Leaflet **1.9.4** is pinned in npm and bundled by Vite with its CSS and marker/retina/shadow images. Blade renders a reusable `branch-location-map` component; the existing Alpine application registers `branchLocationMap`. No external JavaScript CDN, API key, geocoder, browser location permission or new database table is required. The administrative map does not request camera/GPS permissions.

`config/maps.php` supplies the tile URL, attribution, maximum zoom and initial navigation view. Optional environment variables:

```dotenv
MAP_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
MAP_TILE_ATTRIBUTION="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors"
MAP_MAX_ZOOM=19
MAP_DEFAULT_LATITUDE=12.8797
MAP_DEFAULT_LONGITUDE=121.7740
MAP_DEFAULT_ZOOM=6
ATTENDANCE_GEOFENCE_MIN_RADIUS_METERS=1
ATTENDANCE_GEOFENCE_MAX_RADIUS_METERS=100000
```

The radius defaults retain the existing 1–100,000 meter safety range; they do not assert that this entire range is company-approved. Set the permitted positive integer bounds in attendance configuration for company policy. The same bounds are passed to the UI and applied by `BranchRequest`. After changing environment configuration, run `php artisan config:clear` locally, or rebuild the deployment's config cache.

## Create, select and edit

1. HR opens `/hr/branches/create` or an existing branch's Edit action.
2. A new branch starts with a Philippines navigation view and **empty coordinates**. The initial center is never a saved office location.
3. Navigate using zoom/pan, then click/tap to place the one office marker. Repeated clicks move it. Longitude is normalized and selected coordinates are rounded to seven decimal places.
4. Drag the marker to refine the position. Coordinates and circle center follow immediately; the radius stays unchanged.
5. Enter whole meters in **Geofencing Radius (Meters)**. `L.circle` renders a geographic radius; `setRadius` updates the existing circle immediately. Invalid/empty/out-of-policy values remove the circle and show validation feedback; they are never previewed as valid.
6. **Fit Geofence to View** fits circle bounds with padding and a maximum fit zoom of 18. Typing a radius does not change the zoom or recreate the map.
7. **Reset Location** removes the marker/circle and clears coordinates while retaining radius and unrelated fields. Clear the radius too if saving an inactive branch as completely unconfigured.
8. **Save branch** submits the same coordinate/radius state rendered by the map. **Cancel** navigates away without any write.

Saved values initialize Edit and Details. Failed server validation restores old form values, including deliberately emptied values, ahead of database defaults. Older unconfigured records display a configuration prompt without inventing a location. Details supports navigation and fitting, but has no draggable marker, click-to-change handler, editable fields or reset action.

Optional Photon location search is now available in the right-hand panel; see [search configuration](BRANCH_LOCATION_SEARCH.md). Manual map navigation works when Photon is unavailable. The advanced coordinate checkbox provides keyboard entry and synchronized map updates; ordinary map selection requires no coordinate typing. Invalid advanced coordinates remove the preview and are rejected on submission. Values remain in visible input fields if tiles fail. Without JavaScript, the ordinary fields remain editable and server validation still applies.

## Database and validation

No migration is needed. The existing `branches.latitude`, `branches.longitude` (DECIMAL(10,7)) and unsigned nullable `geofence_radius_meters` are reused. The existing precision accommodates the longitude range, so no widening or duplicate columns are necessary.

- Active branches require latitude, longitude and radius together.
- Inactive branches can leave all three empty, or supply a complete valid geofence; partial configurations are rejected.
- Latitude is numeric −90…90; longitude numeric −180…180.
- Radius must be a whole positive number within configured bounds.
- Existing active records without configuration are readable, but must be configured before saving them as active again. No data is silently backfilled.

The existing administrative transaction saves all branch values and the audit together. Assignment records and existing deactivation safeguards remain intact. Existing branches are not re-created and no real branch data is seeded.

## Authorization and audit

Existing authenticated HR middleware protects all branch directory/create/detail/edit/write routes, and `BranchRequest` independently requires HR. Employees receive 403 and cannot configure geofences. CSRF remains enabled. The two-role system and account lifecycle are unchanged.

`branch.created` records a null previous configuration and the selected current configuration. `branch.updated`/`branch.deactivated` include previous/current latitude, longitude and radius whenever a geofence field changes. Existing audit rows are retained. The existing audit record also supplies branch subject ID, actor HR ID and server change timestamp. This is official office configuration, not employee GPS evidence.

## Attendance and history

The existing `AttendanceService` resolves the employee's current authorized assignment and snapshots these **same branch columns**. Both Time In and Time Out continue to use the backend Haversine calculation, freshness/accuracy limits and conservative boundary policy in `GeofencingService`. The map never decides attendance eligibility.

Saving a geofence invalidates an outstanding capture whose snapshot differs; the employee must start a new capture. Future captures use the newly saved configuration. Each historical attendance event already stores its original branch/policy snapshot, geofence result and distance. These records are not changed or reclassified by Branch Management, so no history migration is required.

## Responsive behavior and errors

At viewport widths of 1280px and above, a Tailwind grid places the map on the left and a single Location Details panel on the right using `minmax(0,7fr) minmax(280px,3fr)` with a 24px gap. The minimum panel width keeps controls readable, so the split approaches 70/30 as space allows. All actions, vertically stacked coordinates/radius, explanations, dynamic status and errors live in this panel, without duplicate controls. The shared component applies to Create/Edit and the read-only Details preview. Below 1280px, the map appears above the panel. Map height is 360px on mobile, 400px from 640px, and at least 540px on desktop, stretching to align with the panel. A ResizeObserver calls `invalidateSize` after layout/visibility changes. Alpine teardown disconnects the observer, removes the form listener and destroys the Leaflet map/listeners.

Leaflet uses `scrollWheelZoom: true`: wheel up over the map zooms in, and wheel down zooms out around the pointer without Ctrl. The standard +/− buttons and provider maximum zoom remain unchanged. Wheel scrolling outside the map continues to scroll the page; no global wheel listener is installed. On coarse-pointer devices, one-finger map panning starts disabled. Taps still select a location; **Enable map panning** permits drag navigation and **Enable page scrolling** restores scrolling. Zoom buttons and draggable marker remain available. Controls use existing focus/label styles, coordinates support keyboard entry, and errors/radius labels have live announcements.

Tile failures show an explicit error and **Retry Map**. Selection, marker, circle and unsaved form values remain usable without base tiles. Retrying redraws tiles without resetting form state. Initialization failure offers the same retry action. A missing active location blocks save with a prompt; backend validation remains authoritative even if client checks are bypassed. There is no success notification before a successful database save.

## OpenStreetMap usage

The default uses the official HTTPS endpoint and visible linked attribution. Tile images explicitly use `strict-origin-when-cross-origin` referrers so OSM receives the application origin despite the app's default same-origin document policy. Browser caching is left intact. No tile downloads, offline cache, unrestricted prefetch or search autocomplete is implemented; only viewport tiles are requested, with zero extra tile buffer.

Use an appropriate production tile provider if expected traffic, support or service guarantees exceed the public service's best-effort capacity. Set both URL and provider-required attribution in configuration; do not hide attribution. Provider values are trusted deployment configuration, never user input. See the [OSM tile policy](https://operations.osmfoundation.org/policies/tiles/) and [Leaflet 1.9.4 API](https://leafletjs.com/reference-1.9.4.html).

## Files

| Files | Responsibility |
| --- | --- |
| `config/maps.php`, `config/attendance.php` | Tile/default view and shared radius policy |
| `package.json`, `package-lock.json` | Pinned Leaflet dependency |
| `resources/js/branch-location-map.js`, `resources/js/app.js` | Alpine map state, Leaflet lifecycle and registration |
| `resources/css/app.css` | Responsive map sizing and touch behavior |
| `resources/views/components/branch-location-map.blade.php` | Reusable editor/read-only map |
| `resources/views/hr/branches/form.blade.php`, `show.blade.php` | Create/Edit integration and Details preview |
| `app/Http/Requests/BranchRequest.php` | Complete geofence and configurable policy validation |
| `app/Http/Controllers/HR/BranchController.php` | Transactional before/after audit metadata |
| `tests/Feature/BranchGeofenceConfigurationTest.php`, `BranchDepartmentTest.php` | Backend regressions and updated valid active-branch fixture |
| `tests/Browser/branch-map.spec.js`, `fixture.php`, `phase3.spec.js` | Browser interaction, narrowly scoped fixture cleanup and an updated valid active-branch creation workflow |
| This guide, `README.md`, `docs/BRANCH_MANAGEMENT.md`, `docs/PHASE_3_IMPLEMENTATION.md`, `docs/PHASE_4_IMPLEMENTATION.md`, `docs/GPS_GEOFENCING.md`, `docs/DATABASE_SCHEMA.md` | Current operation and integration documentation |

## Local verification and troubleshooting

Start XAMPP MariaDB and use the existing `.env`; never reset the real database to test this enhancement. Install npm dependencies after pulling the lockfile and rebuild assets. The project helper finds its local Node installation and loads project PHP/GD configuration:

```powershell
cd C:\xampp\htdocs\Attendance_System
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task serve
# In another terminal:
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task test
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task browser
```

Focused checks with PHP/Node available on PATH:

```powershell
php artisan test --compact tests/Feature/BranchGeofenceConfigurationTest.php tests/Feature/BranchDepartmentTest.php
npm run test:browser -- tests/Browser/branch-map.spec.js
php vendor/bin/pint --dirty --format agent
```

If the UI is stale or Vite assets are missing, run the build task (or run the assets task in a second terminal for development). If tiles fail, check connectivity/provider access and use Retry Map; form values are preserved. If saving fails, read field validation errors, confirm active configuration is complete, and check the configured radius limits. A blank preview without a marker can mean no explicit location has been selected. Browser tests require installed Edge and the local server; they create uniquely marked synthetic HR/branch records and clean up only their own fixtures. Physical Android Chrome and iPhone Safari testing remains a manual device check.

## Verification results

Executed on 2026-11-16:

| Check | Actual result |
| --- | --- |
| `php artisan test --compact` with project PHP/GD settings | **111 passed, 755 assertions**; SQLite in-memory test database |
| `npm run test:browser` | **6 passed** in installed headless Edge against local Laravel/MariaDB, including both new map tests and existing account/navigation/live-camera attendance/review workflows |
| `npm run build` | Passed; Leaflet CSS, marker images and application assets bundled |
| `php vendor/bin/pint --dirty --format agent` | Passed |
| `npm run format:check` | Passed |
| `git diff --check` | Passed |

The initial full browser attempt exposed that the previously started server lacked project-scoped GD. Restarting it using `scripts/dev.ps1 -Task serve` resolved the existing attendance submission failure; the final complete browser run passed. During development, test assertions were also corrected for database-hydrated attributes and page scrolling. No known test failure remains.

| Requested acceptance checks | Executed coverage |
| --- | --- |
| MAP-001–006 | Blank new selection, visible attribution, click marker, real drag, coordinate/circle synchronization, immediate 100→200→50 meter circle scaling without map replacement |
| MAP-007–010 | Real MariaDB creation/edit/reopen; backend persistence and before/after HR audit assertions; saved radius updates preserve other fields |
| MAP-011–013 | Invalid/empty/zero/negative/fractional/scientific/decimal-notation/out-of-policy radius rejection, incomplete configuration rejection, guest/Employee denial without writes |
| MAP-014 | Actual Time In/Time Out backend capture/submit flow; saved changed center/radius used; old position outside, new center accepted; stale capture rejected |
| MAP-015 | 320/390/768/1440px overflow checks, emulated touch selection/pan controls, responsive map height; desktop/mobile screenshots visually inspected |
| MAP-016–017 | Cancel preserves saved values; validation restores unsaved preview; older/inactive unconfigured branch renders and can be configured |
| MAP-018 | Original attendance event snapshot/result preserved after geofence change; future Time Out uses new radius; old/new office coordinates/radius are audited |
| Tile outage | Forced tile-network failure, manual selection still works, Retry Map loads real OSM tiles and preserves marker |

Screenshots: `test-results/screenshots/branch-map-desktop.png` and `branch-map-mobile.png` (ignored local artifacts). Browser fixtures were cleaned by the tests. The subsequent [location-search enhancement](BRANCH_LOCATION_SEARCH.md) adds key-free search; manual navigation and advanced coordinate entry remain available. **Pending manual checks:** physical Android Chrome/iPhone Safari marker dragging, zoom/scroll interactions and network behavior. No physical-device or production-service guarantee is claimed. Choose a production tile provider according to expected usage and service requirements. No further development phase was started.

## Layout and wheel-zoom enhancement (2026-11-16)

Changed files for this follow-up only:

- `resources/views/components/branch-location-map.blade.php` — responsive map/details grid, one right-hand control panel, vertical inputs and status/error placement.
- `resources/css/app.css` — remove the old fixed map-height declaration so responsive utility heights apply.
- `resources/js/branch-location-map.js` — enable Leaflet's built-in direct wheel zoom on the existing instance.
- `tests/Browser/branch-map.spec.js` — add layout, wheel-in/out, outside-map scrolling, sidebar resize and breakpoint regressions.
- `docs/BRANCH_MAP_GEOFENCING.md` and `docs/BRANCH_MANAGEMENT.md` — update operation and verification guidance.

The existing ResizeObserver still invalidates the map when its container changes, including sidebar collapse/expansion; it never recreates the map. Click placement, draggable marker, manual editing, real-time meter-radius circle, Fit/Reset, saved-value loading, validation, HR authorization, MariaDB persistence and attendance calculation/snapshots are preserved. No backend or database changes, new dependencies or duplicate component were introduced.

Executed verification for this follow-up:

- `npm run build`: passed; updated assets are built for the running local server.
- `php artisan test --compact tests/Feature/BranchGeofenceConfigurationTest.php tests/Feature/BranchDepartmentTest.php`: **25 passed, 159 assertions**.
- `npm run test:browser`: **7 passed**, including all three map tests and existing account/navigation/live attendance workflows against MariaDB.
- `php vendor/bin/pint --dirty --format agent`, `npm run format:check`, and `git diff --check`: passed.
- Desktop screenshots were visually inspected. Automated checks covered the 70/30 layout, vertical controls, wheel zoom in/out without Ctrl, unchanged coordinates through zoom, page scrolling outside the map, sidebar resizing, and stacked layouts at 1024/768/390/320px. Existing tests covered marker placement/dragging, radius resizing, Fit/Reset, validation recovery, real saves/reopening and mobile touch controls. Screenshots include `test-results/screenshots/branch-map-two-column.png` and `branch-map-desktop.png`.

The initial wheel test sampled an animated SVG midway through zoom; waiting for the transition before the next wheel action corrected the test. The final full browser run has no failures. No unresolved UI/mapping defect was observed. Physical Android/iPhone testing remains pending; desktop and mobile emulation do not replace device testing. The full backend suite was not rerun for this frontend-only follow-up; the affected backend tests above were executed.

