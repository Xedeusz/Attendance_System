# Implemented database schema — current through Phase 5
Updated 2026-11-16. XAMPP MariaDB 10.11.16 / InnoDB / utf8mb4; UTC timestamps and Asia/Manila business dates. Existing users, employee identities, attendance events/photos/GPS and historical decisions are retained. Exactly three fixed role rows exist: HR=1, Employee=2, Supervisor=3. User role/account/session fields are guarded from public mass assignment.

## Phase 5 additive migration
database/migrations/2026_11_16_061609_create_phase_five_workflows.php was applied locally using migrate, without fresh/reset/wipe. It extends roles.code enum with supervisor and adds the following tables.

| Table | Main relationships and safeguards |
| --- | --- |
| supervisor_assignments | Supervisor User, Employee, HR assigner; inclusive effective dates, enabled flag and revocation timestamp; employee/effective lookup index; restricted foreign keys |
| leave_types | Unique code; active/document/balance/half-day settings and requested_days interim duration policy |
| leave_requests | Employee/type/Supervisor; dates, decimal requested/charged days, half-day, encrypted reason, JSON type snapshot, indexed state and submission/review/final/cancel times |
| leave_reviews | Request/reviewer; action, encrypted remarks, JSON routing metadata and review timestamp; generated unique final_request_id for approved/rejected actions |
| employee_leave_balances | Unique Employee/type/policy_period; decimal available_days; no automatic entitlement |
| leave_balance_transactions | Balance/request/authorizer; signed amount, type and encrypted reason; unique nullable leave_request_id prevents duplicate final debit |
| leave_supporting_documents | Request FK, unique random private encrypted storage path, sanitized name, content MIME/size, optional expiry and purge timestamps |
| attendance_correction_requests | Employee/optional original record/branch/HR reviewer; requested date and effective times, JSON original snapshot, record revision, encrypted reason/remarks, indexed state, unique nullable pending_key |
| notifications | Standard Laravel UUID database notifications, polymorphic owner, JSON payload/read timestamp; Supervisor queries restrict leave category |

attendance_records gains nullable effective_time_in_at/effective_time_out_at and correction_version. The unique generated open_employee_id includes only pending/open captured sessions without an effective checkout. This lets an approved manual checkout release the open-session guard while leaving original captured timestamps/status/evidence untouched. A missing-record correction stays supplemental; it does not manufacture a captured attendance row.

Financial day values use decimal storage and integer half-day arithmetic in services. Business mutations share the existing HR Role mutex, then lock workflow/balance rows. Foreign keys restrict deletion of referenced identities/history. No web endpoint deletes leave types, assignments, reviews or ledger entries. Rollback refuses populated Phase 5 business/configuration tables or Supervisor accounts; use a reviewed forward migration for real history.

Migrations work in isolated SQLite feature tests and were applied to local MariaDB. SQLite cannot establish InnoDB lock behavior; target multi-worker load/deadlock testing remains an operational check.

## Earlier schema
Phase 3 administration includes users/roles, branches, departments, employees, employee_branch_assignments, account_invitations, audit_logs and encrypted development local_mail_messages alongside framework tables. Employee assignment history retains effective ranges. Secure invitations store token hashes rather than permanent plaintext passwords.

Phase 4 adds attendance_records, attendance_events, attendance_capture_sessions, attendance_photo_evidence, attendance_reviews and attendance_assistance_requests plus attendance version controls. See [attendance schema](ATTENDANCE_DATABASE.md). Original facts remain protected and separate from Phase 5 effective manual values.

There are no biometric templates, facial-processing tables, schedule/holiday templates, payroll or export tables. The existing local database name is retained for compatibility.
