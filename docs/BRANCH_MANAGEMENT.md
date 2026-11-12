# Branch, Department and Assignment Management

Phase 3. HR has company-wide administrative access. Employees see only their own assigned branch through personal dashboard/profile; HR directory endpoints return 403 to Employees.

## Branch administration

Open `/hr/branches`. The list uses real MariaDB records with code/name/city search, active/inactive filter, allowlisted sorting and server pagination. Columns show code, name, complete address, current assigned-employee count, status and actions. No office names, addresses, coordinates or count of 27 is seeded or reported as actual data.

Create/edit fields: code, descriptive name, street address, city, province, optional postal code, optional coordinate pair, optional positive radius in metres and controlled active/inactive status. Codes are unique and limited to letters/numbers/underscore/hyphen. Latitude is -90 to 90; longitude -180 to 180; supply both or neither. Radius is an integer from 1 to 100,000 metres if supplied, an input-safety bound rather than an approved attendance policy.

Coordinates/radius are configuration only. No Maps integration, camera/location permission, GPS collection, distance decision or attendance acceptance is implemented.

HR detail view shows address, coordinates, radius, status and assignment count. Ordinary office edits do not move employees. Changing status from active to inactive requires an explicit confirmation and **zero current assignments for employment-active employees**. A deactivated login does not alone remove an employee's employment-active assignment; transfer or mark employment inactive first. This avoids stranding active employment at an inactive branch. Reactivation uses the edit status control. Rows are retained, not deleted.

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

Future attendance must snapshot the effective assignment/geofence/rules used at the event time rather than relabeling historical punches after a transfer. Shift scheduling and versioned attendance geofence policy remain separate later phases. Production data retention and archival/deletion rules require company/DPO approval.
