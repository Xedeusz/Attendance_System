# Development Roadmap and Progress Tracker

Date: 2026-11-12 (Asia/Singapore)  
Current phase: **Phase 2 — Laravel/authentication/UI foundation implemented and tested; awaiting next instruction.**

Phase 2's explicit user request expanded its original scope to include basic Fortify authentication and safe module/dashboard previews. Those foundation tasks moved forward from Phase 3; complete RBAC, branch permissions, account activation/deactivation, MFA and privileged recovery remain pending. See [setup](PHASE_2_SETUP.md) and [verification](PHASE_2_TEST_RESULTS.md). No Phase 3 business functionality was started.

[Blueprint](SYSTEM_BLUEPRINT.md) | [Requirements](REQUIREMENTS.md) | [Technology](TECH_STACK.md)

## Operating rules

Each major phase begins only after the user requests it. Finish its agreed deliverables, run appropriate checks, report results and limitations, update this tracker, and stop. Completing one phase is not permission to start the next. Resolve relevant company questions before dependent work; record decisions rather than silently turning proposals into policy.

Keep changes small and reviewable. Use exact paths when implementing, conventional migrations/seeders, synthetic fixtures, and major-feature tests. Never commit secrets, biometric evidence, private documents or database dumps. Explain destructive commands before running them. Optional enhancements require explicit approval.

## Progress

| Phase | Status | Dependency / output |
| --- | --- | --- |
| 1. System planning | Documentation complete; company review pending | Four planning documents; no application implementation |
| 2. Environment and Laravel foundation | Implemented and tested; manual/production gates documented | Laravel/Fortify, MariaDB migrations, compiled responsive UI, tests and setup docs |
| 3. Authentication and scoped access | Not started | Phase 2; account/recovery/access policy |
| 4. Master data and schedules | Not started | Phase 3; branch and attendance policy inputs |
| 5. Attendance core and corrections | Not started | Phase 4; approved state/approval rules |
| 6. Geofencing | Not started | Phase 5; branch radii/accuracy and device policy |
| 7. Biometrics and integration | Not started | Phases 3–6; licensing, DPO, provider and pilot gates |
| 8. Leave and reconciliation | Not started | Phase 4/5; approved leave rules |
| 9. Dashboards/reports/notifications | Not started | Prior data workflows and reporting specifications |
| 10. Integrated assurance | Not started | All required features; measurable acceptance plan |
| 11. Pilot and production rollout | Not started | Security/privacy/operations readiness and company approval |

No calendar duration is promised before team capacity, employee volume, hardware/provider evaluation, and company review availability are known.

## Phase 1 — system planning

Deliverables: architecture and Mermaid diagrams; stack decisions/options; functional/non-functional requirements and role matrix; preliminary schema/workflows; security/privacy design; UX plan; MVP boundaries; challenges; confirmation register and this roadmap.

Exit evidence: all four requested documents exist, local links are checked, proposal/unknown/implemented status is explicit, and the existing `index.php` is unchanged. Stop here in the current task.

## Phase 2 — environment and Laravel foundation

Entry: explicit instruction; confirm Q-03 deployment/DB choice, runtime compatibility and development tools. Obtain network/install approval where the environment requires it.

Work:

1. Inventory CLI/web PHP, Composer, Node/npm, Apache, actual DB engine/version, and Git state.
2. Preserve the login prototype and existing work; plan a reviewed scaffold location/migration without destructive overwrite.
3. Initialize the chosen supported Laravel version; configure local HTTPS, `public/` web root, Vite/Tailwind/Alpine and environment templates.
4. Configure a dedicated least-privilege local database account and synthetic test environment. Never put credentials in tracked files.
5. Establish base Blade layouts/components, error pages, code style and minimal CI. Keep real authentication UI integration for Phase 3.

Tests: boot and route smoke checks, PHP/package compatibility, asset build, test DB isolation, private/config path denial, phone HTTPS reachability. Migrations only start in an authorized implementation phase.

Exit: reproducible setup instructions and working clean foundation; exact commands/files and results documented. No biometric/provider setup yet.

## Phase 3 — authentication and branch-scoped authorization

Entry: approve Q-15/Q-16 login identifier, activation/reset delivery, privileged MFA, role/scope and recovery policies. Some branch scaffolding is required here for authorization tests; full branch UI follows in Phase 4.

