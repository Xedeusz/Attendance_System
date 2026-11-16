# Phase 3 — Employee and HR Administration

Date: 2026-11-12. Stack unchanged: Laravel 13.42.0, PHP 8.5.12, Fortify 1.42.0, Blade, Tailwind 4.3.5, Alpine 3.17.5 and XAMPP MariaDB 10.11.16. No new package was installed.

## Approved authorization model

Exactly **HR** and **Employee** are supported. HR is the company-wide system administrator. There are no separate administrative roles or branch-scoped staff grants. This explicit Phase 3 instruction supersedes those parts of Phase 1/2. [RBAC permissions](RBAC_PERMISSIONS.md) defines current access.

Existing users were retained as active Employees, without automatic HR promotion. The initial HR account must be provisioned through `app:bootstrap-hr`. The old direct-development-account command now refuses creation; subsequent accounts must be created by authenticated HR.

## Implemented modules

| Module | Actual functionality |
| --- | --- |
| Employee directory | Real records; name/number/email search; branch/department/employment/account filters; allowlisted sort/direction; 15-row server pagination; detail/create/edit |
| Employee creation | Fixed Employee role, pending activation, user/profile/assignment/invitation/audit in one transaction; invitation delivery after commit |
| Employee maintenance | Validated employment data and position title; unique account email/employee number; no password reset for ordinary edits; email change requires activation at the new address |
| Account lifecycle | Deactivation/revocation, remember-token rotation, DB session deletion, session-version checks, authorized reactivation and last-active-HR protection |
| Branches | Real search/filter/sort/pagination, create/detail/edit, valid coordinate pairs/radius, archive via inactive status and confirmation; assigned active employees block deactivation |
| Departments | Company-wide directory, search/filter/sort/pagination, create/edit, activate/inactivate; active employees block deactivation |
| HR accounts | Separate HR-only creation path, five-minute password confirmation, invitation activation, resend, status controls and last-active-HR safeguard |
| Activation | Random 256-bit token, SHA-256 DB hash, 24-hour lifetime, single-use/revocation/replacement, password validation, account/employment eligibility checks |
| Self-service | Own profile/assignment/department/position; contact-number update only; Fortify current-password-validated password change |
| Dashboards | HR employee/account/branch/department counts and recent/pending records from DB; own real employment details on Employee dashboard |
| Audit | Transactional append-style administrative events with actor, subject, UTC time, bounded request metadata and safe change metadata; HR-only read interface |
| Local mail | Laravel custom transport captures encrypted development previews; HR-only, reauthentication required, local environment only, 24-hour access expiry and cleanup command |

Positions are HR-controlled `position_title` values on employee profiles, following the requested proposed schema. A separate position catalogue is not invented. Departments are company-wide; an employee's primary office is tracked independently in effective-dated assignments.

## Authentication and lifecycle details

- Fortify remains the authentication backend. Its authentication callback checks account status and active employment; wrong credentials/status use the generic login error.
- Login updates `last_login_at`; session rotation/CSRF/logout remain framework-managed. Existing remembered sessions must also pass active-account checks.
- User `role_id` is not mass assignable. No endpoint changes a role; HR creation explicitly assigns HR on a separate reauthenticated route. Employee/profile requests reject protected-field injection.
- Only an activated, verified account can return directly to active status after HR-authorized reactivation. Never-activated accounts return to pending and need a new invitation. Inactive employment blocks reactivation/activation.
- Infrequent HR writes and activation commits use a locked HR role row. This serializes last-HR, assignment, deactivation, and invitation state invariants. Network delivery occurs outside the lock/transaction.
- New employee passwords start as unguessable random values; no temporary password is printed, sent, or stored as plaintext. Activation lets the recipient set a private password.
- The same shared password policy applies to bootstrap, activation, recovery and changes: 12+ characters, upper/lowercase letters, number and symbol.

## Branch map enhancement (2026-11-16)

Branch Create/Edit now share an interactive Leaflet/OpenStreetMap office picker and live geographic radius preview. Details shows a read-only map. Active branch writes require a complete valid geofence; inactive branches can remain unconfigured. Company radius bounds are configurable and HR changes audit previous/current coordinates and radius. Existing assignments and deactivation protections remain unchanged. Phase 4 now uses these values for attendance, superseding the original Phase 3-only scope statements below. See [implementation and verification](BRANCH_MAP_GEOFENCING.md).

## Local delivery configuration

