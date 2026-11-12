# Two-Role Authorization

Effective Phase 3. **HR** and **Employee** are the only roles. HR serves as system administrator for every company branch. Earlier multi-role and branch-grant designs are superseded.

`roles` contains ID 1 / `hr` and ID 2 / `employee`; code is a database-constrained two-value enum and unique. `users.role_id` references the role with a default of Employee. Role rows have no web mutation routes. The repeatable seeder creates only these two records. No extra permission package is installed.

| Capability | HR | Employee |
| --- | --- | --- |
| HR dashboard/company counts | Yes | 403 |
| Employee list/create/detail/edit/status | Company-wide | 403 |
| Branch and department management | Company-wide | 403 |
| Provision HR accounts | Yes, recent password confirmation | 403 |
| HR account status/resend | Yes, recent password confirmation | 403 |
| Audit viewing | Read only | 403 |
| Development mail previews | Local only + recent password confirmation | 403 |
| Own employee dashboard/profile | Yes, same login; profile optional until linked | Yes |
| Update own contact number | If linked to employee profile | Own only |
| Change own password | Correct current password required | Correct current password required |
| Set own role/branch/employment/account status | Prohibited through profile | Prohibited |
| Activate invited account | Valid invitation, eligible pending account, signed out | Same |
| Create account without HR | First HR via restricted CLI bootstrap only | No public signup |
| Personal attendance/history/photos | Own eligible linked employee | Own eligible linked employee |
| Company attendance/photos/reported coordinates | Company-wide | 403 |
| Review attendance exceptions | Pending only; no self-review | 403 |
| Attendance assistance disposition | Required remarks; no self-disposition; no punch created | Own request only |
| Original attendance time/photo/GPS edits | No endpoint | No endpoint |
| Leave | Unavailable | Unavailable |
| Company reports | Protected placeholder only | 403 |

## Enforcement layers

1. Fortify accepts only active, employment-eligible accounts.
2. Web middleware rechecks account state and session version, including remembered sessions.
3. `auth` and `auth.session` protect workspace endpoints.
4. `hr` middleware protects HR routes and legacy directory aliases, before sensitive route binding/rendering.
5. `EmployeePolicy` authorizes directory/create/update; view permits HR or the owning user. Self-service routes derive the profile from the authenticated user, never a client-supplied employee ID.
6. HR Form Requests validate and reject protected attributes. Profile requests permit only contact number; unknown nonvalidated fields never reach persistence.
7. `Administration` locks the HR role row and rechecks the actor's current HR/active state before administrative writes. This prevents a concurrent deactivation from leaving a stale in-flight writer authorized.
8. Additional HR provisioning, HR status changes, and local mail require password confirmation within five minutes. The confirmation endpoint is rate limited.

Directory search/filter/sort values are validated and SQL values are bound; sort columns/directions are allowlisted. Hiding navigation is a convenience, never the access boundary. Authenticated employees receive 403 for HR pages/actions. Public registration GET/POST returns 404. Guest protected requests redirect to login.

## Last-HR and bootstrap safeguards

The first HR command is CLI-only, never routed, requires private interactive credentials, and refuses once any HR account exists. It creates the first active HR account without modifying existing users. Additional HR accounts are pending until activated.

All normal HR deactivations use the same serialized guard. The last active HR cannot be deactivated, including through a linked employee's status action or employment edit. Role changes are not exposed. HR self-service does not allow protected-field changes.

Company-wide HR scope is intentional. There is no implicit empty-branch-list behavior, staff branch grant model, or hidden third role. HR uses the same account for personal attendance when linked to an eligible employee profile. Phase 4 attendance mutations share the administrative mutex, recheck active state and validate capture ownership/current assignment; evidence/details use `AttendanceRecordPolicy`. No public attendance photo URL exists.

See [tests](PHASE_3_TEST_RESULTS.md) for the positive and negative cases actually verified.