Work: Laravel-backed login/logout; activation/reset tokens; inactive account/session checks; rate limits and session security; no public registration; role/permission/branch grants; policies; protected dashboards; secure initial administrator provisioning without seeded default passwords; redacted audit trail. Migrate the prototype into Blade and compiled styles.

Tests: valid/invalid login, throttling, CSRF, fixation/logout/reset/deactivation revocation, expired/replayed tokens, generic account recovery responses, last-admin protection, direct endpoint and cross-branch denials, privilege escalation, privileged recovery/MFA flows.

Exit: approved permission matrix enforced server-side; no public employee signup; recovery delivery works securely in the selected environment.

## Phase 4 — branches, employees, schedules and calendars

Entry: Q-02/Q-04/Q-05/Q-06/Q-11 master data, branch/schedule policy and historical access decisions.

Work: employee/department/branch migrations and forms; search/filter/pagination; account/profile linkage; staff scope grants; effective-dated assignments; versioned geofences; shift templates/versions and occurrences; rest/holiday calendar; enrollment status placeholder (not simulated verification); HR identity-check procedure. Use synthetic seeders, not real personal records in source control.

Tests: uniqueness/FKs, unauthorized and cross-branch CRUD, assignment overlaps, archived branches/employees, transfer history, overnight and calendar examples, concurrent assignment changes, validation/accessibility and mobile layout.

Exit: reviewed schema/migrations and scoped master data; historical assignment semantics demonstrated.

## Phase 5 — attendance core and correction workflow

Entry: approve late/absence/incomplete rules, server receipt timestamp policy, idempotency/freshness windows, and Q-08 review policy.

Work: attempt/challenge lifecycle; shift-aware time-in/out state machine; append-only events and derived records; idempotency/short locking transactions; status query after lost response; server clock/UTC conversion; incomplete/absence reconciliation; corrections and decision history; audit/outbox.

Until Phase 7, automatic biometric attendance remains disabled outside an isolated test environment. Explicit test doubles can validate orchestration but must not present themselves as real verification or permit production approval. The real provider contract must be required by the production attendance path.

Tests: double-click/two-tab/concurrent punches on MySQL, replay/expiry, different payload with reused key, clock manipulation, overnight checkout, pending attempt/lost response, rollback, correction race/self-approval, leave/calendar reconciliation contracts, account/assignment change during verification.

Exit: reliable state transitions and negative tests; production biometric path cannot be bypassed by a fake result.

## Phase 6 — GPS geofencing

Entry: Q-09 accuracy/radii/boundary and Q-14 failure alternatives approved; HTTPS test devices available. Q-18 required only if visual mapping is selected.

Work: permission-based location module; bounded freshness/retry UI; backend distance/uncertainty evaluator; stored version references; encrypted short-retention coordinate evidence; optional approved map editor with restricted keys/quotas. Document residual spoof risk.

Tests: known distances and antimeridian cases; invalid/nonfinite inputs; zero/negative/poor accuracy; inside/outside/uncertain boundary; cached/future-reported readings; arbitrary branch/radius injection; policy changes; indoor/desktop/Android/iPhone field tests; denial/timeout/offline.

Exit: approved field results and clear review path; no radius auto-expansion or absolute presence claim.

## Phase 7 — provider evaluation, enrollment and genuine biometrics

Entry: Q-10/Q-11/Q-12/Q-13/Q-14 privacy, procurement, licensing, lawful-basis, retention and fallback gates satisfied before any real enrollment. Obtain explicit approval for any paid provider or frontend-stack exception.

Work:

1. Compare commercial candidate and licensed self-hosted alternative using documented criteria and projected check volume/cost.
2. Validate target-browser capture SDK, service contract, quality/PAD controls, region/transfer terms, model licenses and deletion behavior.
3. Run an approved representative evaluation; establish match/PAD policy thresholds, failure handling and review SLA. Security includes presentation attacks and injection limits.
4. Build authorized identity-linked enrollment, encryption/key lifecycle, versioning/revocation/re-enrollment and cleanup.
5. Connect server-fetched provider results to the attendance orchestrator; bind subject/action/session/evidence and consume results once.
6. Test compensating deletion for failed enrollments and process vendor outages without approving unverified attendance.