The ignored local `.env` uses `MAIL_MAILER=private_local`, `AUTH_PASSWORD_RESET_ENABLED=true`, and `APP_URL=http://127.0.0.1:8000`. No paid/email service is required. The private capture transport is **not external delivery** and is disabled outside `local`. Activation/recovery messages are not written to Laravel logs. Generic password-recovery responses do not reveal whether an email exists; inactive/pending users do not receive recovery messages.

HR can view local invitations at `/hr/local-mail` after confirming the current password. Message bodies are encrypted at rest and escaped as plain text. Use a separate/private browser context when testing the invited employee. A message marked `local_preview` was captured locally; `submitted` means accepted by configured SMTP, not proven recipient delivery; failed/unconfigured delivery leaves the account pending and offers resend.

For production, configure approved SMTP, HTTPS and the authoritative APP_URL; remove development inbox use and complete mail/privacy/operational review. Do not use the log or array transports for actual invitations.

## Important file inventory

All paths are relative to `C:\xampp\htdocs\Attendance_System`.

| Path | Purpose |
| --- | --- |
| `database/migrations/2026_11_12_051823_create_phase_three_administration_tables.php` | Additive tables, user lifecycle fields, foreign keys and unique current assignment |
| `database/seeders/RoleSeeder.php` | Repeatable exactly-two-role seed; no company data/accounts |
| `app/Models/{User,Role,Employee,Branch,Department,EmployeeBranchAssignment,AccountInvitation,AuditLog,LocalMailMessage}.php` | Relationships, casts, protected attributes and eligibility |
| `app/Services/Administration.php` | Short locked transactions and fresh HR authorization |
| `app/Services/EmployeeService.php` | Atomic employee persistence and branch-transfer history |
| `app/Services/{AccountService,InvitationService,AuditService,PasswordPolicy}.php` | Account state, session/token lifecycle, delivery, audit and shared policy |
| `app/Http/Requests/{EmployeeRequest,BranchRequest,DepartmentRequest}.php` | Backend validation and HR authorization |
| `app/Http/Middleware/{RequireHR,EnsureActiveAccount}.php` | Role enforcement and active/session-version checks |
| `app/Policies/EmployeePolicy.php` | Directory, ownership and HR update permissions |
| `app/Http/Controllers/HR/{EmployeeController,BranchController,DepartmentController,HRAccountController,AuditController,LocalMailController}.php` | HR modules and protected administration |
| `app/Http/Controllers/{ActivationController,SelfServiceController,DashboardController}.php` | Activation, own profile and actual dashboards |
| `app/Providers/FortifyServiceProvider.php`, `app/Http/Responses/RoleLoginResponse.php`, `app/Listeners/RecordLogin.php` | Role-aware login and login timestamp/session state |
| `app/Actions/Fortify/{ResetUserPassword,UpdateUserPassword}.php` | Framework password lifecycle and session revocation |
| `app/Mail/{AccountInvitationMail,PrivateLocalTransport}.php` | Testable invitation mail and encrypted local capture |
| `app/Console/Commands/{BootstrapHR,PruneLocalMail,ProvisionUser}.php` | First HR, preview cleanup, disabled legacy provisioning |
| `routes/{web,console}.php`, `bootstrap/app.php`, `config/{auth,fortify,mail}.php` | Protected routes, reauthentication, middleware and cleanup schedule |
| `resources/views/hr/` | Dashboard, employees, branches, departments, HR accounts, audit and private inbox |
| `resources/views/employee/`, `resources/views/auth/activate.blade.php`, `resources/views/workspace/settings.blade.php` | Own profile/workday, activation and password change |
| `resources/views/components/{select,state,confirm-check}.blade.php` | Reusable form/status/confirmation controls |
| `resources/views/components/{sidebar,topbar,stat-card,layouts/app}.blade.php` | Two-role navigation, actual metrics, common feedback and layout |
| `resources/js/app.js`, `resources/css/app.css` | Fragment-token activation, shared responsive forms/tables |
| `tests/Feature/{Phase3Authorization,EmployeeManagement,BranchDepartment,AccountActivation,HRAccount}Test.php` | Phase 3 behavior/security coverage |
| `tests/Support/Phase3Data.php`, `database/factories/` | Synthetic test setup only |
| `tests/Browser/{fixture.php,foundation.spec.js,phase3.spec.js}` | Existing regression checks and actual MariaDB/Edge workflow |

## Run and first HR provisioning

Start XAMPP MariaDB if necessary. From the project root:

