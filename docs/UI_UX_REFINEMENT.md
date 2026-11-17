# LIMCOMA UI/UX refinement

This refinement builds on the approved HR Workforce Overview and automatic Photon location selection. It changes shared presentation and information hierarchy across HR and Employee screens. Existing roles, routes, authorization, authentication, database schemas, submissions, geofencing, evidence storage and review workflows remain in place. No package or frontend framework was added.

## Initial UI audit

Before editing, the shared layouts, all current Blade modules, CSS tokens, forms, tables, navigation, login, dashboards, attendance capture/evidence/history, profile/settings, notifications, administration and existing tests were reviewed. Representative screenshots were captured before implementation.

Observed issues:

- Small, pale labels/table headers and metadata reduced readability.
- Similar card/icon treatments gave different kinds of information equal visual priority.
- The Employee dashboard placed employment and recent-record panels before the attendance action on mobile.
- Filters occupied several full rows, with technical/secondary controls competing with search.
- Employee identity was split across three columns, making the directory harder to scan.
- Status badges did not consistently distinguish pending/rejected states.
- Required controls lacked a consistent visible indicator. Shared error markup could replace an existing help-text relationship.
- Sidebar navigation had little grouping and did not mark the HR attendance route active. Hidden mobile navigation remained keyboard-focusable.
- Authentication copy/hero size was more promotional than operational, and a checkmark stand-in appeared where an approved logo was unavailable.
- Leave/report previews devoted space to unavailable statistics/controls.
- Empty tables could inherit the width of their full column set, moving the empty-state message outside a narrow viewport.

## Design system

The approved palette is unchanged: Deep Purple #2F1B72, Dark Purple #241454, Teal #10AB8C, accessible Dark Teal #087C68, Light Teal #E7F7F2, Light Purple #F0ECF8, White #FFFFFF, workspace #F8FAFC, primary text #1E293B, secondary text #64748B and border #E2E8F0. Neutral content surfaces carry most information. Purple identifies primary actions/headings, teal identifies active navigation/positive states, amber identifies pending attention, and red identifies rejected/destructive states. Text labels accompany every status.

Existing Inter/system sans-serif typography is retained. Page titles use 24–28px, section headings 16px, body/table text 13px, labels 12px and supporting text approximately 12px. Compact notification text remains 12.5px. Minor metadata may be smaller; important content is no longer deliberately reduced to 9–10px.

Reusable tokens in `resources/css/app.css` define control/panel radii (8px/10px), 46px form controls, a 4/8/12/16/24/32px spacing scale, body/label font sizes and a popover shadow. Flat bordered panels replace unnecessary elevation. Buttons remain at least 44px tall, with purple primary, neutral outlined secondary and red destructive variants. The existing dark-teal keyboard focus ring and reduced-motion rules are preserved.

The shared stylesheet was edited in place, including removal of competing dashboard overrides for shared navigation. Dashboard-specific composition remains separate from general form/table patterns. Vite/Tailwind/Blade/Alpine remain the established stack; no icon or chart library was introduced.

## Shared layout, navigation and branding

HR navigation is grouped into Overview, Workforce, Attendance & leave and Workspace. Employee navigation is smaller, grouped into My workday and My account. Existing destinations remain available. HR retains My Workday; Employees already have their dashboard in the main menu and no longer receive a duplicate footer link to the same destination.

Active states now follow the actual attendance/history routes. Closed mobile navigation uses native inert behavior; opening the existing drawer restores interaction and the established focus trap/Escape behavior. Collapsed desktop navigation retains accessible link titles. Account controls have a descriptive disclosure-button label without introducing incomplete ARIA menu semantics.

The topbar remains compact, with readable account details and the working Notifications link. Development workspace is still limited to local/testing environments. Breadcrumb/page-header typography, primary/secondary action grouping and footer contrast are consistent.

The approved LIMCOMA logo is served from `public/LIMCOMA-LOGO.png` and is rendered by the shared brand component. Login uses one main Sign in heading, restrained company context, clearer account help and the existing protected login form. Password visibility, CSRF, recovery configuration and authentication behavior are unchanged.

