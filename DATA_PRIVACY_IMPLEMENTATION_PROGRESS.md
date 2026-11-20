# LIMCOMA Data Privacy implementation progress

## Phases 1–2 — Audit and schema

Laravel 13 and Fortify 1.42 use the existing role-aware login response and active-account and Supervisor restrictions. The workspace applies private, no-store responses and only allows camera and location permission on attendance capture. The new migration creates privacy_notices and privacy_notice_acknowledgements. It enforces unique version identifiers, unique user/version acknowledgements and restrictive foreign keys. An effective published version has an immutable record and a SHA-256 content hash. The seeded example is a draft with explicit decision gaps. It is not registered in DatabaseSeeder or activated automatically.

## Phases 3–5 — Notice, management and Privacy Center

The role-aware login response and authenticated routes consult the database when the feature is enabled. The current effective version is selected by effective date and version record ID. Only versions that explicitly require acknowledgement gate ordinary workspace routes. The notice and authenticated privacy routes, Fortify authentication routes, and logout remain available. JSON workspace requests receive a 403 with the notice URL. Key acknowledgements to the signed-in account and exact current version. Submissions are validated, CSRF-protected, throttled, and transactional. Duplicate submissions preserve the earliest acknowledgement and its audit event. A stale version produces a conflict and requires a new review.

The profile menu links to Privacy & Data Protection. It shows the effective notice, full version text, own acknowledgement history, published version history, NPC rights information, and the verified contact if supplied. The application only displays the contact recorded in a published version. The approved notice must describe request channels and their identity verification procedures. An in-app ticket workflow is deferred until LIMCOMA provides an authorized receiver, handling and retention rules. No consent-based activity requiring a separate consent screen was established in this audit.

Manage privacy notices is available only to enabled, configured HR account IDs with recent password confirmation. Editors save and preview drafts. A publisher must confirm the exact preview revision and enter the authorized approval reference. Versions become visible at the effective timestamp. Archiving is limited to superseded versions and keeps previous text and acknowledgements readable. Privacy audit events use the existing audit log with actor, action, version and server timestamp. They omit request IP, user-agent and notice contents.

## Phase 6 — Existing evidence and session protections

| Area | Verified implementation and remaining review |
| --- | --- |
| Attendance photos | The camera is limited to attendance capture. Stored photo bytes use the attendance disk and application encryption. Retrieval authorizes the underlying attendance record and uses private, no-store delivery. The product uses photographs as visual evidence. DPO review is needed for necessity, use and retention. |
| GPS | The attendance flow uses reported coordinates and accuracy for event verification. The authorized record detail exposes raw coordinates in technical evidence. No raw latitude or longitude fields were found in the scanned mail templates. Review collection necessity, authorized map access, providers, exports and location retention. |
| Leave documents | Supporting files are served through authenticated record checks and protected preview/download, with sandbox headers on supporting previews. The Supervisor's scope stays tied to authorized leave. The public QR entry page accepts a token and redirects to authenticated verification. Record authorization gates verification results and original PDF retrieval. Review electronic signatures, PDF handling and actual operational recipients. |
| Sessions | Existing active-account checks, authentication, logout, and framework CSRF protections remain in use. Production config forces Secure cookies and defaults to HttpOnly and SameSite=Lax. Verify HTTPS, scope, idle timeout, trusted proxy, and shared-workstation policy in the deployment. |
| Retention and incident response | The attendance purge clears photo bytes and raw location detail while retaining core timestamps and review decisions. Review ATTENDANCE_PHOTO_RETENTION_DAYS, ATTENDANCE_REVIEW_HOLD_DAYS and LEAVE_DOCUMENT_RETENTION_DAYS against approved schedules and legal holds. The code defaults are technical settings. No new automatic disposal or breach notifications are configured. The authorized incident team should assess whether mandatory notification applies. NPC Circular 16-03 describes a 72-hour notification framework for qualifying incidents. |

Sources for review are the [Data Privacy Act](https://officialgazette.gov.ph/2012/08/15/republic-act-no-10173/), the [NPC right-to-be-informed guidance](https://privacy.gov.ph/the-right-to-be-informed/), the [NPC data subject rights guidance](https://privacy.gov.ph/data-subject-rights/), and [NPC Circular 16-03](https://privacy.gov.ph/wp-content/uploads/2022/01/sgd-npc-circular-16-03-personal-data-breach-management.pdf). The technical implementation does not establish legal compliance.

## Phase 7 — Verification

In progress. Isolated test accounts and database only. Browser work targets the dedicated notice with native labelled forms, explicit submission, and server validation. The retrieved Modern Web Guidance `forms` guide informed checkbox labelling, named forms, and visible required fields.

## Phase 8 — Deployment and DPO sign-off

1. Review the migration in staging, back up before the normal deployment migration, and run the existing Vite production build.
2. After migrating, set PRIVACY_NOTICES_ENABLED=true and configure PRIVACY_NOTICE_PUBLISHER_IDS as a comma-separated list of designated HR user IDs. Default deployment leaves the feature disabled and assigns no publishers.
3. Create or optionally seed the review draft with `php artisan db:seed --class=PrivacyNoticeSeeder --no-interaction`. Edit the draft using the actual, approved content and verified contact. Confirm category-specific lawful bases, photos and GPS, sensitive documents, recipients, retention and legal holds, handling of employee requests, and incident escalation.
4. Obtain the authorized approval reference for the precise text and any mandatory acknowledgement. With recent password confirmation, preview the revision and publish deliberately. Test the expected role paths and contact route before enabling the gate for real staff.

## Implementation files

- config/privacy.php and the create_privacy_notice_tables migration.
- PrivacyNotice and PrivacyNoticeAcknowledgement models, their factories and seeders.
- PrivacyNoticeController, HR/PrivacyNoticeController, RequirePrivacyAcknowledgement, RestrictSupervisor, RoleLoginResponse, AppServiceProvider, AuditService, bootstrap/app.php and routes/web.php and routes/phase5.php.
- The account menu, dedicated privacy layout and privacy views for notice text, history, draft editing and publication.
- Privacy notice feature and browser regressions in the existing test suites.
