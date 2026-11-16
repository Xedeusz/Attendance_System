# Supervisor role — Phase 5
Updated 2026-11-16. Exactly HR (1), Employee (2), Supervisor (3) exist. HR remains system administrator. Supervisor is restricted to leave review and necessary personal account utilities.

HR provisions accounts at /hr/supervisors with recent password confirmation. New users receive the existing expiring, hashed, single-use invitation and choose their own password. Delivery feedback distinguishes protected local preview, SMTP submission and failure. Public registration stays disabled.

For an existing Employee, HR selects the linked identity and its existing email. The same User and Employee IDs, password, employment history and attendance evidence are retained. The controlled promotion increments session_version, rotates remember credentials, deletes sessions/reset tokens, revokes old invitations and audits the transition. Unverified/new accounts cannot be made active directly.

After login, Supervisor lands at /supervisor/leave. Navigation contains Leave Overview, Pending Leave Requests, Leave History, Leave Notifications, personal account settings, and My Leave only when an employee profile exists. Settings expose personal password/session controls; HR administration is guarded separately.

RestrictSupervisor middleware denies unrelated routes, including direct HR, attendance, photo, GPS, correction, global notification, audit and report URLs. Owner policies additionally deny Supervisor access to their own attendance evidence. Leave queries and policies require both the stored assigned Supervisor and a currently effective explicit assignment. Historical/revoked assignment ownership alone grants no access. Only necessary leave employee/branch/department/balance/document details are shown.

A Supervisor's own application needs a different assigned Supervisor. Self assignment and self review are rejected. No role inherits HR privileges.