## HR experience

- **Dashboard:** preserves the Workforce Overview hierarchy, real company KPIs, grouped attendance metrics, actual seven-day chart and attention items. Shared surfaces are flatter and typography is consistent; no data definition changed.
- **Employee directory:** groups name, employee number and email into one identity column while retaining department, position, branch, employment/account status and labeled Edit actions. Search/branch/department/account filters remain prominent. Employment/sort/order controls use More filters, which opens when those query parameters are supplied. Filtering/sorting/pagination still submit the original GET parameters.
- **Employee details:** separates personal and employment subsections, keeps branch history/account access and makes deactivation visibly destructive. Invitation/status confirmations and protections remain intact.
- **Branch directory/forms:** groups branch identity and city/province/address, adds a factual configured-radius summary and retains assignment counts/status/actions. The 70/30 map/details layout, map-first form, automatic Photon fill, editable fields, teal marker/circle, manual coordinate controls, wheel/touch behavior and Save/Cancel flow are preserved. No removed Photon confirmation or search paragraphs were restored.
- **Departments:** existing scoped fields/forms, search, status, counts and actions inherit the shared control/table improvements. Departments remain company-wide; no unsupported branch relationship was invented.
- **Attendance Monitoring/Notifications:** eight existing metrics are grouped into one operational summary rather than repeated identical cards. Search/date/branch controls are prominent; session/verification/technical-ID/sort/order filters are available under More filters and reopen for active queries. Evidence and HR decisions retain their existing groupings and secure access. Persistent review notifications remain separate from transient toasts.
- **Administration:** accounts, audit logs and local mail views inherit the shared readable tables, forms and action hierarchy. Account deactivation uses the destructive button variant without changing confirmation or authorization.

## Employee experience and attendance

My Workday now leads with an attendance action panel, the employee's assigned branch and actual current open/pending session, then recent records. Employment details and account/update shortcuts are secondary. An unlinked login keeps the existing explanation and disabled Time In/Out controls. No absence or eligibility is inferred from a missing record.

`DashboardController::employee` uses the existing `AttendanceService::open` lookup for that employee instead of inferring current state from the limited recent-record list. Pending review is identified explicitly; actual action eligibility continues to be checked by the existing attendance page/service. No submission or attendance calculation changed.

The mobile capture page shows three plain-language steps and preserves the existing Time In/Out, permission, live preview, capture, retake, switch-camera, submit, retry/status and assistance controls. Secondary photo/location limitations are available in an About attendance photos and location disclosure. Persistent camera/GPS/result/error messages remain visible when applicable. There are no gallery uploads or facial recognition. GPS/geofence checks, secure timestamps, original evidence, uncertain-submit recovery and HR review remain unchanged.

Attendance History, My Profile and Account Settings use the same readable table/form/status patterns. Password warnings, settings validation, editable contact information and persistent review outcomes remain available.

## Forms, tables and accessibility

Shared input/select/password components display Required beside native required controls while preserving their exact accessible label and native required behavior. Validation stays beside the field. Input/select `aria-describedby` now combines help and error IDs rather than discarding existing help. Visible focus, placeholder contrast, readonly/disabled differentiation and textarea focus are consistent.

Tables retain semantic captions, column headers, labeled focusable scroll regions, subtle row dividers/hover and functional pagination. Wide records scroll inside the region rather than across the page. Empty results hide the irrelevant full-width header row and use the available viewport for the actual empty-state explanation. Exact data and actions remain accessible. This is a practical accessibility refinement, not an independent WCAG conformance certification.

More filters is a native keyboard-accessible details disclosure. Primary actions keep text labels; destructive decisions are never automatically confirmed. Ordinary SweetAlert2 notifications retain the approved compact top-right 3000ms timer, no buttons, semantic icons and reduced-motion support. Confirmations retain meaningful Confirm/Cancel and no auto-dismiss timer.

## Unsupported modules

