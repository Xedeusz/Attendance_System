# Supervisor assignments
HR alone manages explicit employee assignments at /hr/supervisors/{supervisor}. Branch or department membership never grants automatic review permission.

Effective dates are inclusive in attendance.timezone (Asia/Manila). The Supervisor account must have role 3 and be active; linked employment must be active. The employee profile must be active, and cannot belong to the selected Supervisor. Conflicting enabled date intervals are rejected unless HR explicitly selects replacement.

Immediate replacement revokes overlapping assignments while retaining their original dates and revoked_at. Future replacement shortens an earlier assignment to the day before the new start date, preserving current access until that date; superseded intervals beginning at or after the new start are revoked. Each change is audited. There is no assignment deletion endpoint.

Pending requests are rerouted under the same administration transaction lock. Their original submitted_at never changes. LeaveReview records record old/new Supervisor IDs. Newly assigned Supervisors receive a review notification; previous Supervisors receive a generic reassignment notice without a working link to the confidential application.

No eligible assignment means awaiting_supervisor_assignment, with HR notified to resolve routing. HR assigns a reviewer; HR does not approve the application.

leave:refresh-routing runs hourly to handle future starts, expirations and eligibility changes. Authorization checks current dates/account status on every list, detail, document and decision access, so expiration/revocation blocks access immediately even before this job runs. Final decisions/history are never rerouted. HR can monitor and manually refresh pending routing.


## Direct Supervisor department assignment

HR can now link a Supervisor directly to one existing active department, independently of an Employee profile. New employee assignments and leave scope require both explicit effective assignments and matching active departments. Existing history is preserved; missing/mismatched links require explicit HR resolution. See [Supervisor department linking](SUPERVISOR_DEPARTMENT_LINKING.md) for schema, conflict handling, authorization and verification.
