# Branch, Department and Assignment Management

Phase 3. HR has company-wide administrative access. Employees see only their own assigned branch through personal dashboard/profile; HR directory endpoints return 403 to Employees.

## Branch administration

Open `/hr/branches`. The list uses real MariaDB records with code/name/city search, active/inactive filter, allowlisted sorting and server pagination. Columns show code, name, complete address, current assigned-employee count, status and actions. No office names, addresses, coordinates or count of 27 is seeded or reported as actual data.

Create/edit fields: code, descriptive name, street address, city, province, optional postal code, interactive office location/geofence and controlled active/inactive status. Codes are unique and limited to letters/numbers/underscore/hyphen. Latitude is -90 to 90; longitude -180 to 180. Active branches require coordinates and radius together; inactive branches may leave all three empty. Radius is a whole number within configurable attendance policy bounds (defaults 1–100,000 meters).

HR selects/drags a Leaflet marker on OpenStreetMap and previews the geographic radius live. Create/Edit share the map component; Details is read-only. Advanced coordinate entry supports keyboard access. The map does not request camera/location permission. Phase 4 attendance uses the saved values for server-side GPS validation. See [map setup, behavior and verification](BRANCH_MAP_GEOFENCING.md).

HR detail view shows address, coordinates, radius, status and assignment count. Ordinary office edits do not move employees. Changing status from active to inactive requires an explicit confirmation and **zero current assignments for employment-active employees**. A deactivated login does not alone remove an employee's employment-active assignment; transfer or mark employment inactive first. This avoids stranding active employment at an inactive branch. Reactivation uses the edit status control. Rows are retained, not deleted.

## Map layout and navigation

Create/Edit now show Branch Location & Geofencing first and Branch Information underneath, within one form. The right-side panel includes HR-only Photon search, with the Search button beside its input. Choosing a result moves the marker, coordinates and circle while preserving radius, and offers available branch/address details under the safe autofill policy below. HR still saves explicitly. Invalid provider configuration disables search with a message, leaving manual selection available. See [configuration and tests](BRANCH_LOCATION_SEARCH.md) and the [LIMCOMA theme](LIMCOMA_BRAND_THEME.md).