Leave and Reports remain clearly labeled structural previews with disabled primary actions. Decorative unknown-value KPIs, fake chart areas and unavailable filter forms were removed. Useful links point to implemented attendance/dashboard pages. No leave application, approval, reporting/export or scheduling workflow was invented.

## Responsive and visual QA

Representative screens are captured at 1440px and 390px: Login, HR Dashboard, Employee Management, Add Employee, Branch Management, Add Branch, Department Management, Attendance Monitoring, Notifications, Settings, Leave/Reports previews, Employee Dashboard, Employee Attendance, Attendance History and My Profile. Additional overflow checks exercise 320/360/375/390/430/768/1024/1280/1440px. Existing map tests check desktop layout, wheel zoom, sidebar resizing and mobile touch selection.

Before/after images are saved in `.tools/ui-review/before` and `.tools/ui-review/after`, deliberately outside Playwright's disposable test-results directory. Fixtures use uniquely marked HR/Employee records and clean up only those records; screenshots are local development artifacts, not fabricated production data.

The visual review led to a second pass on advanced filters, empty-table widths and heading colors. Compared with before, employee attendance appears before employment details on mobile, directory identity is grouped, headers/labels are more readable, form actions are restrained, navigation has task groups, and unimplemented previews consume less space.

## Changed files in this refinement

- `app/Http/Controllers/DashboardController.php`
- `resources/css/app.css`
- `resources/views/components/input.blade.php`
- `resources/views/components/select.blade.php`
- `resources/views/components/password.blade.php`
- `resources/views/components/button.blade.php`
- `resources/views/components/page-header.blade.php`
- `resources/views/components/stat-card.blade.php`
- `resources/views/components/state.blade.php`
- `resources/views/components/brand.blade.php`
- `resources/views/components/table.blade.php`
- `resources/views/components/sidebar.blade.php`
- `resources/views/components/topbar.blade.php`
- `resources/views/components/layouts/guest.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/employee/dashboard.blade.php`
- `resources/views/hr/employees/index.blade.php`
- `resources/views/hr/employees/show.blade.php`
- `resources/views/hr/branches/index.blade.php`
- `resources/views/hr/accounts/index.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/updates.blade.php`
- `resources/views/attendance/capture.blade.php`
- `resources/views/attendance/show.blade.php`
- `resources/views/workspace/module.blade.php`
- `tests/Feature/AuthenticationFoundationTest.php`
- `tests/Feature/AttendanceWorkflowTest.php`
- `tests/Feature/AttendanceReviewTest.php`
- `tests/Browser/ui-refinement.spec.js`
- `docs/UI_UX_REFINEMENT.md`
- `docs/LIMCOMA_BRAND_THEME.md`
- `README.md`

These files build on prior uncommitted dashboard/Photon work in the workspace; those earlier changes are preserved.

## Verification and remaining limitations

Commands use the project-local Node toolchain and XAMPP PHP/GD, with the app and MariaDB running for browser tests:

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
npm.cmd run format:check
npm.cmd run build
npm.cmd run test:browser
```

Executed results: **134 backend tests passed (887 assertions)** and **all 15 browser tests passed (3.3 minutes)**. After final visual adjustments, the representative UI test passed again (33.4 seconds); after completing required-textarea labels, the camera/GPS/Time In-Out/HR-review browser test passed again (34.2 seconds). Production build, Pint, Prettier and whitespace checks passed. There are no remaining automated failures. A preliminary UI assertion was corrected to check the native required property rather than a particular HTML boolean-attribute serialization. **38 before and 38 after screenshots** are preserved. Visual review covered Login, HR/Employee dashboards, Employee Management, Add Employee, Branch Management, Add Branch, Attendance Monitoring, Employee Attendance and Attendance History, including desktop and mobile examples. The review corrected heading inheritance and dashboard identity alignment as well as advanced-filter and empty-state layout issues. Existing camera/GPS tests use controlled browser devices and real application/database flows. Physical Android/iPhone, Safari/Firefox and assistive-technology testing are not claimed. An approved logo asset, production service configuration and unimplemented Leave/Reports functionality remain outside this visual refinement. No additional phase was started.
