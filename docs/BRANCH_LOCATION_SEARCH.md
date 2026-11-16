# HR branch location search — Photon

Updated 2026-11-16. Photon replaces Geoapify in the active search flow. The previous integration required a key that was not configured, so the Search button remained disabled. Photon needs no API key.

## Configuration

```dotenv
GEOCODING_PROVIDER=photon
PHOTON_BASE_URL=https://photon.komoot.io
```

The base URL is centralized in `services.photon.base_url`; the adapter appends `/api/`. Defaults enable the public demo without additional configuration. Remove an old `GEOCODING_PROVIDER=geoapify` override, if present, and run `php artisan config:clear` locally. A trusted deployment can point the base URL to its own HTTPS Photon instance. URLs must be valid HTTPS without user/password, query or fragment. Provider URLs cannot be supplied through the search request. Redirects are not followed.

Geoapify's HTTP calls, key configuration and frontend attribution have been removed. No key is copied into source, browser data, responses or documentation. Existing private environment secrets were not read or exposed. An invalid/empty URL or unsupported provider disables execution with a safe configuration message; the input stays editable and manual map selection remains usable.

The public Photon demonstration server may throttle, block or be unavailable, and offers no availability guarantee. Use a suitable self-hosted instance for production requirements. No automatic provider fallback or autocomplete is implemented. See [Photon documentation and demo policy](https://github.com/komoot/photon).

## Request and normalization

The existing route is reused: **POST /hr/branch-location-search**, named `hr.branches.location-search`, with JSON `query`. This preserves the application convention, HR authentication/session middleware, explicit HR authorization and CSRF protection. Guests and Employees cannot use it as a public proxy.

Queries are trimmed, whitespace-normalized and constrained to 3–200 characters including a letter/number. Search/Enter triggers one request; typing alone does not. Laravel encodes a GET to the configured Photon `/api/` with `q`, `limit=5`, and `lang=en`. The server identifies itself as LIMCOMA-Attendance/1.0. Connection/total timeouts are 3/8 seconds, and the browser aborts after 12 seconds. There are no immediate automatic retries.

Photon returns a GeoJSON FeatureCollection. Only Point geometry with numeric, finite coordinates and valid ranges is accepted. **GeoJSON [longitude, latitude] becomes the UI's latitude/longitude fields**, not the other way around. Labels combine available name, house number, street, city, county, state, postcode and country values, omitting empty/duplicate parts. Missing geographic data is not invented. Invalid geometry/coordinates and nameless/addressless results are discarded.

The result contract is `{label, latitude, longitude, details}`, with at most five entries. `details` contains only supported nonempty branch-name/address/city/province/postal-code suggestions. The cache namespace was updated so older cached results without details are not reused. No raw provider payload or unsafe HTML is inserted into the interface. All labels use Alpine text rendering.

## UI and persistence

The existing LIMCOMA theme, desktop 70/30 layout and stacked mobile layout are unchanged. Location Details contains Search Location, Search/results, Fit/Reset, manual coordinates, radius, help/status/errors. Loading shows **Searching locations...** and disables duplicate searches.

HR chooses a result explicitly. The existing map moves to it, the single marker and circle center follow, and coordinate inputs update to seven decimal places. **The configured radius is retained**, and the same geographic circle still resizes immediately through setRadius. Available branch details are applied immediately without confirmation, following the field mapping and missing-value policy in [Branch Management](BRANCH_MANAGEMENT.md#map-first-form-and-safe-automatic-information-fill-up); search never saves automatically. HR can refine the point by clicking, dragging or manually entering coordinates, then Save. Cancel preserves the saved location and information.

Create/Edit reuse this one component. Read-only Details has no search editor. Wheel zoom without Ctrl, +/- controls, Fit Geofence, Reset, saved-value loading, backend validation, HR permissions and employee assignments remain intact. Search errors never clear the selected location/radius. Attendance continues using the same saved branch columns and historical snapshots; no schema change or new dependency was needed.

## Errors and limits

- Empty/invalid input: **Enter a valid location to search.**
- No matches: **No matching locations found. Try another address or select a location manually on the map.**
- Provider unavailable/malformed: safe 503 feedback and manual fallback.
- Connection failure/timeout: **Location search timed out. Please try again.** (504).
- Provider or application throttling: **Location search is temporarily limited. Please try again later.** (429), with no retry loop.
- Invalid deployment configuration: safe 503 configuration message.
- Invalid selected coordinates: no marker movement.

The HR endpoint permits 10 requests/minute/user. A shared provider budget permits 20 uncached requests/minute across HR users. Normalized repeated queries, including empty results, are cached for ten minutes with a short per-query lock. Cache keys include the provider/base URL and query, isolating old Geoapify results. Use a shared cache across production instances. These safeguards do not imply a reserved quota or service guarantee from the demo operator.

## Exact files modified in the Photon replacement

- `app/Services/GeocodingService.php`
- `app/Http/Controllers/HR/BranchLocationSearchController.php`
- `config/maps.php`
- `config/services.php`
- `.env.example`
- `resources/views/components/branch-location-map.blade.php`
- `resources/js/branch-location-map.js`
- `tests/Feature/BranchLocationSearchTest.php`
- `tests/Browser/location-search.spec.js`
- `docs/BRANCH_LOCATION_SEARCH.md`
- `docs/BRANCH_MAP_GEOFENCING.md`
- `docs/BRANCH_MANAGEMENT.md`
- `docs/LIMCOMA_BRAND_THEME.md` (remove obsolete key requirement)
- `README.md`

## Verification

```powershell
cd C:\xampp\htdocs\Attendance_System
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task serve
# In a separate terminal, with XAMPP MariaDB running:
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task test
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task browser
```

Focused checks with project PHP/GD and Node on PATH:

```powershell
php artisan test --compact tests/Feature/BranchLocationSearchTest.php
npm run test:browser -- tests/Browser/location-search.spec.js
```

Backend tests fake Photon GeoJSON and prohibit stray HTTP. They cover coordinate ordering, unsupported/invalid geometry, caching, no matches, malformed/error responses, timeouts, provider 429, HR authorization, invalid configuration and application limits. Browser tests now use genuinely enabled Photon configuration without intercepting HTML to bypass a missing key; only search result responses are mocked for deterministic tests. Real Laravel authentication/CSRF and MariaDB branch creation/edit/reopen remain exercised.

A live authenticated browser check also returned **HTTP 200 with five results** for Lipa City, Batangas. Selecting the first result set latitude **13.9743635**, longitude **121.1443500**, while retaining the **70-meter** radius. This was a public-index result, not a verified LIMCOMA office location; no branch record was saved by that smoke check. Temporary fixture accounts were cleaned. Public index coverage for every street/office is not guaranteed.

Executed results for this replacement:

- Focused backend search suite: **18 passed, 76 assertions**.
- Full backend suite: **129 passed, 831 assertions**.
- Full Edge browser run: **8 passed, 1 failed**. All map/search, HR/Employee branding, branch persistence and navigation checks passed. The unchanged camera test initially sampled track state before asynchronous cleanup completed; its separate rerun **passed** (1/1), including real Time In/Out and HR review. No attendance code was changed.
- Focused location-search browser run: **2 passed**.
- Live Photon endpoint and authenticated browser search: **HTTP 200**, five results; result selection updated coordinates and retained the 70-meter radius without a key.
- Asset build, Pint, Prettier and whitespace checks: passed. The provider-budget test fixture was corrected to use the installed Laravel rate limiter's increment method when preloading multiple attempts.

The remaining test limitation is an observed timing-sensitive camera assertion in the existing browser suite; its rerun passed. Physical Android/iPhone testing and production-hosted Photon availability remain unverified. No further development phase was started.

## Automatic selection and clean search panel

Clicking one result immediately updates the map, existing marker/circle and latitude/longitude, then fills the available normalized Branch Information values and closes results. Both Create and Edit use the same handler. No Apply Details, Keep Existing Details, selection confirmation, Selected paragraph, approximate-search paragraph or selection toast remains. Search stays usable and keyboard focus returns to its input. Error/no-result feedback is visible only when needed; map controls and the 70/30 desktop/stacked-mobile layout remain unchanged.

Field mapping remains server-normalized: classified establishment name only; house number/street with district/locality for address; city/town/municipality for City; province-level state or supplied county for a known region label; postcode when present. No country/province is unnecessarily duplicated in Street Address. Code, Status and radius are never changed by selected address data.

Reliable new values replace existing values. During the current page session, automatic field values are tracked separately from manual edits. Missing fields clear unchanged values from an earlier selection, preventing stale mixed-location suggestions. Manual corrections and values loaded from the saved record are retained when the new result supplies no replacement. No data is invented. Visible controls remain the submitted form state; input/change events support existing reactive listeners. Existing Laravel required-field and uniqueness validation remains authoritative.

Old request responses are ignored after a newer selection; pending requests are aborted and their timers cleared. Selection does not write to MariaDB. Save is still explicit; Cancel or navigation away leaves stored data unchanged. Provider suggestions are approximate, not verified office coordinates; HR can adjust the marker and correct the editable fields before saving.

Photon credit has moved into Leaflet's map attribution control. The existing linked Leaflet/OpenStreetMap copyright attribution remains visible there; no provider or copyright credit was discarded. Attribution is trusted static markup; result labels and field text never become HTML.

Executed checks for this update: **44 backend tests passed (240 assertions)** and **6 browser tests passed (47.7 seconds)** across branch autofill, map behavior and Photon workflows. Build, Pint, Prettier and whitespace checks passed; the 320px mobile screenshot was visually reviewed. Deterministic mocked provider responses were used. The existing backend normalizer, request authorization, provider limits, cache and attendance rules were unchanged. Live public-provider availability and physical-device testing remain unverified for this update.