```powershell
& 'C:\xampp\php\php.exe' artisan migrate
& 'C:\xampp\php\php.exe' artisan app:bootstrap-hr
.\scripts\dev.ps1 -Task serve
```

The migration has already been applied here; rerunning it reports no pending work. Bootstrap prompts privately for name/email/password/confirmation and refuses once any HR account exists. Choose a new, unique email; it does not silently promote or replace the existing login. No initial HR password was invented or committed by the development assistant.

Open **http://127.0.0.1:8000/login**. Use `scripts/dev.ps1 -Task assets` in a second terminal for live frontend edits or `-Task build` for compiled assets. To run cleanup periodically, use `php artisan schedule:work` locally, or invoke `php artisan accounts:prune-local-mail`. Production scheduling is a later deployment task.

## Privacy and limits

Only necessary employment/contact fields are collected; work email is authoritative in `users`, avoiding a second stored copy. Audit records exclude contact values, passwords and raw tokens. Activation tokens travel in URL fragments and then POST bodies; the fragment is removed from browser history before submission. Do not add request-body logging or analytics to activation/recovery screens. Production proxies/APM must redact all secret-bearing URLs and bodies, including framework reset links.

The company/DPO still must approve lawful processing, employee notices, retention, access/recovery processes and real-data rollout under the Philippine privacy plan in the blueprint. Implemented controls are not a legal-compliance certification. Backups, external audit tamper evidence, privileged MFA, production SMTP/HTTPS and company privacy operations remain deployment gates.

No biometric enrollment/PAD, camera, GPS validation, attendance punch, leave approval, payroll, or attendance analytics is implemented. Branch coordinates are configuration only. HR employee-profile linkage is supported by the one-to-one model and shared self-service routes; an operational HR-to-employee linking interface is future work.

Phase 3 stops after verification. See [test record](PHASE_3_TEST_RESULTS.md); no next phase starts automatically.

Final verification: **65 PHP tests / 348 assertions, 3 headless Edge browser tests, asset build, formatting, Blade compilation, MariaDB migration/schema checks and documentation checks passed.** Physical-device, production SMTP/HTTPS and company privacy/operations gates remain as documented. The local inbox is a testing channel, not an independently accessible mailbox for recovering a sole HR account whose password is lost; configure approved SMTP before real recovery use.

## Exact Phase 3 file paths

Workspace: C:/xampp/htdocs/Attendance_System. Local .env configuration also changed, remains ignored, and is never included in Git.