Tests: no/multiple faces, poor quality, mismatch, print/photo/video replay and injection scenarios in the agreed test plan, stale/forged/wrong-subject/provider responses, expired sessions, re-enrollment races, quota/timeout/callback replay, provider contract failures, unauthorized reference access, deletion and log redaction. Measure representative false-match/non-match rates and latency; do not infer universal accuracy from vendor marketing.

Exit: documented provider decision and licensed/PAD-capable integration with approved pilot evidence. Failed checks have an equitable reviewed alternative; test providers cannot be selected in production.

## Phase 8 — leave and attendance reconciliation

Entry: Q-07 leave types/units/overlap/cancellation/balance policy and approver delegation decisions. Balance/accrual is not implemented by assumption.

Work: leave types, applications/days, quarantined attachments, scoped reviews, state history and employee status; reconcile approved leave with scheduled absence and existing attendance; implement narrow medical-document access.

Tests: overlap/concurrent approvals, self-review and scope denial, partial/overnight/calendar examples, malicious MIME/oversize files and failed scans, private download denial, rejection reasons, notification after commit, conflicting attendance/leave review.

Exit: approved HR cases reconcile without deleting original attendance facts.

## Phase 9 — dashboards, reports and notifications

Entry: Q-17 report definitions/layouts/recipients agreed; underlying classifications stable.

Work: role-specific scoped cards/charts, essential daily/weekly/monthly/employee/branch/late/absent/leave/correction reports; pagination/filtering and CSV; in-app workflow/security notifications; private expiring exports. Security recovery messages were handled in Phase 3; general email notifications remain future scope.

Tests: counts match drilldowns, branch filters enforced in background jobs and downloads, permission recheck after revocation, timezone/date boundaries, large range limits, formula injection, duplicate job delivery, personal recipient isolation, accessible charts/table alternatives and mobile navigation.

Exit: HR reconciles sample totals; no sensitive data leakage via aggregate counts or export links.

## Phase 10 — integrated assurance and operational readiness

Entry: all required MVP workflows integrated; company confirms measurable service/load/recovery targets (Q-01/Q-03/Q-19).

Work: integrated threat-model review, dependency/security scanning, penetration/access-control tests, privacy/retention/provider deletion and data-rights drills, secret/key rotation, real-device accessibility testing, shift-start load tests, clock drift/provider/queue monitoring, backup/restore and incident runbooks.

Tests: combined end-to-end attendance/leave/correction scenarios; cross-branch data exposure on every surface; malicious files/requests; concurrent bursts/provider throttling; worker crash/retry; DB rollback/deadlock; restore with keys and deletion ledger; simulated reportable incident escalation. Run synthetic data in automated environments.

Exit: no unresolved release-blocking findings, acceptance evidence recorded, DPO/security/HR review, documented residual risks and operational ownership. Security built in earlier phases is revalidated here.

## Phase 11 — controlled pilot and production rollout

Entry: company authorizes hosting/provider costs, real data, pilot branches, notices/enrollment process, support/fallback, and go-live responsibilities. Deploy/publish only with the required explicit authorization.

Work: production TLS/private DB/storage, least-privilege identities and keys, supervised jobs/scheduler, monitoring/alerts, encrypted backups, trained HR/admins, approved employee onboarding and pilot. Record genuine acceptance/rejection/uncertainty metrics without unnecessary evidence retention. Expand branch by branch after pilot acceptance.

Tests: production configuration smoke tests, no debug/config/private file exposure, real-device pilot workflows, restore/recovery drill, provider outage/fallback, alert delivery, deployment rollback with migration compatibility and data preservation.

Exit: sponsor/HR/IT/DPO sign-off, measured pilot acceptance, tested recovery, support and incident ownership, operational documentation and backlog. Do not announce production readiness before evidence exists.

## Future backlog — not part of authorized MVP implementation

Payroll integration; advanced analytics; general email notifications; additional report formats/integrations; authorized off-site attendance; offline mode; native apps; break punches; expanded scheduling or balance/accrual features if not required by confirmed policy. Each requires its own requirements/security/privacy review and explicit instruction.

## Reporting template for each implementation phase

- Completed files/features and purpose.
- Decisions made and company questions resolved.
- Tests run, actual results, and material gaps.
- Security/privacy implications and migration/rollback considerations.
- Remaining work and current phase status.
- Stop and await instruction before the next major phase.
