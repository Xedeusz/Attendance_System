# Employee profile access

HR opens Employee Management and selects View Profile (or the employee name). The existing employee details route, /hr/employees/{employee}, now uses the shared profile header and details components, including the employee photo, current branch, department, work email, employment status, contact number, date hired and current assigned supervisors. Account details and existing edit, assignment-history and attendance workflows remain HR-only.

Supervisors open Assigned Employees inside Leave Management. The paginated list includes only explicit, currently effective assignments whose supervisor account and linked department meet existing authorization rules. Department membership alone grants no access. The read-only route is GET /supervisor/leave/employees/{employee}/profile (supervisor.leave.employees.profile). Leave application employee names link to profiles in a new tab, preserving unsaved decisions. An optional leave parameter is authorized against the same employee and leave-review policy and supplies a return link.

Employee self-service continues at /employee/profile (and /profile). Employees retain their own contact and photo controls. HR and supervisors cannot upload or remove another employee photo through these profile views.

## Authorization and privacy

EmployeePolicy::view permits HR, the owner, or an explicitly assigned supervisor. Supervisor access requires SupervisorAssignment::current() plus a null revoked_at: active assignment, effective dates in the attendance timezone, active supervisor, active linked department and matching employee department. Expired, future, revoked, inactive or mismatched assignments deny current profile access, including when a historical leave record remains accessible. HR route middleware and RestrictSupervisor remain in force. Missing employees return 404; unauthorized profiles return 403.

Supervisors receive only employee identification and work assignment information: name, employee number, work email, department, position, branch and employment status. Personal contact numbers, account administration, date hired and supervisor assignments are restricted to HR details. No address, government identifiers, salary, bank details, emergency contacts, medical data, camera evidence, GPS data or supporting documents are added to profiles.

## Profile pictures

Existing private profile-photos storage is reused. The owner photo URL remains unchanged. Viewing someone else's photo uses GET /profile/photo/employees/{employee}/{version} (employee.profile.photo). Every read authorizes EmployeePolicy::view, validates the current hashed version and safe storage path, and sends a private no-store JPEG response. Knowing an image URL does not bypass assignments. Superseded versions return 404. Missing images fall back to initials. Other employees' avatars have independent state so the viewer's account avatar is not overwritten. No public storage link is introduced.

## Live updates

Existing per-user workspace revisions and private Reverb channels refresh profile components. Employee/photo changes reach HR and the employee, with leave-topic invalidation for relevant supervisors. Previous assigned supervisors also receive field-free invalidations so department transfers can remove current access. No employee record or sensitive field is broadcast. Existing server-side authorization runs on every HTML and photo refresh. Reverb failure retains the existing polling fallback; ordinary requests work without broadcasting. Date-based expiry is enforced on each request. Dirty decision forms retain the existing conflict protection.

## Layout and verification

The existing LIMCOMA cards, avatar fallback, states, buttons and responsive profile rows are reused. Mobile rows stack, long values wrap, and supervisor controls remain read-only. No database schema or authentication changes are required.

Regression coverage: tests/Feature/EmployeeProfileAccessTest.php, ProfilePhotoTest.php, SupervisorAuthorizationTest.php and SupervisorDepartmentTest.php. Browser coverage: tests/Browser/employee-profile-access.spec.js checks three roles, employee-specific avatars, 1440/768/390 px widths and fallback photo removal updates. Existing attendance and leave suites cover unaffected workflows. Run PHP tests using scripts/php configuration to enable GD; browser fixtures create and clean only synthetic local test accounts.

## Implementation files

- app/Http/Controllers/EmployeeProfileController.php (new)
- app/Http/Controllers/HR/EmployeeController.php
- app/Http/Controllers/LeaveController.php
- app/Http/Controllers/ProfilePhotoController.php
- app/Models/User.php
- app/Policies/EmployeePolicy.php
- app/Services/WorkspaceSync.php
- routes/web.php
- routes/phase5.php
- resources/views/components/employee-profile-header.blade.php (new)
- resources/views/components/employee-profile-details.blade.php (new)
- resources/views/components/user-avatar.blade.php
- resources/views/employee/view-profile.blade.php (new)
- resources/views/employee/profile.blade.php
- resources/views/hr/employees/index.blade.php
- resources/views/hr/employees/show.blade.php
- resources/views/leave/index.blade.php
- resources/views/leave/show.blade.php
- tests/Feature/EmployeeProfileAccessTest.php (new)
- tests/Browser/employee-profile-access.spec.js (new)
- docs/EMPLOYEE_PROFILE_ACCESS.md (new)

## Verification results

- Focused PHP regressions: 57 passed, 324 assertions.
- Complete PHP suite: 224 passed, 1,570 assertions, including attendance and leave workflows.
- Edge browser regression: 1 passed. Verified HR employee details, explicitly assigned supervisor profiles, employee-owned photo controls, independent avatars, 1440/768/390 px layouts without horizontal overflow, image loading, no JavaScript errors, and photo removal propagation with the viewer's Reverb connection disconnected.
- Production Vite build, Blade compilation, Pint and whitespace checks passed.
- Browser fixtures were cleaned through the existing synthetic-account cleanup workflow. No schema changes or new dependencies.
