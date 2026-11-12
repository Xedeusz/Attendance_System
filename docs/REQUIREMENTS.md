# Requirements and Acceptance Baseline

Date: 2026-11-12 (Asia/Singapore)  
Status: Phase 1 draft for company confirmation; no application implementation.

[Blueprint](SYSTEM_BLUEPRINT.md) | [Technology](TECH_STACK.md) | [Roadmap](DEVELOPMENT_ROADMAP.md)

## 1. Scope and terminology

One company, approximately 27 branches, centralized administration, responsive browser application. Users include Admin/Super Admin, Sub-Admin, HR Personnel, and Employee. An administrator who records personal attendance also requires an employee profile and employee permissions.

An **attendance attempt** is a request that may fail or expire. An **event** is an accepted time-in/time-out or approved correction action. A **record** is the derived attendance for one shift occurrence, not necessarily one calendar day. **Verification** means 1:1 comparison with the authenticated employee's authorized reference. **Scope** is the set of branches a user may access; role names alone do not grant company-wide access.

MVP means the first controlled production release after all required modules and security gates pass. Earlier milestones are development increments, not a production biometric system.

## 2. Functional requirements

| ID | Requirement | Acceptance evidence |
| --- | --- | --- |
| AUTH-01 | Secure login/logout, hashed passwords, throttling, session rotation and invalidation | Session fixation/replay tests; logout invalidates access; generic login failures |
| AUTH-02 | Authorized staff create accounts; activation and password reset use expiring single-use tokens | No public signup; replay/expiry and token enumeration tests; no shared default password |
| AUTH-03 | Deactivation blocks new and existing sessions | Each protected action checks account/employee state; remembered sessions and outstanding attempts revoked |
| AUTH-04 | Admin-configured roles, permissions, and branch access | Direct URL/API and cross-branch access denied even when IDs are known |
| AUTH-05 | Privileged MFA and secure recovery proposed for production | Enrollment/recovery tests; company confirms factor and operational process |
| EMP-01 | HR/Admin manage employee details, department, status, employee number, search/filter | Unique employee number; server validation; scoped pagination |
| EMP-02 | Effective-dated branch and schedule assignment | Transfer does not relabel historical records; overlap rejected under approved assignment policy |
| EMP-03 | Enrollment state visible without exposing references | Authorized status view; template download unavailable in ordinary HR UI |
| BIO-01 | Authorized, supervised or otherwise approved enrollment linked to verified employee identity | Notice/policy basis recorded; unauthorized or substitute enrollment denied |
| BIO-02 | Capture handles permission errors, quality, single-face checks, and retry guidance | No/multiple face, blur, poor light, camera denial/disconnection tested |
| BIO-03 | Server-authoritative 1:1 matching and evaluated liveness/PAD | Genuine provider integration; replay/photo/video attack tests; no client success flag accepted |
| BIO-04 | Version, revoke, replace, and delete enrollments under policy | Only one active reference version initially; replacement invalidates stale attempts; deletion evidence |
| BIO-05 | Typed verification logs and human fallback | Provider timeout/mismatch never creates automatic attendance; review path available |
| GEO-01 | Authorized branch coordinates and versioned radius/accuracy policy | Coordinates/radius validated; changes audited and effective-dated |
| GEO-02 | Capture fresh location with permission and accuracy; validate server-side | Invalid/missing/nonfinite/out-of-range values rejected; stale/poor-accuracy readings retried |
| GEO-03 | Assigned branch restriction and server distance decision | Employee cannot submit an arbitrary branch ID/radius to gain approval |
| GEO-04 | Treat uncertain/outside/unsupported readings explicitly | No automatic radius widening or silent bypass; review route does not imply off-site attendance approval |
| ATT-01 | Time In and Time Out for an authenticated active employee | Server receipt time, successful challenge, current assignment, and policy checks required |
| ATT-02 | Duplicate, replay, and concurrent submission prevention | Same idempotency key returns same outcome; two tabs cannot create two time-ins |
| ATT-03 | Shift-aware history, late/present/absent/incomplete classification | Approved test examples cover overnight, missing checkout, leave, holidays/rest days, and transfers |
| ATT-04 | Server clock authority; UTC storage with local display | Modified device time never changes accepted timestamp; clock health monitored |
| ATT-05 | Employee correction requests; scoped HR approval/rejection | Reason and evidence supported; no self-approval; original event preserved; concurrent review handled |
| ATT-06 | Failed/lost submissions have safe recovery | Pending status visible; status lookup prevents resubmitting blindly after timeout |
| LEAVE-01 | Submit configured leave types/dates/reason and optional supporting files | Date/type validation, overlap checks, private quarantined attachments |
| LEAVE-02 | Scoped HR review, decision reason, and history | No self-approval or unauthorized branch review; atomic decision and notification |
| LEAVE-03 | Approved leave affects attendance classification | Unapproved leave does not excuse absence; policy for partial days/attendance conflicts confirmed |
| BR-01 | Branch directory, department data, coordinates, geofence, staff assignments | Stable branch code; inactive branch cannot receive new assignments/attempts |
| BR-02 | Assigned HR/Sub-Admin branch grants | Grant/revocation audited; in-progress operations recheck scope |
| DASH-01 | Role-specific statistics and action queues | Counts use same scoped rules and filters as detail reports; no invented live data |
| REP-01 | Daily/weekly/monthly and employee/branch attendance reports | Date boundaries and totals tested; filter/pagination; daily-to-monthly reconciliation |
| REP-02 | Late/absent, leave, and correction reports | Scoped CSV export proposed; private short-lived retrieval; spreadsheet formula injection mitigated |
| NOT-01 | In-app leave/correction decisions and security notifications | Recipient authorization; no raw biometric/location or medical details in payloads |
| AUD-01 | Audit security and sensitive business actions | Actor/action/subject/scope/result/time/correlation recorded; restricted access; no secrets/captures |
| PRIV-01 | Privacy notices, lawful-basis record, rights handling, retention, deletion, fallback | DPO-reviewed artifacts and tested cleanup/provider deletion before real enrollment |

