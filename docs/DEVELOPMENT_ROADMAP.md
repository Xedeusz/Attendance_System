# Development roadmap
Updated 2026-11-16 for the final Phase 5 instruction. Exactly HR, Supervisor and Employee roles exist. The Supervisor is the final leave approver. This replaces earlier proposals placing scheduling in Phase 5, leave in Phase 6 or HR after Supervisor review.

| Phase | Status | Scope |
| --- | --- | --- |
| 1 — Planning | Complete, updated | Blueprint, requirements, stack and policy confirmation register |
| 2 — Foundation | Complete | Laravel/Fortify, existing database, authentication and responsive shell |
| 3 — Employee/branch administration | Complete | HR administration, secure activation, master data, historical assignments and audit |
| 4 — Camera/GPS attendance | Complete; physical pilot remains | Server punches, private original evidence, geofence, HR exception review, monitoring/history |
| 5 — Supervisor, leave and corrections | Implemented | Three roles; HR-only Supervisor provisioning; dated explicit assignments; final Supervisor approval; configurable balances/private documents; Employee-to-HR attendance corrections; persistent notifications and regression coverage |
| 6 — Reports and analytics | Recommended next, not implemented | Confirm official report definitions/access, filtered attendance/leave/correction summaries and authorized safe exports |
| 7 — Confirmed scheduling/policy automation | Future, separately authorized | Approved shifts/holidays, leave duration automation, classifications, accrual/reversal policy where authorized |
| 8 — Production hardening and pilot | Future | Physical devices, peak concurrency, security/privacy, backup restore, operational ownership and staged rollout |

Phase 5 details and exact inventory: [implementation](PHASE_5_IMPLEMENTATION.md), [test evidence](PHASE_5_TEST_RESULTS.md), [permissions](RBAC_PERMISSIONS.md). Existing Phase 3/4 reports remain historical records of their original two-role scope.

## Phase 6 recommendation
Obtain HR-approved definitions, recipients, date/time rules, report totals and export fields before implementing Reports and Analytics. Start from existing server attendance facts, clearly labeled manual correction overlays and final leave decisions. Do not invent late/absent/overtime classifications without schedules. Authorize each report/export, minimize photos/GPS/reasons, prevent spreadsheet formula injection and audit sensitive exports. Reconcile sample totals and test role/record scope and pagination.

Scheduling, payroll, automated entitlement accrual, approved-leave reversal, off-site attendance, paid APIs, offline/native apps, public deployment and external notifications require separate authorization. No Phase 6 code or public deployment is included in Phase 5.

Phase 5 also includes the [Leave Calendar](LEAVE_CALENDAR.md). It displays recorded leave and overlap counts. Work scheduling remains a separate phase.
