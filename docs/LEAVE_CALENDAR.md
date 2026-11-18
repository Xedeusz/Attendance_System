# Leave Calendar

The shared Blade calendar uses the project's installed Alpine.js and Tailwind CSS. HR, Supervisors and Employees reach their calendar from the corresponding leave section of the sidebar. Month and Week show each leave application as a continuous bar. The previous grid expanded its events into separate cards with the same title and status on every covered date. List shows each application once for the selected month. On narrow screens, the initial view is List. Users can switch views, navigate periods, return to Today and keep their selected filters and date in the URL.

## Calendar data and access

The authenticated event feed queries existing leave requests that overlap the visible period. It accepts at most 42 inclusive dates, returns up to 150 events per cursor page, and orders by start date and request ID. It supplies an event ID, authorized title, inclusive dates, status and AM/PM designation. The details endpoint independently enforces the same scope and provides the existing application route and, for approved requests with an authorized PDF, a document action. Supporting files, full reasons, signatures and PDF contents stay outside the event feed. The additive date index supports range queries on the existing leave table.

| Role | Calendar scope and filters |
| --- | --- |
| HR | Company schedules, with combined branch, department, Supervisor, employee name or number, leave type and status filters. Branch and department use the employee's current organization. Supervisor event filtering uses the request's assigned Supervisor. |
| Supervisor | Requests assigned to the Supervisor for employees with a current, explicit, department-eligible assignment. The employee selector uses those current assignments. Pending details link to the existing Approve & Sign and Reject workflow. |
| Employee | The authenticated employee's own requests, identified by leave type. The details panel shows available Supervisor remarks and an authorized approved form link. |

The API and database use inclusive civil dates. The Alpine renderer counts the day after the inclusive end as the exclusive layout boundary, using date-only arithmetic. For November 18–20, the visible interval starts November 18 and ends before November 21. Month and week rendering clips the span to the visible period and divides it at Monday-based week rows. Each segment keeps the same leave ID, color and details action. The first and final edges are rounded. An edge that continues into another row is square. Today and displayed workflow timestamps use Asia/Manila. Approved uses teal, pending uses amber, awaiting assignment uses purple, rejected and revoked use muted red, and cancelled uses gray. Labels accompany each color. Rejected, cancelled and revoked records appear through status filters and are excluded from staffing.

## Leave counts and overlaps

Staffing counts apply the organizational filters, independent of the calendar's leave type or displayed status. The summary uses active employees and unique employee counts, and reports employees without approved leave rather than staffing availability. HR overlaps are checked within the same current department and branch. Supervisor overlaps are checked within the authorized team. Full-day requests occupy both sessions, morning or afternoon requests occupy their respective session, and multiple requests by one employee never create a self-overlap. The warning identifies simultaneous approved leave, approved and pending leave, and simultaneous pending leave.

The calendar displays recorded leave instead of inferring shift, holiday or rest-day availability. Calendar actions open the existing workflows for review and document access. Overlap indicators carry no approval or balance effect.

## Updates and interaction

The existing private Reverb workspace revisions trigger a fresh authorized HTTP fetch on submission, decisions, cancellations, assignment changes, revocation and document replacement. Navigation and filters remain in place. The same endpoint works with standard requests and the workspace polling fallback. No event contents are broadcast. The calendar includes keyboard-operated controls, an accessible details dialog, and explicit loading, empty, and retry states. For each week it sorts events by start date, duration for the same start date, title and ID. Overlapping spans stack in up to three visible lanes. A span that cannot fit stays intact and its covered dates show an exact count of hidden loaded events. The + more button moves focus to a list of those hidden requests. A date button displays all loaded leave for that date. The original Load more schedules control brings in additional pages.

The grid truncates labels on narrow spans. Wider bars show the title and status inline. Every bar has an accessible label containing the full title, status, date range and half-day session where applicable. Mobile defaults to List, and users can switch to Month or Week at any viewport size. The app uses one event object per request and constructs only week segments and lightweight day counters for the grid. Refreshes replace the current events by request identity through the existing authorized feed.

## Implementation files

Added app/Http/Controllers/LeaveCalendarController.php, app/Services/LeaveCalendar.php, database/migrations/2026_11_18_030742_add_leave_calendar_date_index.php, resources/js/leave-calendar.js, resources/views/leave/calendar.blade.php, resources/views/components/leave-calendar-event.blade.php, tests/Feature/LeaveCalendarTest.php and this guide.

Updated routes/phase5.php, resources/views/components/sidebar.blade.php, resources/js/app.js, resources/js/realtime.js, and tests/Browser/phase5.spec.js.

README.md and the existing Leave Management, Supervisor Role, Supervisor Assignments, RBAC Permissions, Phase 5 Implementation, and Development Roadmap guides link to this guide.

The continuous-bar update changed `resources/js/leave-calendar.js`, `resources/views/leave/calendar.blade.php`, `tests/Browser/leave-calendar.spec.js`, `tests/Browser/phase5.spec.js`, and this guide. It uses the installed Alpine and Tailwind calendar.

## Verification

For the continuous-bar update, all 263 PHPUnit tests passed. The four calendar feature tests also passed on a focused rerun after the final view changes. The Vite production build and both new Playwright scenarios passed. The browser checks used isolated SQLite and synthetic event responses to verify actual bar width, overlapping lanes, overflow navigation, single-day and multi-day spans, week/month/year boundaries, mobile fit, status filters, the details dialog, and updates triggered by a simulated workspace revision. Mobile verification used a 390 px Edge viewport.

For the original calendar implementation, all 262 PHPUnit tests passed. Calendar-specific cases tested role scope, expired and revoked assignments, department restrictions, combined HR filters, Manila dates, inclusive boundaries, status filtering, half-day overlaps, distinct employee counts, and cursor paging. The Vite build and local MariaDB index migration passed. The targeted Playwright leave workflow passed on the local app, including month navigation, week review, list view, live submission and approval updates, a 320px emulated Employee viewport, keyboard dismissal, and existing signed PDF access. The separate workspace fallback scenario also passed. Mobile verification used Edge viewport emulation.