At desktop widths of 1280px and above, the shared Branch Location & Geofencing component uses an approximately 70/30 map/details grid with a readable minimum panel width. The right-side Location Details panel contains Fit/Reset, manual coordinate editing, vertical latitude/longitude/radius inputs, help, status and errors. Smaller screens stack the map above the panel. Desktop wheel scrolling over the map zooms directly without Ctrl; scrolling outside it moves the page. Standard zoom buttons and mobile touch/panning controls remain available. Existing marker, circle, persistence and backend validation behavior is unchanged. The ResizeObserver continues to refresh Leaflet sizing after sidebar and viewport changes. See [layout verification and modified files](BRANCH_MAP_GEOFENCING.md#layout-and-wheel-zoom-enhancement-2026-11-16).

## Map-first form and safe automatic information fill-up

The shared Create/Edit form places the map and Location Details above the full-width Branch Information section. Search and its button stay on one row, including at 320px; Save/Cancel remain at the bottom. Selection updates the existing coordinate inputs and geographic circle, preserving radius, Branch Code and Branch Status.

| Branch field | Photon mapping |
| --- | --- |
| Branch Name | `name`, only for classified offices, shops, named buildings, crafts, healthcare or selected named institutional amenities. Generic cities, roads and barangays never supply a branch name. |
| Street Address | House number + street, then district/locality, without repeating city/province/country. A highway result's name can supply its street. A house number alone is insufficient. |
| City | `city`, then explicit `town` or `municipality` if supplied. No district-to-city guessing. |
| Province | `state`; known region-level labels such as CALABARZON use a supplied `county` instead. Missing province data stays for HR review. |
| Postal Code | `postcode` when supplied. No invented default. |
| Branch Code / Status | Never provided or changed by Photon. |

Empty supported fields fill automatically on Create. If any suggestion differs from a nonempty field, an inline keyboard-focusable confirmation offers **Apply Details** or **Keep Existing Details**. Edit always requires explicit application of suggested details. The map changes in either case; keeping details retains all existing text. Applying updates only fields present in that result, retaining missing name/address/postal information. HR can edit every suggested value before saving. Values are capped at existing field limits and remain subject to Laravel validation, including unique Branch Code.

The single selection handler applies whitelisted suggestions to the actual form controls and dispatches input/change events, so displayed and submitted values match. Reset dismisses pending suggestions and clears only map selection; existing branch information is preserved. Clicks/drags do not reverse-geocode or overwrite branch text. Search failure makes no form changes. No record is created or updated before Save, and Cancel leaves the database unchanged. No schema, assignment, authorization or attendance-calculation changes were needed.

Changed files in this enhancement: `resources/views/hr/branches/form.blade.php`, `resources/views/components/branch-location-map.blade.php`, `resources/js/branch-location-map.js`, `app/Services/GeocodingService.php`, `tests/Feature/BranchLocationSearchTest.php`, `tests/Browser/branch-autofill.spec.js`, this guide and `docs/BRANCH_LOCATION_SEARCH.md`.

Provider suggestions remain unverified company data. Establishments without suitable classification may require manual Branch Name entry; incomplete or ambiguous administrative information must be reviewed by HR. Physical Android/iPhone checks remain pending.

Verification for this enhancement (2026-11-16): **44 affected backend tests passed (240 assertions)**, including branch/geofence validation, unique codes, HR authorization and attendance snapshot integration. **All 7 affected Edge browser tests passed**, covering the new autofill scenario, existing map/search controls, real MariaDB Create/Edit persistence and employee registration/activation. The final autofill test was rerun after its last assertion update and passed (1/1). Build, Pint, Prettier and whitespace checks passed. The mobile screenshot was visually inspected. The full backend suite and physical-device tests were not rerun for this scoped enhancement; no final affected test failures remain. Photon responses were mocked for deterministic address-mapping tests. No later phase was started.

## Company-wide departments and positions


`/hr/departments` supports search, status filter, sorting, pagination, create/edit and status management. Departments are company-wide so one department can span multiple branches; no unnecessary branch FK is stored. Codes are unique; new assignments require active departments. Department deactivation requires confirmation and no employment-active employees. There is no ordinary deletion endpoint.

Position titles are maintained by HR in employee employment forms, matching the requested `position_title` field. A separate position taxonomy/CRUD module is not assumed.

## Primary branch assignment and transfer

Every newly registered employee requires one real, active primary branch and an active department. The employee form lists only active choices; server validation repeats those checks, then the transaction rechecks current records to close validation/write races.

Assignments store `employee_id`, `branch_id`, `is_primary`, UTC `effective_from` and nullable `effective_until`. Phase 3 supports one immediate current primary assignment, not future-dated/multiple primary offices or secondary assignments through the UI.

On transfer:

1. Validate HR access and chosen active branch/department.
2. Acquire the administrative transaction lock and recheck the actor and referenced rows.
3. Close the old open assignment at server time.
4. Add the new primary assignment at server time.
5. Record previous/new branch IDs in the audit trail and commit atomically.

The unique generated `current_employee_id` is populated only for open primary rows; its unique index prevents two current primary assignments even if a caller bypasses form validation. Closed history remains attached to the employee and is visible in Employee Details. Historical intervals are interpreted as `[effective_from, effective_until)`.

The common administrative lock serializes transfer with branch/department status changes. It is deliberately short; mail delivery occurs after commit. This is suitable for infrequent centralized HR changes, not a future high-volume biometric inference lock.

## Privacy and future work

Employee assignment history is business data and is HR-restricted. Audit metadata contains IDs/change-field names, not complete personal profiles or precise employee location. Branch office coordinates are official configuration and must not be mistaken for captured employee GPS data.

Phase 4 attendance snapshots the effective assignment/geofence/rules used at event time rather than relabeling historical punches after a transfer. Branch configuration changes now audit previous/current office coordinates and radius alongside actor/subject/timestamp. Shift scheduling remains a later phase. Production data retention and archival/deletion rules require company/DPO approval.
