# Special Leave Eligibility Verification Progress

## Implemented

- Employee maternity and paternity eligibility requests with event details, declarations, private supporting-document uploads, status tracking, withdrawal, and resubmission after an HR information request.
- HR work queue with employee, leave type, and status filters; protected document review; and decisions for verified, additional information required, or not eligible.
- Event entitlement activation after verification: 105 days for standard maternity, 120 days for solo-parent maternity, 60 days for miscarriage or emergency termination of pregnancy, and 7 working days for paternity.
- Integration with the existing leave-credit ledger, leave application choices, supervisor approval workflow, audit trail, and in-application/email notification infrastructure.
- Authorization that limits employee records and documents to their owner and HR, prevents supervisors from making eligibility decisions, and keeps sensitive eligibility details out of the supervisor approval view.
- Server-side validation for leave type, event category, dates, declarations, document type/size/count, duplicate requests, decision transitions, and entitlement idempotency.
- Regression coverage for private documents, cross-employee access denial, information-request/resubmission, maternity and paternity grants, duplicate decisions, HR-only decisions, and availability in the leave application.

## Database

- `2026_11_26_053139_create_special_leave_eligibility_tables.php` creates eligibility requests, encrypted activity remarks, private document metadata, and the link from a verified request to its leave entitlement.

## Confirmed policy boundaries

- Paternity qualifying conditions and the exact evidence checklist remain an HR review decision; the system records the decision without trying to infer legal eligibility from sensitive personal data.
- The optional unpaid maternity extension is not converted into paid leave credits. A separate unpaid-leave workflow should be added only after HR confirms its approval and payroll rules.
- The current credit ledger scopes event entitlements to the event year. Events whose approved absence crosses a calendar-year boundary need an HR policy decision before changing ledger allocation semantics.
- Time-sensitive cases can immediately submit a verification request and notify HR, but they do not bypass eligibility verification into supervisor approval.

## Verification

- Focused PHPUnit coverage: `tests/Feature/SpecialLeaveEligibilityTest.php`.
- Existing leave-credit regression coverage: `tests/Feature/LeaveCreditManagementTest.php`.
- Formatting: Laravel Pint with the project agent format.
