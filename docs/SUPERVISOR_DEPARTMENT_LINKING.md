# Supervisor department linking

## Relationship and rollout

Each Supervisor has one nullable direct `users.supervisor_department_id` foreign key referencing `departments.id` with restricted deletion. The `supervisorDepartment()` belongs-to relationship is independent of any Employee profile or the Employee's own department. No duplicate account/department is created, and no Employee profile is required. Historical changes are retained in audit logs with actor, original/new department IDs and server timestamp (`supervisor.department_changed`). The nullable FK plus existing audit trail avoids a redundant assignment-history table.

Migration: `2026_11_17_023450_add_supervisor_department_id_to_users_table.php`. It was applied incrementally to the local MySQL database. It does not infer/backfill links, revoke assignments or alter attendance. Rollback refuses to discard populated department links; use a reviewed forward migration.

A read-only audit found one enabled current/future assignment with a missing or mismatched direct department at rollout. HR must review it explicitly. Supervisor list warnings and details identify conflicts, and the Not assigned department filter finds missing links. Legacy rows remain in assignment history but no longer provide leave access unless the current department policy is satisfied.

## HR workflow

Open Supervisor Details, confirm the existing sensitive-administration password requirement when prompted, and use Department Assignment. Current department is shown separately from profile identity. Select an existing active department and explicitly Save Department Assignment. No extra confirmation popup is required for normal department saves. Edit/Create Supervisor uses the same relationship through Assigned Department. A blank field in account editing can clear a link only if no enabled current/future assignments would become inconsistent.

Both paths validate server-side, execute inside the existing workflow transaction/administration lock, and audit changes. Signed edit versions prevent stale forms from overwriting newer data. Supervisor Management eager-loads direct departments, preserves its table columns/search/pagination, and supports all/specific/unassigned department filters. Eligible assignment counts and conflict warnings are aggregated without per-row queries.

## Employee assignment and conflict handling

New explicit employee assignments require an active Supervisor, active assigned department, active employee/account eligibility under existing rules, and equal employee/Supervisor department IDs. Self-assignment and existing overlap/effective-date protections remain enforced. Details only offers eligible employees from that department; without a linked department the form explains the prerequisite and disables saving.

Changing or clearing a department is rejected if any enabled, unexpired assignment (including future assignments) has an incompatible employee department. HR must explicitly revoke/reassign those rows through the existing administrative workflow, then save the department. Matching legacy assignments can be retained when HR links the matching department. No automatic assignment creation, revocation, transfer or department-wide permission is introduced.

Existing assignment rows and effective dates remain visible. Department conflicts are labeled as blocked access. Employee transfers immediately cease satisfying `SupervisorAssignment::current`; changing an employee department through HR editing also refreshes pending leave routing. Inactive departments likewise cease authorizing leave scope.

## Leave and security

`SupervisorAssignment::current` includes direct-department equality and active-department eligibility. Existing LeaveRequest::forSupervisor, policies, private documents, routing and final decision transactions use that common scope. The department alone never authorizes an employee's leave; an explicit, currently effective assignment and existing ownership/self-review restrictions remain necessary.

The final workflow is still Employee submission -> assigned Supervisor approval/rejection -> final employee result. No HR final approval stage is added. Supervisors see their assigned department read-only in the leave workspace, with no Department Management navigation or self-assignment API. HR-only middleware, fresh actor authorization, password confirmation and Form Request validation protect writes. Supervisors and Employees cannot link departments or assign employees. Attendance code and official attendance evidence remain unchanged by this feature.

## Real-time behavior

Committed Supervisor department changes reuse private workspace invalidations and durable revisions. HR lists/details and the Supervisor's leave header refresh through authorized reads; publishing remains queued after commit. Reverb availability is not required to persist a change. Department name/status changes also invalidate directly linked Supervisors without Employee profiles. HR Supervisor pages subscribe to department changes. Opted-in department/employee forms refresh when clean, but preserve entered drafts and show saved-data conflict feedback when dirty.

## Verification

The final full PHP suite passed with 209 tests and 1,423 assertions. The focused Supervisor/leave regression run passed 58 tests and 358 assertions. Six new feature tests cover direct links without Employee profiles, list/detail/edit/leave display, HR-only writes, invalid/inactive choices, matching legacy preservation, conflicting changes, department-not-permission, immediate transfer scope loss, dropdown/server eligibility, shared account-edit relationship, and stale saves.

Microsoft Edge passed direct assignment/clearing without an Employee profile, live restricted-workspace department updates, eligible options, and widths 320/390/768. The existing Phase 5 browser scenario also passed final Supervisor approval and HR correction isolation. An initial immediate width assertion was intermittent during viewport changes; the final test waits for responsive layout to settle. Physical Android/iPhone hardware and production hosting were not tested.

## Files

- `app/Models/User.php`, `app/Models/SupervisorAssignment.php`
- `app/Services/SupervisorService.php`, `app/Services/EmployeeService.php`, `app/Services/WorkspaceSync.php`
- `app/Http/Requests/SupervisorDepartmentRequest.php`, `app/Http/Requests/SupervisorAccountRequest.php`
- `app/Http/Controllers/HR/SupervisorController.php`, `routes/phase5.php`
- `database/migrations/2026_11_17_023450_add_supervisor_department_id_to_users_table.php`
- `resources/views/hr/supervisors/index.blade.php`, `form.blade.php`, `show.blade.php`
- `resources/views/leave/index.blade.php`, `resources/js/realtime.js`
- `tests/Feature/SupervisorDepartmentTest.php`, `PhaseFiveWorkflowTest.php`, `SupervisorAuthorizationTest.php`
- `tests/Browser/supervisor-department.spec.js`, `tests/Browser/fixture.php`
- This report and cross-references in Supervisor role/assignment, Phase 5, RBAC, database-schema and README documentation.