# HR-Managed Accounts and Activation

Phase 3. Public registration is disabled. Accounts are created by HR, except the first HR account through the authorized CLI bootstrap.

## Employee registration

1. Sign in as HR and add official active branches/departments; no fictional company data is seeded.
2. Open **Employee Management → Register employee**.
3. Enter employee number, names, work email, optional contact details, active primary branch, company-wide department, position title, hire date and employment status.
4. Backend checks authorization, unique number/email, field formats and active referenced records. `role`, `role_id`, `account_status`, passwords and user-ID injection are rejected.
5. In one short transaction, create the User with fixed Employee role/pending status, Employee profile, current primary assignment, invitation hash and safe audit records.
6. After commit, send the invitation through the configured framework mailer. Failure does not roll back the registered employee; it leaves the account pending and reports accurate failure/configuration state.

No initial plaintext password is supplied to HR or sent to the employee. An unguessable random initial credential cannot be used as an advertised login. HR can resend while pending; resend revokes the prior token.

## Invitation/activation protocol

- Random 32-byte token represented as 64 hex characters; only SHA-256 hash is stored in `account_invitations`.
- Token bound to a specific user, expires in 24 hours, and has used/revoked timestamps.
- URL format is `/activate-account#TOKEN`, intentionally using a fragment rather than a secret path/query. The browser fragment is not sent in HTTP request paths. JavaScript consumes it, removes it from history and submits it in a CSRF-protected POST body.
- JavaScript is required for activation. On a password error the token remains only in page memory; on refresh reopen the original complete invitation link. Tokens are not flashed into Laravel's old-input session data.
- GET shows no account identity. POST validates token shape, hash, expiry, use/revocation, pending account state, and employment eligibility.
- Valid activation sets the chosen hashed password, active status and verified email, rotates session/remember state, consumes the invitation and audits activation. The user then signs in normally.
- Invalid, expired, used, replaced, deactivated or employment-ineligible cases never grant login. Activation writes share the administrative serialization lock, preventing concurrent reuse or deactivation races.

HTTPS is required for real deployment. Do not introduce request-body logging, URL analytics, or error telemetry that captures secrets. Default framework password-reset URLs also require redaction at production proxies/APM.

## Local invitation delivery

The project uses a free built-in **private local mail preview** transport for development. Bodies are encrypted in the database with the application key. HR opens **Settings → Protected local mail inbox**, confirms the current password, and views a preview. It is **not an externally delivered email**. Use an approved private channel or separate browser context for local testing.

Message access expires after 24 hours. Resending/revoking or activating an invitation removes the target recipient's old local previews. Scheduled `accounts:prune-local-mail` removes expired rows; run the scheduler or command to purge at rest. The inbox is inaccessible outside `APP_ENV=local` and never available to Employees.

Delivery status meanings:

| Status | Meaning |
| --- | --- |
| `pending` | Invitation committed, delivery not completed |
| `local_preview` | Encrypted local capture succeeded; no external delivery |
| `submitted` | Configured SMTP accepted the submission; not proof of inbox receipt |
| `failed` | Delivery attempt failed; HR may fix configuration and resend |
| `not_configured` | Unsupported/unapproved delivery mechanism; nothing claimed delivered |

Production must use an approved SMTP channel and verified APP_URL/HTTPS. Log/array transports are not accepted for invitation delivery. No paid service is needed for Phase 3.

## Edits and lifecycle

- Ordinary profile/employment edits preserve the password. Work email is stored only on `users`; `employees.work_email` is a read accessor.
- A changed work email invalidates sessions/reset credentials and previous invitations, clears verified status, and requires activation at the new address. The old password is not silently changed by the edit. HR email cannot be changed through employee editing.
- Deactivation needs HR confirmation, preserves history, revokes invitations, rotates remembered credentials, increments session version and deletes DB sessions/reset tokens. Subsequent requests, including an already-open browser, lose access.
- Reactivation is an explicit HR action. Previously activated/eligible accounts can return to active; never-activated accounts return to pending with a new invitation. Inactive employment cannot reactivate.
- Password changes use Fortify and require the correct current password. The same strength policy applies to bootstrap/activation/recovery; other DB sessions are revoked.
- Pending/deactivated users do not receive recovery mail. Reset tokens cannot activate accounts or bypass account state. Activation/issuance and email changes invalidate stale reset tokens.

## HR accounts

Use `php artisan app:bootstrap-hr` once for the first HR. Name/email/password are prompted privately; no default credentials exist. An existing Employee is not silently promoted. Once any HR exists, use **Settings → HR Account Management**, requiring recent password confirmation. The separate HR form creates a pending HR account and invitation. It cannot be reached by changing a field on employee registration.

Last-active-HR deactivation is rejected. HR accounts can have an optional one-to-one employee profile and use the shared personal dashboard; no duplicate login or additional role is needed. A dedicated operational HR/profile linking UI remains future work.

## Employee self-service

The Employee dashboard/profile shows only the signed-in employee's data and assigned branch. Contact number is the only editable personal field. Names, email, employee number, role, department, position, branch and employment/account states are managed by HR. Password changes are in Account Settings. Attendance/biometric/leave sections are explicitly unavailable.