Account activation/reset require a verified delivery or controlled recovery process. Email for these security flows must be decided before their implementation; optional general email notifications remain excluded.

## 3. Role and permissions matrix

Legend: **All** = explicit company-wide permission; **Scoped** = separately granted branches; **Own** = personal data; **Conditional** = additional named permission/approval; **No** = denied by default.

| Capability | Admin / Super Admin | Sub-Admin | HR Personnel | Employee |
| --- | --- | --- | --- | --- |
| System settings, branch creation | All | Conditional, scoped edits only | No | No |
| Manage roles and grant scope | All; protected grants | No by default | No | No |
| Account provisioning/deactivation | All | Conditional / Scoped | Scoped employee accounts | No |
| Employee profiles and assignments | All | Conditional / Scoped | Scoped | Own limited view |
| Supervise enrollment/re-enrollment | Conditional | Conditional / Scoped | Scoped with enrollment permission | Own participation only |
| View/download biometric references | No routine UI | No | No | No |
| Record attendance | Own, if employee | Own, if employee | Own, if employee | Own |
| View attendance/dashboard/reports | All | Conditional / Scoped | Scoped | Own history |
| Submit leave/corrections | Own, if employee | Own, if employee | Own, if employee | Own |
| Decide leave/corrections | Conditional, no self-approval | Conditional / Scoped, no self-approval | Scoped, no self-approval | No |
| Export company records | Conditional | Conditional / Scoped | Conditional / Scoped | No bulk export |
| View security audit logs | Conditional, designated security access | Conditional restricted subset | Relevant business history only | Own request history |
| Process data rights/deletion | Designated privacy workflow | No by default | Conditional, minimum necessary | Submit own request |

Super Admin manages the system but does not automatically gain a biometric dump or permission to approve personal requests. Prevent deletion/deactivation of the last active Super Admin. No privilege escalation through employee forms, mass assignment, or self-edited grants. Role/branch permissions are additive only within explicit scope; an ordinary HR role is not inherently global. Every list, detail, attachment, count, export, queued job, and download checks scope.

## 4. Non-functional requirements

All numeric values below are **proposed acceptance targets**, not measured results or contractual promises.

| ID | Area | Target / validation |
| --- | --- | --- |
| NFR-01 | Performance | Ordinary scoped pages/API p95 server response <= 2 seconds under an agreed load; verification target <= 10 seconds after capture excluding user interaction, confirmed by pilot |
| NFR-02 | Capacity | Benchmark 27-branch simultaneous shift-start scenario after headcount/peak window confirmed; include retries and biometric quotas, not just steady traffic |
| NFR-03 | Availability | Proposed 99.5% monthly service availability; define exclusions/support ownership with company |
| NFR-04 | Recovery | Proposed RPO <= 24 hours and RTO <= 4 hours; approve and test restores before deployment; increase backup frequency if business requires |
| NFR-05 | Consistency | Transactions, uniqueness, idempotency, and serialized state changes; exactly one accepted in/out of each type per shift in MVP |
| NFR-06 | Accessibility | WCAG 2.2 AA target; keyboard/focus, labels, contrast, errors, announcements, reduced motion; accessible alternative for failed biometric capture |
| NFR-07 | Responsive support | Usable at 320 CSS px through desktop; no full-page horizontal overflow; targets >=44 px proposed for primary attendance controls |
| NFR-08 | Browser support | Test current Chrome/Edge/Firefox/Safari and real Android/iOS devices; minimum versions finalized from inventory and CSS/SDK requirements |
| NFR-09 | Security | No unresolved critical/high findings without documented remediation and accountable risk decision; negative authorization and upload tests mandatory |
| NFR-10 | Privacy | No continuous location tracking; capture only on action; no raw biometric data in logs, analytics, URLs, notifications, or source control |
| NFR-11 | Maintainability | Laravel conventions, small services, typed provider contract, migrations/seeders, reviewable changes, CI and meaningful major-feature tests |
| NFR-12 | Observability | Correlation IDs, clock drift, provider error rates/latency, queue lag, DB health, denied access, backup and cleanup failure alerts without sensitive payloads |
| NFR-13 | Usability | Permission guidance, progress, retry/status lookup, helpful errors, empty states, slow-network support; never announce success before server commit |