- `C:/xampp/htdocs/Attendance_System/.env.example`
- `C:/xampp/htdocs/Attendance_System/app/Actions/Fortify/ResetUserPassword.php`
- `C:/xampp/htdocs/Attendance_System/app/Actions/Fortify/UpdateUserPassword.php`
- `C:/xampp/htdocs/Attendance_System/app/Console/Commands/BootstrapHR.php`
- `C:/xampp/htdocs/Attendance_System/app/Console/Commands/ProvisionUser.php`
- `C:/xampp/htdocs/Attendance_System/app/Console/Commands/PruneLocalMail.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/ActivationController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/DashboardController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/HR/AuditController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/HR/BranchController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/HR/DepartmentController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/HR/EmployeeController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/HR/HRAccountController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/HR/LocalMailController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/SelfServiceController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Controllers/WorkspaceController.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Middleware/EnsureActiveAccount.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Middleware/RequireHR.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Requests/BranchRequest.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Requests/DepartmentRequest.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Requests/EmployeeRequest.php`
- `C:/xampp/htdocs/Attendance_System/app/Http/Responses/RoleLoginResponse.php`
- `C:/xampp/htdocs/Attendance_System/app/Listeners/RecordLogin.php`
- `C:/xampp/htdocs/Attendance_System/app/Mail/AccountInvitationMail.php`
- `C:/xampp/htdocs/Attendance_System/app/Mail/PrivateLocalTransport.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/AccountInvitation.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/AuditLog.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/Branch.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/Department.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/Employee.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/EmployeeBranchAssignment.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/LocalMailMessage.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/Role.php`
- `C:/xampp/htdocs/Attendance_System/app/Models/User.php`
- `C:/xampp/htdocs/Attendance_System/app/Policies/EmployeePolicy.php`
- `C:/xampp/htdocs/Attendance_System/app/Providers/AppServiceProvider.php`
- `C:/xampp/htdocs/Attendance_System/app/Providers/FortifyServiceProvider.php`
- `C:/xampp/htdocs/Attendance_System/app/Services/AccountService.php`
- `C:/xampp/htdocs/Attendance_System/app/Services/Administration.php`
- `C:/xampp/htdocs/Attendance_System/app/Services/AuditService.php`
- `C:/xampp/htdocs/Attendance_System/app/Services/EmployeeService.php`
- `C:/xampp/htdocs/Attendance_System/app/Services/InvitationService.php`
- `C:/xampp/htdocs/Attendance_System/app/Services/PasswordPolicy.php`
- `C:/xampp/htdocs/Attendance_System/bootstrap/app.php`
- `C:/xampp/htdocs/Attendance_System/config/auth.php`
- `C:/xampp/htdocs/Attendance_System/config/fortify.php`
- `C:/xampp/htdocs/Attendance_System/config/mail.php`
- `C:/xampp/htdocs/Attendance_System/database/factories/BranchFactory.php`
- `C:/xampp/htdocs/Attendance_System/database/factories/DepartmentFactory.php`
- `C:/xampp/htdocs/Attendance_System/database/factories/EmployeeFactory.php`
- `C:/xampp/htdocs/Attendance_System/database/factories/UserFactory.php`
- `C:/xampp/htdocs/Attendance_System/database/migrations/2026_11_12_051823_create_phase_three_administration_tables.php`
- `C:/xampp/htdocs/Attendance_System/database/seeders/DatabaseSeeder.php`
- `C:/xampp/htdocs/Attendance_System/database/seeders/RoleSeeder.php`
- `C:/xampp/htdocs/Attendance_System/docs/BRANCH_MANAGEMENT.md`
- `C:/xampp/htdocs/Attendance_System/docs/DATABASE_SCHEMA.md`
- `C:/xampp/htdocs/Attendance_System/docs/DATABASE_SETUP.md`
- `C:/xampp/htdocs/Attendance_System/docs/DEVELOPMENT_ROADMAP.md`
- `C:/xampp/htdocs/Attendance_System/docs/EMPLOYEE_ACCOUNT_WORKFLOW.md`
- `C:/xampp/htdocs/Attendance_System/docs/PHASE_2_SETUP.md`
- `C:/xampp/htdocs/Attendance_System/docs/PHASE_3_IMPLEMENTATION.md`
- `C:/xampp/htdocs/Attendance_System/docs/PHASE_3_TEST_RESULTS.md`
- `C:/xampp/htdocs/Attendance_System/docs/RBAC_PERMISSIONS.md`
- `C:/xampp/htdocs/Attendance_System/docs/REQUIREMENTS.md`
- `C:/xampp/htdocs/Attendance_System/docs/SYSTEM_BLUEPRINT.md`
- `C:/xampp/htdocs/Attendance_System/docs/TECH_STACK.md`
- `C:/xampp/htdocs/Attendance_System/docs/UI_COMPONENTS.md`
- `C:/xampp/htdocs/Attendance_System/phpunit.xml`
- `C:/xampp/htdocs/Attendance_System/README.md`
- `C:/xampp/htdocs/Attendance_System/resources/css/app.css`
- `C:/xampp/htdocs/Attendance_System/resources/js/app.js`
- `C:/xampp/htdocs/Attendance_System/resources/views/auth/activate.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/confirm-check.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/layouts/app.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/select.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/sidebar.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/stat-card.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/state.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/components/topbar.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/dashboard.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/employee/dashboard.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/employee/profile.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/accounts/create.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/accounts/index.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/audit.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/branches/form.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/branches/index.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/branches/show.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/dashboard.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/departments/form.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/departments/index.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/employees/form.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/employees/index.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/employees/show.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/mail/index.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/hr/mail/show.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/mail/activation.blade.php`
- `C:/xampp/htdocs/Attendance_System/resources/views/workspace/settings.blade.php`
- `C:/xampp/htdocs/Attendance_System/routes/console.php`
- `C:/xampp/htdocs/Attendance_System/routes/web.php`
- `C:/xampp/htdocs/Attendance_System/scripts/dev.ps1`
- `C:/xampp/htdocs/Attendance_System/tests/Browser/fixture.php`
- `C:/xampp/htdocs/Attendance_System/tests/Browser/foundation.spec.js`
- `C:/xampp/htdocs/Attendance_System/tests/Browser/phase3.spec.js`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/AccountActivationTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/AuthenticationFoundationTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/BranchDepartmentTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/EmployeeManagementTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/HRAccountTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/PasswordRecoveryTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Feature/Phase3AuthorizationTest.php`
- `C:/xampp/htdocs/Attendance_System/tests/Support/Phase3Data.php`
