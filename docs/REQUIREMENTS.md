# Requirements and acceptance baseline

Updated 2026-11-12 through Phase 4. This document supersedes the original facial-biometric proposal. Title: **Web-Based Employee Attendance and Management System with Live Camera Photo Capture and GPS Geofencing**. Approximately 27 company branches, centralized web application, exactly HR and Employee roles. HR is the administrator across all branches.

## Functional acceptance

| ID | Requirement | State / acceptance evidence |
| --- | --- | --- |
| AUTH-01 | Fortify login/logout, hashed passwords, throttled/session-protected actions | Implemented and regression tested |
| AUTH-02 | HR-created employee accounts, private activation/password choice, no public registration | Implemented Phase 3; no employee default password |
| AUTH-03 | Account/employment deactivation and session revocation | Implemented; writes recheck fresh account state |
| RBAC-01 | Exactly HR/Employee and backend-enforced permissions | Implemented; HR middleware and owner policies |
| EMP-01 | Register/edit/search employees, departments, active status, primary branch/history | Implemented Phase 3 |
| BR-01 | Real branch details/coordinates/radius and status | Implemented; no fabricated branches or geofences |
| ATT-01 | Live-camera-only ordinary UI, preview/capture/retake/switch/cleanup | Implemented getUserMedia; no file/gallery control |
| ATT-02 | One-shot GPS, reported accuracy/timestamp, backend Haversine/freshness/assignment checks | Implemented; no continuous tracking |
| ATT-03 | Server-timestamped Time In/Out with private photos | Implemented; separate capture/location per action |
| ATT-04 | Overnight sessions, retries, duplicates and stale tabs | Implemented; transactions, unique constraints, capture binding/version/fingerprint |
| ATT-05 | Employee own history/photos and HR company-wide filtered monitoring | Implemented, paginated and policy-protected |
| ATT-06 | Explicit review requests for technically valid evidence that fails location/repeat checks | Implemented; no automatic failed-GPS approval |
| ATT-07 | HR approve/reject pending events with remarks/confirmation/audit | Implemented; no self-review or original timestamp/GPS overwrite |
| ATT-08 | Camera/session/device assistance | Implemented support request and HR disposition only; creates no punch |
| ATT-09 | Session, verification and classification kept distinct | Implemented; classification unused; no inferred absence/lateness |
| ATT-10 | Real dashboard counts and in-app exception updates | Implemented; refresh-based lists, no external provider |
| PRIV-01 | Encrypted private evidence, authorized retrieval, configurable retention | Implemented technical controls; company/DPO approval and operational processes pending |
| AUD-01 | Attendance submissions/validation/flags/reviews/assistance/purge logs | Implemented using existing audit system |
| SCH-01 | Schedules, grace/holiday/late/absent/undertime rules | Deferred pending confirmed HR policy and instruction |
| COR-01 | Formal missing-punch/timestamp correction with approval history | Deferred; assistance is not correction |
| LEAVE-01 | Leave types/applications/private supporting documents/approvals | Deferred |
| RPT-01 | Daily/weekly/monthly/branch/employee reports and exports | Monitoring filters available; formal reports/exports deferred |

An attempt may fail without a record. An event is an original submitted punch, including pending/rejected evidence. A record summarizes the effective accepted punches. “Location validated” means reported data passed business rules; “exception approved” records an HR decision without rewriting failed GPS. No facial/identity verification, enrollment, liveness, gallery upload, offline punch, arbitrary branch selection or client-controlled official timestamp is authorized.

## Permission matrix

| Operation | Employee | HR |
| --- | --- | --- |
| Own profile/attendance/history/photos | Own eligible linked employee only | Own eligible linked employee using same login |
| Submit Time In/Out | Own only | Own only, if linked/eligible |
| View others' attendance/photos/reported coordinates | Denied | Company-wide |
| Manage users/branches/departments | Denied | Company-wide, with existing account safeguards |
| Review flagged attendance | Denied | Pending only; another HR must review own submission |
| Dispose assistance request | Denied | Required remarks; no self-disposition; creates no punch |
| Change submitted photo/original event time/location | Denied | Denied through application |
| Audit logs | Denied | Company-wide |
| Assign extra roles/public signup | Denied | No extra roles/public signup exist |

## Non-functional requirements

- Responsive desktop/laptop/tablet/mobile browser UI, keyboard-accessible labels/statuses, large attendance actions and contained table scrolling. Test widths 320/360/375/390/430/768/1440. Automated Edge coverage does not certify iOS/Android hardware.
- Stable existing stack and reusable Blade/Alpine components; no unnecessary dependencies. Exact source paths and migrations documented.
- Authenticated HTTPS, CSRF, server validation/authorization, parameterized Eloquent queries, escaped views, whitelisted sorting, rate limiting, private storage and least-privilege infrastructure.
- UTC authoritative receipt times, Manila display/work date, atomic writes and idempotent same-result retries. Do not invent offline times after disconnection.
- Bounded image sizes and decoding dimensions, eager loading/pagination/indexes, short write locks. Production capacity/RPO/RTO/SLA require measurement and company agreement.
- Privacy notices, lawful basis, PIA, restricted data access, defined retention/backup/rights/incident procedures before real employee collection. Browser permissions are not legal consent. See [privacy plan](ATTENDANCE_SECURITY.md).

## Company confirmation register

| ID | Decision | Owner / effect |
| --- | --- | --- |
| Q-01 | Official 27-branch list, legal entity/branding, employee counts and peak arrivals | HR/sponsor; capacity and master data |
| Q-02 | Actual branch coordinates and individual radii, indoor/boundary trials | HR/operations; normal geofence acceptance |
| Q-03 | 50-metre accuracy, 45-second freshness, capture lifetime and clock-skew tolerance | IT/HR; development defaults currently configurable |
| Q-04 | Exception eligibility, review deadlines, missing-camera/accessibility remedy, escalation and HR self-review coverage | HR/DPO; equitable usable rollout |
| Q-05 | Transfers/open sessions, multiple punches, checkout windows, missing-punch corrections | HR; later workflow definitions |
| Q-06 | Shift patterns, grace, overnight attribution, breaks, holidays, overtime and absence cutoffs | HR; scheduling/classification remains unimplemented |
| Q-07 | Leave types/days/overlap/balances and approval delegation | HR; next leave phase |
| Q-08 | Photo/location/core-record/assistance/capture/audit/backup retention, legal holds and rights requests | DPO/HR/IT; current 30/90-day defaults are not legal policy |
| Q-09 | Lawful basis, employee notice, PIA, DPO contact, processor terms and applicable NPC duties | DPO/company advisers; real collection gate |
| Q-10 | Recovery delivery, privileged MFA requirements, production session policy | IT/security; production auth hardening |
| Q-11 | Approved hosting/region, database parity, backup RPO/RTO, patch/incident owners | IT/sponsor; deployment/recovery gate |
| Q-12 | Actual browser/OS/device inventory and trusted HTTPS mobile test environment | IT; real-device compatibility |
| Q-13 | Report layouts/authorized exports and notification recipients | HR; later reporting phase |

MVP release includes implemented account/master-data/attendance features plus separately requested scheduling/corrections/leave/reports and verified production privacy/security operations. Payroll, advanced analytics, external notifications, mapping services, authorized off-site attendance and integrations require explicit future approval. Facial recognition is removed rather than deferred.
