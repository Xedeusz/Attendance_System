# LIMCOMA Leave Credit Implementation Progress

## Status

Core implementation is complete and ready for local rollout verification.

## Implemented

- Effective-dated annual and event leave policies.
- Default policy definitions for Vacation (15), Sick (15), Birthday (1), Paternity (7), and Maternity (105) leave.
- Event-based Paternity and Maternity entitlements that require explicit HR verification; they are not allocated to every employee annually.
- Encrypted event verification notes and non-sensitive ledger descriptions.
- Employee entitlements with duplicate-safe idempotency keys.
- Append-only leave credit ledger linked to entitlements and approved leave requests.
- Pending reservations derived from active pending applications.
- Submission-time balance validation and concurrent balance-row locking.
- Approval-time revalidation that excludes the request's own reservation and prevents duplicate debit.
- Revocation credits recorded as compensating ledger entries instead of deleting the approval debit.
- HR adjustments that cannot reduce credits below pending reservations.
- Annual allocation command with dry-run support, carryover limits, monthly proration, active-employment filtering, and duplicate protection.
- Historical reconciliation command with preview and explicit apply modes.
- Scheduled annual allocation on January 1 at 12:10 AM in the configured attendance timezone.
- Employee balance cards, approved/pending split, event reference, application projection, and personal ledger history.
- Supervisor visibility for assigned-employee credits and request-specific projected availability.
- HR policy history, event grants, adjustments, balance report, ledger drill-down, filters, and CSV export.
- Formula-injection protection and exclusion of private leave reasons and eligibility notes from CSV exports.
- Notifications for grants, adjustments, and approvals using the latest committed balance.
- Audit metadata for adjustments, approval debits, event grants, and revocation reversals.

## Automatic Credit Exhaustion Restriction

- Exhausted annual leave types remain visible on the application form with an unavailable label, disabled native controls, and a specific explanation.
- Credits fully reserved by pending applications are distinguished from permanently consumed credits.
- Event-based leave without an established entitlement displays eligibility-verification guidance instead of being described as exhausted annual leave.
- The application form shows total, approved, pending, available, requested, and projected balances using whole-number-aware formatting.
- The submit action is disabled when no eligible type is selected or the displayed request exceeds current available credits.
- Authoritative submission validation rejects zero-balance, reserved-balance, and insufficient-balance requests even when browser controls are tampered with.
- Validation errors report the leave type, requested days, and currently available days without creating a leave request, reservation, ledger entry, or notification.
- Reservation checks lock the employee balance row and perform a locking pending-request read in the same transaction, preventing simultaneous submissions from using a stale pending-credit snapshot.
- Supervisor review shows requested credits and the projected balance after approval while retaining the existing final approval workflow.
- HR adjustments and event grants automatically restore form availability on the next request or page refresh.

## Rollout Commands

```powershell
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan db:seed --class=LeaveCreditPolicySeeder
C:\xampp\php\php.exe artisan leave:reconcile-credits
C:\xampp\php\php.exe artisan leave:reconcile-credits --apply --actor=<HR_USER_ID>
C:\xampp\php\php.exe artisan leave:allocate-annual 2026 --dry-run
C:\xampp\php\php.exe artisan leave:allocate-annual 2026 --actor=<HR_USER_ID>
```

The reconciliation preview must be reviewed before `--apply`. The annual allocation command is idempotent and reports skipped duplicates.

## Verification

- `LeaveCreditManagementTest`: annual allocation, reservations, protected adjustments, append-only reversals, HR event grants, authorization, and CSV export.
- Existing balance-validation workflow test updated to arrange credits before submission under the new reservation contract.
- All 48 leave-related tests pass with 875 assertions when PHPUnit is run with the installed GD extension loaded.
- Blade templates compile successfully.
- PHP files formatted with the repository's required Pint profile.
- `LeaveCreditManagementTest` and `LeaveDaysTest`: 19 tests passed with 85 assertions, including exhausted selection, direct-request bypass, insufficient credits, pending reservations, and HR restoration.
- `PhaseFiveWorkflowTest`: 11 tests passed; 6 image/signature tests could not run because the local PHP CLI does not have the GD extension installed.
- Production Vite build and Blade template compilation completed successfully after the restriction changes.

## Operational Notes

- `employee_leave_balances` remains a locked projection for compatibility with the existing workflow. Entitlements and ledger entries provide the audit trail needed to reconcile it.
- The local database reconciliation mapped 10 historical approved Vacation/Sick requests to canonical balances and recorded duplicate-safe historical debit entries; a follow-up preview found no remaining candidates.
- Statutory/event policy values are configurable and effective-dated. HR remains responsible for verifying eligibility and supporting evidence.
- Run Laravel's scheduler continuously in production so the annual allocation job and existing maintenance jobs execute.
- Install or enable the PHP GD extension before running the complete image, signature, and PDF regression suite locally.
