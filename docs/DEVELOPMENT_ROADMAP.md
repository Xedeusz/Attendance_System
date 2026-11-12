# Development roadmap

Updated 2026-11-12. Phase 4 explicitly replaces the original facial-biometric architecture with **live camera photo evidence + reported GPS + server Time In/Out**. Exactly HR and Employee roles exist. Earlier biometric/provider/enrollment phases are removed, not queued for later implementation.

| Phase | Status | Scope / verification |
| --- | --- | --- |
| 1 — Planning | Complete, updated for approved scope | Architecture, requirements, technology and confirmation register |
| 2 — Foundation | Complete | Laravel/Fortify, database configuration, responsive shell/login and checks |
| 3 — HR and employees | Complete | Two roles, employee/master-data administration, activation, historical assignments and backend authorization |
| 4 — Camera/GPS attendance | Complete; physical-device/company rollout gates remain | Capture lifecycle, real Time In/Out, private evidence, geofence, HR review, history/dashboard/feedback, retention and automated tests |
| 5 — Scheduling and controlled corrections | Proposed next; not authorized/started | Confirm rules first; refine open-session/missing-punch handling without altering original evidence |
| 6 — Leave and reconciliation | Not started | Explicit leave policies/applications/approvals and private attachments |
| 7 — Reports and notification expansion | Not started | Approved reports/exports and useful workflow notifications |
| 8 — Production hardening and pilot | Not started | Real-device/peak-load/security/privacy/restore checks and staged rollout |

Later phase numbering has been revised because Phase 4 now implements attendance/geofencing together. Request approval of the next phase's concrete scope before implementation. Do not automatically execute it.

## Phase 4 completed increment

1. Inspected existing authentication, two-role model, master data, assignments, components and MariaDB; ran the 65-test baseline.
2. Added six attendance/support tables and employee attendance version through an incremental migration.
3. Added lifecycle, capture/session/replay protection, server geofence and private image validation/storage services.
4. Connected real camera/GPS UI, Time In/Out, own history and protected evidence.
5. Added HR monitoring/review/assistance disposition and actual dashboard/review updates.
6. Added retention scheduling, feature/unit/browser coverage and Phase 4 documents.

Automated completion evidence is in [test results](PHASE_4_TEST_RESULTS.md). Physical Android/iPhone/webcam/GPS and production privacy/hosting checks remain explicit rollout tasks; they are not claimed as passing.

## Proposed Phase 5 — scheduling and controlled correction requests

Prerequisites: HR approves shift definitions, work-date assignment, grace, holidays/rest days, missing-punch remedy, correction deadlines/review delegation and audit/retention policy.

Work: add schedule templates and dated employee schedule assignments using migrations; preserve historical shift policy snapshots; derive attendance classifications from approved schedules without rewriting original event facts. Implement employee correction requests, required evidence/reason, HR review with no self-approval and append-only adjustment history. Define support-to-correction handoff explicitly. Preserve original Time In/Out and GPS outcomes.

Tests: overnight and boundary shifts, transfers, missing/duplicate punches, concurrent decisions, stale requests, rejected corrections, owner/HR access, no self-review, audit integrity and clock handling. Exit: HR-reviewed examples reconcile to expected results and regressions pass. No payroll calculations.

## Phase 6 — leave

Confirm types, dates/partial days, overlap, approvers/delegation, cancellation, attachment requirements and access restrictions first. Build applications/status/history/HR decisions and reconcile approved leave with schedules/attendance without erasing events. Secure uploaded documents privately with appropriate type/size/content checks and retention. Tests cover overlap/concurrency, sensitive document access, rejection/cancellation and attendance conflicts. Balances/accrual are included only if specifically approved.

## Phase 7 — reports and notifications

Approve report definitions and recipients before implementing daily/weekly/monthly/employee/branch/late/absent/leave/correction outputs. Paginate/query by index, authorize every export, minimize photo/location inclusion, defend spreadsheet formula injection and record sensitive exports in audit. Expand the existing refresh-based in-app feedback only where useful; external email providers require explicit approval. Verify report totals from known schedules and real event rules.

## Phase 8 — production hardening and pilot

Resolve the [company register](REQUIREMENTS.md), publish privacy notice and complete the DPO-led assessment/retention/accessibility process. Run real-device HTTPS tests, burst/concurrency/deadlock tests on the target DB, dependency review, penetration testing and backup/APP_KEY restore drills. Define monitoring, scheduler ownership, incident response, support and rollback. Pilot selected branches; evaluate indoor GPS and false rejection rates. Roll out progressively to the actual branch inventory after sign-off. No public deployment was performed in Phase 4.

Future only on explicit instruction: payroll, advanced analytics, extra integrations/reports, paid maps, authorized off-site attendance, offline mode and native apps. Keep credentials/evidence/DB dumps out of Git and use synthetic fixtures for automation. Each phase requires its own meaningful tests and completion record.
