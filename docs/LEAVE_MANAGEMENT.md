# Leave management — implemented Phase 5
The working modules are Employee My Leave (/employee/leave), Apply for Leave (/employee/leave/create), Supervisor Leave Management (/supervisor/leave) and HR Leave Monitoring (/hr/leave). HR manages types/policies at /hr/leave/types and balance ledger adjustments at /hr/leave/balances. Lists are scoped, filtered and paginated.

HR must enter verified company types. No official type, accrual, entitlement, holiday, rest day or automatic annual credit is seeded. A type defines active status, supporting-document requirement, balance impact and single-date half-day permission. Types are retained historically; applications snapshot relevant policy values. HR can view applications by type and deactivate types.

## Interim duration policy
Scheduling is not implemented. The employee enters requested dates and requested days using the actual company work schedule. The application does not automatically count all calendar dates as chargeable days. A balance-affecting approval requires the Supervisor to explicitly confirm chargeable days against the actual schedule and holidays. The amount cannot exceed requested days, calendar span or configured half-day precision. Zero can be explicitly confirmed where policy warrants no charge.

Requests permit positive whole days. A fractional request is limited to one date, 0.5 day, AM/PM, and a type allowing half days. A bounded request spans at most 366 calendar dates; this is an operational validation limit, not an entitlement. Overlaps with pending/approved requests are blocked conservatively at whole-date granularity, including AM versus PM. Past-date deadlines are not invented. Balance-based requests crossing calendar policy years must be split.

## Balances
Annual policy_period is YYYY. Each employee/type/year has a unique balance. No row means unconfigured, not a fabricated entitlement. HR enters authorized credits or corrections with mandatory reason and confirmation; a ledger preserves authorizer, amount and timestamp. No negative available balance is allowed. Half-day arithmetic uses integer units.

Submission never deducts balance. Final Supervisor approval validates a configured sufficient balance, locks it and creates exactly one approved_debit ledger transaction linked uniquely to the request. Rejection/cancellation does not deduct. Approved cancellation/reversal, accrual, carry-over and payroll integration await company policy and are outside this phase.

## Supporting documents
Device-file PDF/JPEG/PNG uploads are allowed for leave; camera-only restrictions still apply to attendance photos. File rules enforce extensions, content MIME, configured size (default 5120 KB), PDF header or bounded decodable image content. Files use random paths, Laravel encryption and a private disk with serve=false. Download routes authorize owner, HR or currently assigned Supervisor; responses are attachment/no-store/nosniff/sandbox.

leave.document_retention_days defaults to null until the company confirms retention. A configured value sets purge_after at upload; changing configuration does not retroactively rewrite existing expiry dates. leave:purge-documents removes expired bytes and retains document metadata/history. Review requires an available required document. APP_KEY and encrypted backups must be retained securely.

Company confirmation still needed: official type names and guidance; credits/policy years/accrual/carry-over; schedules/holidays; document requirements/retention/legal holds; cancellation/reversal rules; request deadlines; authorized Supervisor coverage. Existing operational privacy and real-device rollout requirements still apply.
