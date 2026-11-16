# HR dashboard redesign

The HR dashboard at `hr.dashboard` is a server-rendered workforce operations overview. It retains all existing company, attendance, recent employee, branch and pending-account information. It introduces no dependencies, migrations, roles or business rules.

## Design and hierarchy

1. **Workforce Overview:** concise purpose, current Asia/Manila date/timezone, Register employee and Monitor attendance actions.
2. **Company overview:** four primary KPIs for total employees, active branches, departments and pending activations. Active employee accounts are supporting information within Total employees.
3. **Today's Attendance:** prominent Currently clocked in, Completed sessions today and Pending HR review; five smaller values retain Time Ins, Time Outs, open sessions over 24 hours, rejected submissions and geofence exceptions.
4. **Attendance Activity / Requires Attention:** a seven-day actual-event chart beside actionable pending reviews, long-open sessions and pending activations. Zero-count attention rows are omitted; All caught up is displayed when applicable.
5. **Recently Added Employees:** five newest employees, with name/number, department, current primary branch, account state and local date added.
6. **Additional workforce details:** branch assignment counts/status, five newest pending activations and compact links to departments, HR accounts, audit logs and My Workday.

White 14px cards, light #F8FAFC workspace surfaces, #E2E8F0 borders, subtle shadows and existing Inter typography keep the view compact. Deep Purple #2F1B72 anchors headings/actions; Dark Purple #241454, Teal #10AB8C and accessible Dark Teal #087C68 retain LIMCOMA branding. Amber/red accents communicate pending or exceptional activity alongside meaningful text. Zero values are not automatically styled as successes. The purple sidebar, approved brand component, teal active navigation, mobile drawer, My Workday, account dropdown and Notifications link remain functional. The topbar is more compact, with readable name/role details; Development workspace is visible only in local/testing environments.

## Data definitions and accuracy

`DashboardController::hr` retains the original company aggregates and limited, eager-loaded employee/assignment/department lists. It supplies the configured business date and the new activity series from `AttendanceQueries::activity`. No queries run in the Blade template.

Existing `AttendanceQueries::statistics` definitions are unchanged:

- Total employees: all employee records; active/pending employee accounts: employee records with the corresponding user account status.
- Active branches: branches with active status. Departments: all department records.
- Today's Time Ins/Outs: events within today's business-day boundaries, with location_validated or exception_approved verification status.
- Currently clocked in: open attendance records across all dates.
- Completed sessions today: completed records whose Time Out occurred within today's business-day boundaries.
- Pending HR review: events with pending review status across all dates.
- Open over 24 hours: open records older than the configured warning-hours threshold (currently 24).
- Rejected submissions: all-time rejected review events.
- Geofence exceptions today: today's events whose geofence status differs from within_geofence, regardless of later approval.

Counts represent different entities and can overlap. A pending event count is not a count of absent employees or necessarily a count of distinct sessions. No absence, scheduling, growth or performance percentage is inferred. Pending activations do not imply an email was delivered.

Attention links use existing supported filters: pending_review verification, open session status, and pending_activation account status. The long-open count links to the existing all-open-session list for inspection; an exact age-filter feature was not invented. The dashboard states this limitation next to the panel.

## Seven-day visualization

The lightweight HTML/CSS grouped bar chart uses accepted Time In/Out **event timestamps**, not work_date or session creation dates. Teal means Time Ins; purple means Time Outs. Each day has an exact local date, shared count scale from zero to the maximum, keyboard/hover tooltips and a collapsible data table. Zero values have zero-height bars. The no-data state says No attendance activity recorded yet rather than showing a fabricated trend.

`AttendanceQueries::activity` issues one conditional aggregate query for 14 counts. Local midnight boundaries for the last seven calendar dates, including today, are converted individually to UTC for the stored timestamps. Half-open intervals include midnight and exclude the next midnight. Accepted statuses match existing daily punch statistics; pending and rejected events are excluded. No event/evidence/location rows are loaded into the browser. The SQL uses bound date/type values and portable CASE counts, verified with SQLite tests and live MariaDB browser workflows. The chart needs no JavaScript library or additional asynchronous requests/loading placeholders.

## Responsive behavior and accessibility

At wide desktop widths the company KPIs use four columns, attendance uses three primary columns and activity/attention use two unequal columns. Header tools wrap at intermediate widths. Company KPIs become two columns on tablet/mobile; activity, attention and detail panels stack. At narrow widths primary attendance metrics become horizontal rows and secondary metrics use two columns. Tables scroll inside focusable labeled regions rather than overflowing the page. The existing keyboard drawer, Escape handling, focus indicators, semantic headings and skip link remain available.

Chart tooltips appear on keyboard focus as well as hover, with edge alignment to avoid clipping. Text labels, legends and the exact-data table provide non-color alternatives. There are no new dashboard animations; existing reduced-motion behavior remains supported. Mobile screenshots cover 320/360/375/390/430px and tablet 768px; desktop screenshots cover 1440px. Physical-device and Safari checks remain manual.

## Files changed

- `app/Http/Controllers/DashboardController.php`
- `app/Services/AttendanceQueries.php`
- `resources/views/hr/dashboard.blade.php`
- `resources/views/components/topbar.blade.php`
- `resources/css/app.css`
- `tests/Feature/HrDashboardTest.php`
- `tests/Feature/Phase3AuthorizationTest.php`
- `tests/Browser/foundation.spec.js`
- `tests/Browser/attendance.spec.js`
- `docs/HR_DASHBOARD_REDESIGN.md`
- `README.md`

## Verification

The new feature tests cover exact empty/populated company and attendance aggregates, recent and pending records, production-only indicator suppression, accepted-versus-pending chart series and Asia/Manila midnight/window boundaries using real attendance submissions/review. Existing authorization tests cover Employee denial and HR access; no authorization path changed.

Browser tests cover four KPIs/eight attendance values, real saved-event chart/table/tooltips, mobile overflow, header actions, sidebar/drawer, My Workday, Notifications and existing camera/GPS, CRUD, geocoding and SweetAlert workflows. Synthetic fixtures are scoped and cleaned up. Dashboard screenshots are stored under `test-results/screenshots/workforce-*`; a desktop overview is also captured by the foundation test.

Commands (with the local Node toolchain and configured XAMPP PHP):

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
npm.cmd run format:check
npm.cmd run build
npm.cmd run test:browser
```

Executed results: **134 backend tests passed (882 assertions)** and **all 14 browser tests passed (2.7 minutes)**. After final screenshot/refined responsive-test changes, the three affected attendance/foundation browser tests passed again (1 minute). Production build, Pint, Prettier and whitespace checks passed. Desktop and 320px mobile screenshots were visually reviewed; tablet and other mobile widths were also captured and checked for overflow. Physical Android/iPhone, Safari and assistive-technology testing are not claimed. No database or attendance-policy change is required to use this dashboard.
