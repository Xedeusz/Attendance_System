# Attendance security and privacy — Phase 4

## Enforced controls

| Boundary | Implemented protection |
| --- | --- |
| Login | Existing Fortify session authentication, password hashing, activation, account/session revocation, CSRF and throttling |
| Identity | Resolve employee from authenticated account; active employment/account checked again inside the write transaction; spoofed employee/user/branch/status/punch-time fields prohibited |
| HR access | Backend HR middleware and fresh actor validation through `Administration`; three roles: HR company-wide attendance, Employee own attendance; Supervisor denied all attendance/photo/GPS/correction access |
| Capture | Random 256-bit token hashed at rest, UUID, session/password/session-version binding, 180-second expiry, action/assignment/record/version/policy binding, one consumed event |
| Duplicates | Database unique constraints, transactions, locked employee/administration row, attendance version, request fingerprint, same-result retries and conflicting replay rejection |
| GPS | Server range/age/accuracy/Haversine checks; no trusted client “verified” result; snapshot invalidation on policy change |
| Image | 2 MiB limit, actual MIME/content/dimension/decode checks, GD re-encoding/metadata stripping, randomized paths, normalized hash repeat signal |
| Storage | Private non-served local disk, encrypted file bytes, protected owner/HR controller, inline JPEG/nosniff/no-store; no public storage URL |
| Details | Record policy before data/photo response; paginated owner-scoped history and refresh-based review updates |
| Review | Pending-only, required remarks/confirmation, no HR self-review, one immutable decision, original failed GPS/time preserved, unavailable photo blocks approval |
| Browser | Same-origin camera/geolocation only on capture page; microphone denied; frame denial, no-store and referrer policy remain |
| Audit | Submitted/Time In/Time Out/validation failed/flagged/review/assistance/purge actions, employee or event IDs and necessary state only; no raw image/GPS/token/password |

Tokens exist only in component memory/request body, not URLs or localStorage. A retry status endpoint requires the original authenticated browser session. File storage happens before the short database transaction; failed/duplicate operations discard the staged file. Review/status responses never convert a pending/rejected attempt into false success.

## Residual risks and deployment requirements

Browser camera data can come from virtual cameras or fabricated HTTP requests; location can be inaccurate/spoofed, and client timestamps can be manipulated. Tokens and hashes reduce replay/coordination risk but cannot attest capture origin, freshness, identity or physical presence. No face matching, liveness, identity score or fraud-proof claim exists. A repeated image is a review signal only.

The existing global HR role-row lock provides correctness with Phase 3 administrative changes but serializes short attendance commits across branches. Measure realistic peak workloads before rollout. A future performance change must replace it with a consistent employee/assignment/branch locking protocol across both administration and attendance, then test deadlocks and races on MariaDB/MySQL. Do not remove this lock only in attendance.

Use HTTPS, secure cookies, production debug off, least-privilege DB/storage credentials, restricted backups, maintained PHP/GD and dependencies, monitored scheduler, time synchronization, restore drills and incident response. Laravel must serve only `public/`. Configure web/PHP upload limits to support the 2 MiB application ceiling with multipart headroom. Private data and keys must stay out of Git/logs/screenshots. This phase does not add MFA, malware infrastructure, WAF, immutable external audit storage or a production hosting deployment.

Retention deletes filesystem evidence before marking SQL; failure/rollback can leave a missing file, while process death during staged upload can leave an orphan. Missing evidence is shown as unavailable and cannot be approved. Run scheduled pruning, monitor errors and reconcile storage. The manual `--orphans` scan does a DB existence lookup per file and should be evaluated for large storage volumes.

## Philippine privacy implementation plan

Attendance photos and reported locations are personal data. The company/DPO must establish an applicable lawful basis, necessity and proportionality for employment monitoring, publish a notice, and approve access/retention/exception policies. Browser permission is an API permission, not proof of valid legal consent. Apply the Data Privacy Act principles and the NPC's government/private-sector security requirements; completion of code is not a compliance certification. [Data Privacy Act, sections 11–12](https://privacy.gov.ph/data-privacy-act/) and [NPC Circular 2023-06 announcement](https://privacy.gov.ph/npc-issues-circulars-to-strengthen-personal-data-protection-in-ph/).

Before real collection the DPO/HR must:

1. Complete a privacy impact assessment and record purposes, lawful basis and recipients. Decide whether incidental sensitive information may appear and how to restrict it.
2. Publish employee notice describing each collected field, camera/location limitations, HR access, rights, DPO contact and retention. Instruct employees to photograph only appropriate office evidence, avoiding bystanders, confidential screens and unnecessary sensitive information.
3. Provide an accessible HR-assisted alternative for employees unable to use camera/location. The current assistance path records support only; the company must define the lawful attendance remedy before production.
4. Approve differentiated photo/location (development default 30 days), pending review (90 days), core attendance/audit/assistance/capture and backup lifetimes, including dispute/hold procedures. The numbers are not legal requirements. The code has a bounded pending hold; it does not implement a legal-hold override.
5. Establish access/correction/deletion request processes, retention verification, backup expiry/deletion ledger, staff confidentiality, recovery-key custody and processor agreements where applicable.
6. Verify incident/breach response, security training, access reviews, production recovery and applicable NPC registration/notification obligations with responsible company advisers.

Images and exact reported coordinates are automatically removed by `attendance:prune-evidence`; core records/reviews remain. Assistance/audit/capture metadata lifetimes and selective subject-request erasure need company policy and later authorized work. Do not promise unconditional deletion or indefinite evidence preservation.

Image defense follows the layered approach in [OWASP File Upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html). No format check can prove that a technically valid office photograph reflects a real attendance event.
