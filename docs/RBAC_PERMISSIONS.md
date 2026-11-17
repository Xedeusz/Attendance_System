# RBAC permissions — current Phase 5
Exactly three fixed roles: HR (1), Employee (2), Supervisor (3). This supersedes earlier two-role descriptions in historical phase reports. No Admin, Super Admin or additional role is introduced. Public registration is disabled.

| Capability | HR | Supervisor | Employee |
| --- | --- | --- | --- |
| Employee/branch/department/HR account administration | Company-wide | Denied | Denied |
| Provision Supervisors and manage effective assignments | Yes, recent password confirmation | Denied | Denied |
| Manage leave types/policies/balance ledger | Yes | Denied | Denied |
| View leave applications | Company-wide monitoring | Current explicit assigned scope | Own only |
| Apply/view own leave and balances | Eligible linked profile | Eligible linked profile; another Supervisor reviews | Eligible linked profile |
| Final leave approve/reject | No ordinary decision endpoint | Current assigned scope, no self-review | Denied |
| Private leave document retrieval | Administrative access | Current authorized request scope | Own request |
| Attendance capture/history/photos | Company-wide monitoring, own capture if eligible | Denied, including own | Own only |
| Correction submission | Own eligible profile | Denied | Own eligible profile |
| Correction approval/rejection | Another eligible HR; no self-review | Denied | Denied |
| HR reports/general audit/system administration | Yes | Denied | Denied |
| Personal password/session/profile utilities | Own | Own | Own |
| Notifications | Own admin/self notices plus attendance review queue | Leave category only within leave module | Own notices and attendance updates |

Middleware enforces active account/session version and Supervisor route restrictions. HR routes use explicit RequireHR middleware; sensitive account/assignment/balance/type mutations require recent password confirmation. Laravel policies, FormRequests and scoped queries independently enforce record access. IDs supplied by a client do not grant ownership/review permission. Services reload actors inside transactions and recheck eligibility/current assignment before mutation.

Supervisor menus contain only leave and personal utilities. Status or role changes revoke sessions/remember tokens/reset credentials. Existing Employee promotion reuses identity and history. All significant changes create existing AuditLog entries; private reasons/document contents are excluded from audit metadata.


## Direct Supervisor department assignment

HR can now link a Supervisor directly to one existing active department, independently of an Employee profile. New employee assignments and leave scope require both explicit effective assignments and matching active departments. Existing history is preserved; missing/mismatched links require explicit HR resolution. See [Supervisor department linking](SUPERVISOR_DEPARTMENT_LINKING.md) for schema, conflict handling, authorization and verification.
