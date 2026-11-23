# LIMCOMA Attendance Reports

HR can open **Attendance Management → Attendance Reports**, filter by dates, branch, department and employee, and export the complete filtered dataset. The page shows six metrics across all matching days and 25 daily rows per page. The read-only detail page preserves report filters, sort and page number. The former `/reports` link redirects to the new module.

## Existing rules and report behavior

- `AttendancePolicyResolver::forPeriod()` resolves dated primary branch assignments, employee/department/branch/company schedule precedence, schedule exceptions, applicable holidays and final approved leave in the attendance timezone. The report uses the current department field because departments are not versioned. If a dated primary assignment is unavailable, the report falls back to the recorded branch.
- `AttendanceComputationService::day()` uses corrected effective timestamps, versioned policies where available, and approved standalone manual corrections. It combines sessions at the employee-day level. The underlying `WorkDuration` service handles unpaid break overlap, grace, undertime and overnight shifts. Incomplete sessions retain known punches and metrics without attributing estimated work minutes. Legacy records without a computation use the application's existing legacy fallback, which can leave late and undertime unavailable.
- Scheduled workdays count work obligations before leave. Present days count scheduled days with accepted Time In, including days with missing Time Out. Late arrivals count employee-days with a positive authoritative late duration. Work hours use net minutes from complete attendance.
- The application had an expected-work classification but no persisted absence status. The report counts a missing day as absent after the resolved shift ends if it was an eligible workday with no effective punch or pending evidence review. Pending leave and correction requests remain visible without being applied as approvals. A partial-day leave exempts the approved portion and leaves the other portion scheduled.
- Approved leave counts on scheduled dates inside the selected period. One half-day contributes 0.5 and overlapping approved slots are deduplicated by the resolver. Leave coverage and leave balance charges serve different purposes.
- Synthetic rows respect the hire date and the first recorded invitation activation when available. Pending-activation accounts do not generate synthetic days. There is no authoritative separation date, so inactive employees retain recorded attendance, correction and approved leave history without inferred missed days.
- The date range defaults to the current business month. It is capped at 92 inclusive days and 100,000 candidate employee-days. The service processes date windows of up to a week and employee batches. Existing attendance, correction, schedule and leave indexes support the lookups. No migration was added.

## Access and export

All report endpoints use the existing authentication, session, privacy acknowledgement and HR middleware. CSV includes all matching days in the report sort order, with numerical duration columns. It uses UTF-8 and PHP CSV serialization with explicit escaping rules. Spreadsheet formula prefixes, including full-width variants and leading whitespace before a formula, are neutralized by prefixing a single quote. Export generation uses a temporary stream that spills to disk when needed. It completes before download headers are sent so a failed report cannot appear as a successful partial CSV. Report and export contain no photo, GPS, or confidential leave document fields.

## Files changed

| Area | Files |
| --- | --- |
| Requests and controllers | `app/Http/Requests/AttendanceReportRequest.php`, `app/Http/Controllers/HR/AttendanceReportController.php` |
| Report and policy | `app/Services/AttendanceReportingService.php`, `app/Services/AttendancePolicyResolver.php` |
| Routes and interface | `routes/web.php`, `resources/views/attendance/reports.blade.php`, `resources/views/attendance/report-day.blade.php`, `resources/views/components/sidebar.blade.php`, `resources/views/components/icon.blade.php` |
| Tests | `tests/Feature/AttendanceReportTest.php`, `tests/Unit/AttendanceReportCsvTest.php`, `tests/Browser/attendance-reports.spec.js` |
| Progress | `ATTENDANCE_REPORTS_PROGRESS.md` |

## Verification and deployment

- Vite production asset build passed.
- Laravel Pint passed.
- All 355 PHPUnit tests passed. The 62 affected report, attendance, and leave tests passed again after the final report batching change.
- The dedicated Playwright report test passed at 1440px and 390px, covering filtering, daily details, filter persistence and CSV download.
- The HR dashboard browser check passed on retry. The broader UI sweep reached the report at desktop and mobile widths, then timed out while capturing an employee page.

Use the normal XAMPP or Railway release flow and rebuild frontend assets with `npm run build`. No new dependency, migration or environment variable is required. Employee options narrow after applying branch or department filters and use the native select's keyboard type-ahead.