## 5. Initial attendance policy proposals

- One effective primary branch and one planned shift occurrence per employee at an instant; changes are effective-dated. Supporting concurrent assignments/split shifts needs confirmation.
- One time-in and one time-out per shift occurrence; break punches, payroll computations, and overtime approvals excluded initially.
- Overnight shifts belong to the local date on which the shift starts. Shift start/end stored as concrete UTC instants with a historical policy snapshot.
- Late, absent, rest day, on leave, and incomplete are separate classifications; absence is finalized only after the applicable cutoff and leave reconciliation.
- No automatic time-out fabrication. Missing checkout is incomplete and enters correction review.
- Server-received attempt time is authoritative only when verification completes within approved freshness bounds. Record commit time separately.
- No offline automatic attendance or default off-site bypass. Operational failures go to documented review; denial alone does not establish misconduct or justify automatic pay deductions.
- Initial candidate freshness values: challenge 120 seconds; location target age <=30 seconds; location accuracy <=50 metres. These are configurable pilot proposals, not approved thresholds or proofs of presence.
- Face and PAD thresholds must be calibrated for the selected provider and representative pilot; no universal percentage is approved.

## 6. Scope boundaries

Required MVP: authentication/recovery, branch-scoped RBAC, employees/departments/schedules, genuine enrollment and facial/PAD verification, geofenced time-in/out, corrections, leave, basic dashboards/reports/CSV, in-app notifications, audit/privacy controls, deployment and recovery testing.

Future, only on explicit approval: payroll integration, advanced analytics, general email notifications, additional reporting formats/integrations, authorized off-site attendance, native apps, offline attendance, and additional punch types. Google Maps is a conditional integration; manual coordinate administration is sufficient for geofencing.

## 7. Company confirmation register

| ID | Decision needed | Owner / blocked work |
| --- | --- | --- |
| Q-01 | Employee count, concurrent arrivals, branch internet/device inventory | Operations / sizing and compatibility |
| Q-02 | Official title/branding, legal entity, branch list, coordinates, department hierarchy | Sponsor/HR / master data |
| Q-03 | MySQL vs MariaDB, hosting region, budget, recovery and availability targets | IT/sponsor / environment |
| Q-04 | Primary/multiple/temporary branch assignment, transfers, current vs historical manager access | HR/security / scoped data model |
| Q-05 | Shifts, grace period, early arrivals, overnight/rotating/split shifts, breaks, overtime, holidays, rest days | HR / attendance rules |
| Q-06 | Check-in/out windows, missing shift, late checkout, leave/attendance conflicts, absence cutoff | HR / attendance classification |
| Q-07 | Leave types, partial days, balances/accrual (if needed), overlap, cancellation, approver delegation and escalation | HR / leave model |
| Q-08 | Correction evidence, submission deadline, one/two-level approval, self-approval alternatives | HR/security / correction workflow |
| Q-09 | Branch radii, acceptable accuracy, indoor/boundary handling, false-rejection support | Operations / geofence policy |
| Q-10 | Biometric budget/provider, licensing, region/transfer terms, PAD evidence and acceptance criteria | Sponsor/IT/DPO / biometric procurement |
| Q-11 | Identity checks for enrollment, enrollment supervisors, re-enrollment and deactivation process | HR/security / enrollment |
| Q-12 | Data classification, lawful basis per purpose, PIA, notices, processor contracts, NPC registration duties | DPO/legal / real personal-data processing |
| Q-13 | Retention by category, legal holds, provider deletion, rights requests, backup expiry | DPO/HR/IT / production data lifecycle |
| Q-14 | Non-biometric/permission-denied/disability/device/outage alternatives and review SLA | HR/DPO / equitable rollout |
| Q-15 | Login identifier, activation/reset delivery, privileged MFA, session lifetime, trusted recovery | IT/security / authentication |
| Q-16 | Admin/Sub-Admin/HR grants; who approves HR/Admin personal requests | Sponsor/security / permission seeding |
| Q-17 | Notification recipients, required report layouts/export formats, authorized recipients | HR / reports and notifications |
| Q-18 | Google Maps billing/keys, whether visual maps are necessary | Sponsor / optional map integration |
| Q-19 | Incident responders, patch/backup owners, support hours, pilot branches and rollout sign-off | IT/sponsor / launch |

Open decisions do not authorize implementation defaults for sensitive policies. Resolve relevant questions before their phase; planning is complete when proposals and unknowns are recorded, not when all company policies have been invented.
