# Final leave approval workflow
Employee submits → assigned Supervisor approves or rejects → Employee receives the final decision. HR monitors/configures and resolves assignments. There is no HR approval or recommendation stage.

States: awaiting_supervisor_assignment, pending_supervisor_approval, approved, rejected, cancelled.

Submission validates account/profile, active type, dates/duration, overlap, half-day rules and required private document. It records encrypted reason, type snapshot, submitted date, history/audit and persistent notifications. No eligible Supervisor saves the application awaiting assignment and informs HR.

The final decision requires active Supervisor status, current explicit assignment, stored request routing, a different requester, pending state, confirmation and remarks (including rejection reason). Approval rechecks requester eligibility, active/unchanged relevant policy, half-day permission, documents and balance. If policy changes incompatibly, HR must clarify configuration and the employee may cancel/reapply; HR cannot override approval.

All account/assignment/decision/balance workflows serialize on the existing HR Role row, then lock the request and balance within a database transaction. A request state check rejects stale or repeated decisions with HTTP 409. The generated unique final_request_id on leave_reviews prevents two final reviews. A unique leave_request_id on balance transactions prevents double deduction. Audit, balance, review, state and database notifications commit atomically.

Cancellation is owner-only for either pending state, with a reason and confirmation. It retains the original application, records previous state/history/time and notifies the current reviewer. Approved/rejected/cancelled requests cannot be approved again.

Supervisor decisions immediately set reviewed_at and finalized_at. HR monitoring displays the result with no Approve/Reject controls or ordinary decision route. SweetAlert confirmation is interactive; only a successful backend response emits the compact top-right 3000 ms toast.

Reassignment history remains accessible to owner and HR. Current assignment is always required for Supervisor details, documents, history and decisions.
