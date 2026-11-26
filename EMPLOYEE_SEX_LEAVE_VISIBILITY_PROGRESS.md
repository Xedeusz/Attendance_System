# Employee Sex and Leave Visibility Progress

## Completed

- Added one nullable, authoritative `employees.sex` field. Existing employee records remain unchanged and can continue authenticating until HR completes the field.
- Added required Male/Female selection to HR employee creation and editing, with normalized `male` and `female` values and server-side allow-list validation.
- Added the recorded value to the HR employee details view. Missing legacy values display as requiring HR completion.
- Added `LeaveTypeVisibilityService` as the centralized rule for employee leave forms, balance cards, eligibility requests, and backend leave submission enforcement.
- Female employees see Maternity Leave by default; male employees see Paternity Leave by default. Ordinary configured leave types remain visible to both.
- Employees without a completed Sex record do not receive a default maternity or paternity pathway and are directed to HR without losing account access.
- Existing pending/verified eligibility records and current verified entitlements preserve visibility as authorized exceptions, even after an HR correction.
- HR changes are audit logged. Active eligibility requests and entitlements are recorded in the audit metadata for HR conflict review; no leave history, credits, or eligibility records are deleted or recalculated.
- The backend rejects modified-browser submissions for a special leave type that is not applicable to the authoritative employee record.
- Unverified event leave cards display `Eligibility verification required`; verified event balances continue to use actual ledger values and whole-number formatting.

## Migration

- `2026_11_26_055847_add_sex_to_employees_table.php`
- The column is nullable for backward compatibility. New and edited employee records require HR to select a supported value through application validation.
- Applied successfully to the configured MySQL database in migration batch 18.
- At deployment verification, all 8 existing employee records remained unclassified and require HR completion; none were inferred or assigned automatically.

## Main Files

- `app/Services/LeaveTypeVisibilityService.php`
- `app/Services/LeaveService.php`
- `app/Services/SpecialLeaveEligibilityService.php`
- `app/Services/EmployeeService.php`
- `app/Http/Controllers/LeaveController.php`
- `app/Http/Controllers/SpecialLeaveEligibilityController.php`
- `app/Http/Requests/EmployeeRequest.php`
- `resources/views/hr/employees/form.blade.php`
- `resources/views/components/employee-profile-details.blade.php`
- `resources/views/leave/index.blade.php`
- `resources/views/leave/eligibility/create.blade.php`

## Automated Coverage

- `tests/Feature/EmployeeSexLeaveVisibilityTest.php` covers supported values, required/invalid validation, HR-only editing, audit history, female/male/null visibility, balance cards, tampered requests, and authorized exceptions.
- Existing Employee Management and Special Leave Eligibility suites remain part of the focused regression run.
- Result: 30 tests passed with 239 assertions across the employee, visibility, eligibility, and leave-credit suites.
- Laravel Pint passed and all Blade templates compiled successfully.
- The broader image/PDF workflow was also invoked; its image-dependent cases cannot run in the current CLI because the GD extension is not installed. The non-image cases completed, with the one changed visibility expectation updated and verified in the passing focused suite.

## Policy Boundary

- Sex controls the ordinary default pathway only. It does not itself grant statutory eligibility.
- Event eligibility and credit creation still require HR verification.
- Statutory exceptions must be established through an authorized HR eligibility record or entitlement; the system does not infer exceptions from personal data.
